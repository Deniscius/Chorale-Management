import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { createHmac } from 'node:crypto';
import assert from 'node:assert/strict';

const origin = process.env.ACCESSIBILITY_BASE_URL || 'http://127.0.0.1:8091';
assert.ok(['localhost', '127.0.0.1', '[::1]'].includes(new URL(origin).hostname), 'Local fixture required');
assert.ok(process.env.ACCESSIBILITY_CREDENTIALS_FILE, 'Use the private browser fixture credentials');
const credentials = JSON.parse(await readFile(process.env.ACCESSIBILITY_CREDENTIALS_FILE, 'utf8'));
const directory = process.env.DESIGN_ARTIFACTS || 'test-results/design';
await mkdir(directory, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined, args: ['--no-sandbox'] });
const results = [], failures = [], errors = [], remote = [];
const pages = ['/dashboard', '/membres', '/choristes/accueil-et-suivi', '/oeuvres', '/repetitions', '/repetitions/rythmes', '/celebrations', '/temps-liturgiques', '/liturgie/maitres-de-choeur', '/mon-pupitre', '/commissions', '/activites', '/secretariat/presences', '/courriers', '/documents', '/messages', '/instruments', '/instruments/articles', '/instruments/mouvements', '/instruments/maintenance', '/instruments/ensembles', '/transactions', '/cotisations', '/dons', '/budgets', '/comptabilite/synthese', '/comptabilite/comptes', '/comptabilite/avances', '/comptabilite/presences', '/comptabilite/choristes', '/parametres/chorale', '/parametres/contributions', '/parametres/annees', '/parametres/emails', '/utilisateurs', '/administration/journal', '/rapports', '/profile', '/mon-carnet'];
const audits = new Set(['/dashboard', '/membres', '/activites', '/transactions', '/cotisations', '/instruments', '/rapports', '/parametres/chorale']);
try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (['http:', 'https:'].includes(url.protocol) && url.origin !== new URL(origin).origin) { remote.push(url.hostname); return route.abort(); }
        return route.continue();
    });
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    async function capture(url, name, audit = false) {
        const response = await page.goto(origin + url, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200, url + ': failed page');
        await page.evaluate(() => document.fonts.ready);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
        if (overflow) await page.screenshot({ path: `${directory}/${name}-overflow.png`, fullPage: true });
        assert.equal(overflow, false, url + ': page overflow');
        if (audit) {
            const report = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
            if (report.violations.length) failures.push({ url, name, violations: report.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => ({ target: n.target, summary: n.failureSummary })) })) });
        }
        await page.screenshot({ path: `${directory}/${name}.png`, fullPage: true });
        results.push({ url, name, status: response.status() });
        await writeFile(`${directory}/audit.json`, JSON.stringify({ results, failures, errors, remote }, null, 2));
    }
    await capture('/login', 'login-desktop', true);
    assert.ok((await page.locator('body').evaluate(e => getComputedStyle(e).fontFamily)).includes('Source Sans 3'));
    await page.locator('#login').fill(credentials.administrator);
    await page.locator('#password').fill(credentials.password);
    await Promise.all([page.waitForURL('**/c/**'), page.getByRole('button', { name: 'Se connecter', exact: true }).click()]);
    const selected = process.env.DESIGN_ROUTES ? pages.filter(url => process.env.DESIGN_ROUTES.split(',').includes(url)) : pages;
    for (const [index, url] of selected.entries()) {
        const name = url.slice(1).replaceAll('/', '-');
        await page.setViewportSize({ width: 1440, height: 1000 });
        await capture(url, name + '-desktop');
        await page.setViewportSize({ width: 390, height: 844 });
        await capture(url, name + '-mobile', audits.has(url));
        if (index % 10 === 9) console.log(`${index + 1} module routes verified on desktop and mobile`);
    }
    await page.goto(origin + '/membres');
    await page.getByRole('button', { name: 'Nouvel intervenant extérieur', exact: true }).click();
    await page.getByRole('heading', { name: 'Nouvel intervenant extérieur', exact: true }).waitFor();
    await page.locator('#members-form-status').waitFor({ state: 'visible' });
    await page.screenshot({ path: `${directory}/external-member-form-mobile.png` });
    assert.equal(await page.locator('#members-form-status').inputValue(), 'externe');
    await page.getByRole('button', { name: 'Fermer la fenêtre', exact: true }).click();
    await page.goto(origin + '/cotisations');
    const edit = page.getByRole('button', { name: 'Modifier', exact: true }).first();
    await edit.click();
    await page.getByRole('heading', { name: 'Modifier la cotisation', exact: true }).waitFor();
    await page.locator('#contributions-form-due_amount').waitFor({ state: 'visible' });
    await page.screenshot({ path: `${directory}/contribution-form-mobile.png` });
    await page.getByRole('button', { name: 'Fermer la fenêtre', exact: true }).click();
    await capture('/plateforme/connexion', 'platform-login-mobile', true);
    const bits = [...credentials.platform.secret].map(c => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(c).toString(2).padStart(5, '0')).join('');
    const key = Buffer.from(bits.match(/.{8}/g).map(byte => parseInt(byte, 2)));
    const counter = Buffer.alloc(8); counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const hash = createHmac('sha1', key).update(counter).digest(); const offset = hash[19] & 15;
    const code = ((hash.readUInt32BE(offset) & 0x7fffffff) % 1000000).toString().padStart(6, '0');
    await page.locator('#email').fill(credentials.platform.email);
    await page.locator('#password').fill(credentials.password);
    await page.locator('#code').fill(code);
    await Promise.all([page.waitForURL('**/plateforme'), page.locator('button[type="submit"]').click()]);
    for (const url of ['/plateforme', '/plateforme/chorales', '/plateforme/supervision', '/plateforme/journal']) {
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 1000 });
            await capture(url, url.slice(1).replaceAll('/', '-') + '-' + width, width === 390);
        }
    }
    assert.deepEqual(errors, [], 'JavaScript errors');
    assert.deepEqual(remote, [], 'Unexpected external requests');
    await writeFile(`${directory}/audit.json`, JSON.stringify({ results, failures, errors, remote }, null, 2));
    assert.deepEqual(failures, [], 'Accessibility regressions: see design/audit.json');
    console.log(`${results.length} page layouts, two mobile forms, local fonts and platform MFA verified.`);
} finally { await browser.close(); }
