import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
const origin = process.env.ACCESSIBILITY_BASE_URL || 'http://127.0.0.1:8091';
assert.ok(
    ['127.0.0.1', 'localhost', '[::1]'].includes(new URL(origin).hostname),
    'Use an isolated local fixture, never production.',
);
assert.ok(
    process.env.ACCESSIBILITY_CREDENTIALS_FILE,
    'Provide disposable local fixture credentials.',
);
const credentials = JSON.parse(
    await readFile(process.env.ACCESSIBILITY_CREDENTIALS_FILE, 'utf8'),
);
const artifacts =
    process.env.ACCESSIBILITY_ARTIFACTS || 'test-results/material';
await mkdir(artifacts, { recursive: true });
const browser = await chromium.launch({
    executablePath:
        process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
});
const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    reducedMotion: 'reduce',
});
await context.addInitScript(() => {
    window.__spoken = [];
    window.SpeechSynthesisUtterance = class {
        constructor(text) {
            this.text = text;
        }
    };
    Object.defineProperty(window, 'speechSynthesis', {
        value: {
            getVoices: () => [{ lang: 'fr-FR' }],
            speak: (u) => window.__spoken.push(u.text),
            cancel: () => {},
        },
    });
});
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
const audit = async (name, target = page) => {
    await target.waitForLoadState('networkidle');
    await target.locator('#nprogress').waitFor({ state: 'detached' });
    const result = await new AxeBuilder({ page: target })
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
    assert.equal(violations.length, 0, name + ' axe violations');
    console.log(name + ': zero axe violations');
};
try {
    await page.goto(origin + '/login');
    await page
        .getByLabel('Identifiant', { exact: true })
        .fill(credentials.administrator);
    await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(credentials.password);
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
    await page.waitForURL('**/dashboard');
    await page.goto(origin + '/instruments');
    await page
        .getByRole('button', { name: 'Ajouter du matériel', exact: true })
        .click();
    const dialog = page.locator('[role="dialog"][aria-modal="true"]');
    await dialog.waitFor();
    const name = 'Microphone guidé ' + Date.now();
    await dialog.getByLabel('Nom du matériel', { exact: true }).fill(name);
    await dialog
        .getByLabel('Catégorie', { exact: true })
        .selectOption('sonorisation');
    await dialog
        .getByLabel('Nombre d’objets identiques', { exact: true })
        .fill('2');
    await dialog
        .getByRole('button', { name: /Écouter : Donnez un nom/ })
        .click();
    assert.ok(
        !(await page.evaluate(() => window.__spoken.at(-1))).includes(name),
        'Help must not read entered values.',
    );
    await dialog
        .getByRole('button', { name: 'Arrêter la lecture', exact: true })
        .first()
        .click();
    await audit('material-form');
    await dialog
        .getByRole('button', { name: 'Enregistrer', exact: true })
        .click();
    await dialog.waitFor({ state: 'detached' });
    await page
        .getByRole('link', { name: 'Préparer les étiquettes', exact: true })
        .click();
    await page.waitForURL('**/instruments/articles');
    const filtered = page.waitForResponse(
        (response) =>
            response.url().includes('/update') &&
            response.request().method() === 'POST',
    );
    await page
        .getByLabel('Quel matériel ?', { exact: true })
        .selectOption({ label: name });
    await filtered;
    await page
        .getByRole('row')
        .filter({ hasText: name + ' n° 2' })
        .waitFor();
    await page.getByText(name + ' n° 1', { exact: true }).waitFor();
    const row = page.getByRole('row').filter({ hasText: name + ' n° 1' });
    const code = await row.locator('td').nth(1).innerText();
    await row.getByRole('checkbox').check();
    await page
        .getByRole('button', {
            name: 'Voir 1 étiquette(s) avant impression',
            exact: true,
        })
        .waitFor({ state: 'visible' });
    await audit('material-labels');
    const popupPromise = context.waitForEvent('page');
    await page
        .getByRole('button', {
            name: 'Voir 1 étiquette(s) avant impression',
            exact: true,
        })
        .click();
    const preview = await popupPromise;
    await preview.waitForLoadState();
    assert.equal(await preview.locator('.code').innerText(), code);
    assert.equal(await preview.locator('.ordinal').innerText(), 'n° 1');
    assert.ok(
        await preview
            .locator('.ordinal')
            .evaluate(
                (el) =>
                    el.getBoundingClientRect().right <=
                    el.parentElement.getBoundingClientRect().right,
            ),
        'Object number must remain inside the printed label',
    );
    await audit('label-preview', preview);
    await preview.close();
    await page
        .getByRole('link', { name: 'Suivre les prêts et retours', exact: true })
        .click();
    await page.waitForURL('**/instruments/mouvements');
    await page
        .getByRole('button', { name: 'Enregistrer un mouvement', exact: true })
        .click();
    await dialog.waitFor();
    await dialog
        .getByLabel('Instrument', { exact: true })
        .selectOption({ label: name });
    await dialog
        .getByLabel('Que fait-on avec le matériel ?', { exact: true })
        .selectOption('pret');
    await dialog.getByLabel('Quantité déplacée', { exact: true }).fill('1');
    await dialog
        .getByLabel('Où va le matériel ?', { exact: true })
        .fill('Salle de validation');
    await audit('material-loan-form');
    await dialog
        .getByRole('button', { name: 'Enregistrer', exact: true })
        .click();
    await dialog.waitFor({ state: 'detached' });
    const loan = page.getByRole('row').filter({ hasText: name });
    page.once('dialog', (d) => d.accept());
    await loan
        .getByRole('button', { name: 'Matériel rendu', exact: true })
        .click();
    await loan.getByText(/Rendu le/).waitFor();
    await audit('material-loan-return');
    await page.setViewportSize({ width: 320, height: 844 });
    await page
        .getByRole('button', { name: 'Grand texte', exact: true })
        .click();

    assert.equal(
        await page.evaluate(
            () => document.documentElement.scrollWidth > innerWidth,
        ),
        false,
        'Page overflow at 320px',
    );
    await audit('material-mobile-large-text');
    await page
        .getByRole('link', { name: 'Préparer les étiquettes', exact: true })
        .click();
    await page.waitForURL('**/instruments/articles');
    assert.equal(
        await page.evaluate(
            () => document.documentElement.scrollWidth > innerWidth,
        ),
        false,
        'Labels overflow at 320px',
    );
    await audit('labels-mobile-large-text');
    await page
        .getByRole('link', { name: 'Voir le matériel', exact: true })
        .click();
    await page.waitForURL('**/instruments');
    assert.equal(
        await page.evaluate(
            () => document.documentElement.scrollWidth > innerWidth,
        ),
        false,
        'Inventory overflow at 320px',
    );
    await audit('inventory-mobile-large-text');

    await page.evaluate(() => scrollTo(0, 0));
    await page.screenshot({
        path: artifacts + '/material-mobile.png',
        fullPage: true,
    });
    assert.equal(errors.length, 0, JSON.stringify(errors));
    console.log(
        'Material creation, private audio guidance, stable label code, loan and return passed.',
    );
} finally {
    await browser.close();
}
