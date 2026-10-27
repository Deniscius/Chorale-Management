#!/usr/bin/env python3
"""Read-only PostgreSQL fingerprints. Freeze ALL writers before taking snapshots.

Credentials belong in libpq service/pass files (mode 0600), never CLI URLs.
Only counts, hashes and schema/sequence metadata are saved, never row contents.
"""

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys


def identifier(value):
    return '"' + value.replace('"', '""') + '"'


def command(service, sql):
    return [os.environ.get('PSQL_BIN', 'psql'), '-X', '-q', '-A', '-t', '-w',
            '-v', 'ON_ERROR_STOP=1', '--dbname=service=' + service, '--command', sql]


def environment():
    result = os.environ.copy()
    result['PGOPTIONS'] = (
        '-c default_transaction_read_only=on -c search_path=pg_catalog '
        '-c timezone=UTC -c extra_float_digits=3 -c bytea_output=hex -c datestyle=ISO,YMD'
    )
    return result


def query(service, sql):
    result = subprocess.run(command(service, sql), env=environment(), capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError('psql failed; inspect connectivity/permissions securely (no credentials logged).')
    return [json.loads(line) for line in result.stdout.splitlines() if line]


def fingerprint(service, relation):
    sql = f'COPY (SELECT row_to_json(t)::text FROM {relation} t ORDER BY row_to_json(t)::text COLLATE "C") TO STDOUT'
    digest, rows = hashlib.sha256(), 0
    # Do not buffer whole tables or large private files in memory.
    with subprocess.Popen(command(service, sql), env=environment(), stdout=subprocess.PIPE,
                          stderr=subprocess.DEVNULL) as process:
        while chunk := process.stdout.read(1024 * 1024):
            digest.update(chunk)
            rows += chunk.count(b'\n')
        if process.wait():
            raise RuntimeError('psql fingerprint failed; no partial manifest was saved.')
    return {'rows': rows, 'sha256': digest.hexdigest()}


def snapshot(service, schemas):
    names = ','.join("'" + name.replace("'", "''") + "'" for name in sorted(set(schemas)))
    scope = f'n.nspname IN ({names})'
    relations = query(service, f"""
        SELECT json_build_object('schema', n.nspname, 'name', c.relname, 'kind', c.relkind)
        FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
        WHERE {scope} AND c.relkind IN ('r','p','m','S') ORDER BY n.nspname, c.relname
    """)
    result = {'format': 1, 'schemas': sorted(set(schemas)), 'tables': {}, 'sequences': {}}
    for item in relations:
        relation = identifier(item['schema']) + '.' + identifier(item['name'])
        if item['kind'] == 'S':
            result['sequences'][relation] = query(
                service, f'SELECT json_build_object(\'last_value\', last_value, \'is_called\', is_called) FROM {relation}'
            )[0]
        else:
            result['tables'][relation] = fingerprint(service, relation)
    result['columns'] = query(service, f"""
        SELECT json_build_array(n.nspname,c.relname,a.attnum,a.attname,
          format_type(a.atttypid,a.atttypmod),a.attnotnull,a.attidentity,a.attgenerated,
          pg_get_expr(d.adbin,d.adrelid))
        FROM pg_attribute a JOIN pg_class c ON c.oid=a.attrelid
        JOIN pg_namespace n ON n.oid=c.relnamespace
        LEFT JOIN pg_attrdef d ON d.adrelid=a.attrelid AND d.adnum=a.attnum
        WHERE {scope} AND c.relkind IN ('r','p','m','v') AND a.attnum>0 AND NOT a.attisdropped
        ORDER BY n.nspname,c.relname,a.attnum
    """)
    result['constraints'] = query(service, f"""
        SELECT json_build_array(n.nspname,c.relname,k.conname,pg_get_constraintdef(k.oid))
        FROM pg_constraint k JOIN pg_class c ON c.oid=k.conrelid
        JOIN pg_namespace n ON n.oid=c.relnamespace WHERE {scope}
        ORDER BY n.nspname,c.relname,k.conname
    """)
    result['indexes'] = query(service, f"""
        SELECT json_build_array(n.nspname,c.relname,i.relname,pg_get_indexdef(i.oid))
        FROM pg_index x JOIN pg_class c ON c.oid=x.indrelid
        JOIN pg_class i ON i.oid=x.indexrelid JOIN pg_namespace n ON n.oid=c.relnamespace
        WHERE {scope} ORDER BY n.nspname,c.relname,i.relname
    """)
    result['sequence_definitions'] = query(service, f"""
        SELECT json_build_array(n.nspname,c.relname,format_type(s.seqtypid,NULL),
          s.seqstart,s.seqincrement,s.seqmax,s.seqmin,s.seqcache,s.seqcycle)
        FROM pg_sequence s JOIN pg_class c ON c.oid=s.seqrelid
        JOIN pg_namespace n ON n.oid=c.relnamespace WHERE {scope} ORDER BY n.nspname,c.relname
    """)
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    actions = parser.add_subparsers(dest='action', required=True)
    take = actions.add_parser('snapshot')
    take.add_argument('--service', required=True)
    take.add_argument('--schema', action='append', dest='schemas')
    take.add_argument('--output', type=Path, required=True)
    compare = actions.add_parser('compare')
    compare.add_argument('source', type=Path)
    compare.add_argument('target', type=Path)
    args = parser.parse_args()
    if args.action == 'snapshot':
        if not re.fullmatch(r'[A-Za-z0-9_-]+', args.service):
            parser.error('Use a libpq service name, never a connection URL.')
        if args.output.exists():
            parser.error('Output already exists; choose a new file to preserve evidence.')
        result = snapshot(args.service, args.schemas or ['public'])
        # O_EXCL and mode 0600 protect existing evidence and the metadata.
        with os.fdopen(os.open(args.output, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), 'w') as file:
            json.dump(result, file, sort_keys=True, indent=2)
            file.write('\n')
        print(f"Manifest saved: {len(result['tables'])} tables, {len(result['sequences'])} sequences.")
        return 0
    source, target = (json.loads(path.read_text()) for path in [args.source, args.target])
    differences = sorted(key for key in source.keys() | target.keys() if source.get(key) != target.get(key))
    if differences:
        print('MISMATCH: ' + ', '.join(differences), file=sys.stderr)
        return 1
    if source.get('format') != 1 or not source.get('tables'):
        raise RuntimeError('Empty or unsupported manifest cannot validate a migration.')
    print('MATCH: all table rows, sequence states, columns, constraints and indexes agree.')
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, ValueError, RuntimeError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
