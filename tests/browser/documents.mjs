import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
const origin = process.env.ACCESSIBILITY_BASE_URL || 'http://127.0.0.1:8091';
assert.ok(
    ['127.0.0.1', 'localhost', '[::1]'].includes(new URL(origin).hostname),
    'Use only an isolated local test app.',
);
assert.ok(
    process.env.ACCESSIBILITY_CREDENTIALS_FILE,
    'Disposable local credentials are required.',
);
const credentials = JSON.parse(
    await readFile(process.env.ACCESSIBILITY_CREDENTIALS_FILE, 'utf8'),
);
const artifacts = 'test-results/documents';
await mkdir(artifacts, { recursive: true });
const browser = await chromium.launch({
    executablePath:
        process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
});
const errors = [];
async function session(username) {
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        reducedMotion: 'reduce',
    });
    const page = await context.newPage();
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(origin + '/login');
    await page.getByLabel('Identifiant', { exact: true }).fill(username);
    await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(credentials.password);
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
    await page.waitForURL('**/dashboard');
    return { page, context };
}
async function audit(name, page) {
    await page.waitForLoadState('domcontentloaded');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
        .analyze();
    const violations = result.violations.map((v) => ({
        id: v.id,
        nodes: v.nodes.map((n) => ({
            target: n.target,
            summary: n.failureSummary,
        })),
    }));
    await writeFile(
        artifacts + '/' + name + '-axe.json',
        JSON.stringify(violations, null, 2),
    );
    if (violations.length) console.log(JSON.stringify(violations));
    assert.equal(violations.length, 0, name + ' accessibility violations');
    console.log(name + ': zero axe violations');
}
try {
    const { page: admin } = await session(credentials.administrator);
    const { page: member } = await session(credentials.chorister);
    const title = 'Document de validation ' + Date.now();
    const url = origin + '/documents?commission=commission_instruments';
    await admin.goto(url);
    await admin.getByRole('button', { name: 'Importer un document', exact: true }).click();
    await admin.locator('#documents-form-title').fill(title);
    await admin.locator('#documents-form-file').setInputFiles({ name: 'notice.txt', mimeType: 'text/plain', buffer: Buffer.from('Document fictif de validation') });
    await admin.getByText(/notice.txt — prêt à être archivé/).waitFor();
    await audit('import', admin);
    await admin.getByRole('button', { name: 'Enregistrer', exact: true }).click();
    const row = admin.getByRole('row').filter({ hasText: title });
    await row.waitFor();
    const download = await row.getByRole('link', { name: `Télécharger ${title}`, exact: true }).getAttribute('href');
    await row.getByRole('button', { name: `Aperçu de ${title}`, exact: true }).click();
    const previewDialog = admin.getByRole('dialog').filter({ hasText: title });
    const previewFrame = previewDialog.locator(`iframe[title="Aperçu de ${title}"]`);
    await previewFrame.waitFor();
    const preview = await previewFrame.getAttribute('src');
    assert.equal(await previewFrame.contentFrame().locator('body').innerText(), 'Document fictif de validation');
    const previewResponse = await admin.request.get(preview);
    assert.equal(previewResponse.status(), 200);
    assert.match(previewResponse.headers()['content-disposition'], /^inline;/);
    assert.match(previewResponse.headers()['cache-control'], /no-store/);
    await audit('document-preview', admin);
    await previewDialog.getByRole('button', { name: 'Fermer la fenêtre', exact: true }).click();
    const downloadResponse = await admin.request.get(download);
    assert.match(downloadResponse.headers()['content-disposition'], /^attachment;/);
    await member.goto(url);
    assert.equal(await member.getByText(title, { exact: true }).count(), 0);
    assert.equal((await member.request.get(download)).status(), 403);
    assert.equal((await member.request.get(preview)).status(), 403);
    for (const close of await admin.getByRole('button', { name: 'Fermer la notification' }).all()) await close.click();
    await row.getByRole('button', { name: 'Visibilité', exact: true }).click();
    await admin.getByRole('radio', { name: /Toute la chorale/ }).check();
    await audit('visibility', admin);
    await admin.getByRole('button', { name: 'Enregistrer la visibilité', exact: true }).click();
    await row.getByText('Toute la chorale (connectée)', { exact: true }).waitFor();
    await member.reload();
    await member.getByText(title, { exact: true }).waitFor();
    const response = await member.request.get(download);
    assert.equal(response.status(), 200);
    assert.match(response.headers()['cache-control'], /no-store/);
    assert.equal(await response.text(), 'Document fictif de validation');
    assert.equal((await member.request.get(preview)).status(), 200);
    await audit('shared-list', member);
    for (const close of await admin.getByRole('button', { name: 'Fermer la notification' }).all()) await close.click();
    await row.getByRole('button', { name: 'Visibilité', exact: true }).click();
    await admin.getByRole('radio', { name: /Responsables habilités/ }).check();
    await admin.getByRole('button', { name: 'Enregistrer la visibilité', exact: true }).click();
    await row.getByText('Responsables habilités', { exact: true }).waitFor();
    await member.reload();
    assert.equal(await member.getByText(title, { exact: true }).count(), 0);
    assert.equal((await member.request.get(download)).status(), 403);
    await member.setViewportSize({ width: 320, height: 800 });
    assert.ok(await member.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
    await audit('restricted-list-mobile', member);
    assert.deepEqual(errors, []);
    console.log('Documents: import, aperçu intégré, téléchargement, partage et révocation vérifiés; 5 états sans violation axe.');
} finally {
    await browser.close();
}
