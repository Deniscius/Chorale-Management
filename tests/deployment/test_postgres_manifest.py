import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / 'deploy/postgres_manifest.py'


class ManifestTest(unittest.TestCase):
    def test_compare_rejects_data_sequence_and_catalog_differences(self):
        source = {'format': 1, 'tables': {'public.users': {'rows': 2, 'sha256': 'original'}},
                  'sequences': {'public.users_id_seq': {'last_value': 2, 'is_called': True}},
                  'constraints': [['users', 'users_pkey']]}
        with tempfile.TemporaryDirectory() as directory:
            before, after = (Path(directory) / name for name in ['before.json', 'after.json'])
            before.write_text(json.dumps(source))
            after.write_text(json.dumps(source))
            self.assertEqual(0, subprocess.run([sys.executable, SCRIPT, 'compare', before, after], capture_output=True).returncode)
            for field in ['tables', 'sequences', 'constraints']:
                changed = dict(source)
                changed[field] = {}
                after.write_text(json.dumps(changed))
                result = subprocess.run([sys.executable, SCRIPT, 'compare', before, after], capture_output=True)
                self.assertEqual(1, result.returncode)
                self.assertIn(field.encode(), result.stderr)
            before.write_text(json.dumps({'format': 1, 'tables': {}}))
            after.write_text(before.read_text())
            self.assertEqual(1, subprocess.run([sys.executable, SCRIPT, 'compare', before, after], capture_output=True).returncode)

    def test_snapshot_preserves_existing_evidence_and_refuses_cli_credentials(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory) / 'evidence.json'
            target.write_text('keep')
            for service in ['render_source', 'postgresql://user:secret@example.test/db']:
                result = subprocess.run([sys.executable, SCRIPT, 'snapshot', '--service', service,
                                         '--output', target], capture_output=True)
                self.assertEqual(2, result.returncode)
                self.assertNotIn(b'secret', result.stderr)
                self.assertEqual('keep', target.read_text())


if __name__ == '__main__':
    unittest.main()
