import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
const origin = process.env.ACCESSIBILITY_BASE_URL || 'http://127.0.0.1:8091';
const target = new URL(origin);
assert.ok(
    ['127.0.0.1', 'localhost', '[::1]'].includes(target.hostname),
    'Use an isolated local app, never production.',
);
assert.ok(
    process.env.ACCESSIBILITY_CREDENTIALS_FILE,
    'Provide a private fixture file via ACCESSIBILITY_CREDENTIALS_FILE.',
);
const credentials = JSON.parse(
    await readFile(process.env.ACCESSIBILITY_CREDENTIALS_FILE, 'utf8'),
);
assert.ok(
    credentials.password && credentials.chorister && credentials.administrator,
    'Disposable fixture credentials are required.',
);
const artifacts =
    process.env.ACCESSIBILITY_ARTIFACTS || 'test-results/accessibility';
await mkdir(artifacts, { recursive: true });
const browser = await chromium.launch({
    executablePath:
        process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
    headless: true,
});
const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    reducedMotion: 'reduce',
});
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
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
            speak: (u) => {
                window.__spoken.push({ text: u.text, rate: u.rate });
            },
            cancel: () => {},
        },
    });
});
const overflow = async () =>
    assert.equal(
        await page.evaluate(
            () => document.documentElement.scrollWidth > innerWidth,
        ),
        false,
        'horizontal overflow',
    );
const audit = async (name) => {
    await page.waitForLoadState('networkidle');
    // Audit the settled page, after Livewire's transient navigation indicator.
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    const r = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
        .analyze();
    const violations = r.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        description: v.description,
        nodes: v.nodes.map((n) => ({
            target: n.target,
            summary: n.failureSummary,
        })),
    }));
    await writeFile(
        artifacts + '/' + name + '-axe.json',
        JSON.stringify(violations, null, 2),
    );
    console.log(name + ' axe violations: ' + violations.length);
    if (violations.length)
        console.log(JSON.stringify(violations).slice(0, 6500));
    assert.equal(violations.length, 0, name + ' accessibility violations');
    return violations;
};
try {
    await page.goto(origin + '/login', { waitUntil: 'networkidle' });
    await page
        .getByRole('button', { name: 'Grand texte', exact: true })
        .waitFor();
    await page
        .getByRole('button', { name: 'Grand texte', exact: true })
        .click();
    await overflow();
    await page
        .getByRole('button', { name: /Écouter :/ })
        .first()
        .click();
    assert.equal(await page.evaluate(() => window.__spoken.length), 1);
    await page.getByRole('button', { name: 'Voix plus lente' }).click();
    assert.equal(await page.evaluate(() => window.__spoken.at(-1).rate), 0.65);
    await page.getByRole('button', { name: 'Arrêter la lecture' }).click();
    const password = credentials.password;
    await page
        .getByLabel('Identifiant', { exact: true })
        .fill(credentials.chorister);
    await page.getByLabel('Mot de passe', { exact: true }).fill(password);
    await page
        .getByRole('button', { name: 'Afficher le mot de passe' })
        .click();
    assert.equal(await page.locator('#password').getAttribute('type'), 'text');
    await page.getByRole('button', { name: 'Masquer le mot de passe' }).click();
    assert.equal(
        await page.locator('#password').getAttribute('type'),
        'password',
    );
    await audit('login');
    await page
        .getByRole('button', { name: 'Se connecter', exact: true })
        .click();
    await page.waitForURL('**/dashboard');
    await page
        .getByRole('heading', { name: 'Que souhaitez-vous faire ?' })
        .waitFor();
    await overflow();
    assert.equal(
        await page.locator('html').getAttribute('class'),
        'comfortable-text',
    );
    assert.equal(
        await page.getByText('Recettes et dépenses', { exact: true }).count(),
        0,
    );
    await audit('dashboard');
    await page.screenshot({
        path: artifacts + '/dashboard-mobile.png',
        fullPage: true,
    });
    await page
        .getByRole('link', { name: 'Retrouver un chant', exact: true })
        .click();
    await page.waitForURL('**/oeuvres');
    await page.getByLabel('Rechercher un chant', { exact: true }).waitFor();
    await page
        .getByText('Pas à pas : Retrouver un chant', { exact: true })
        .click();
    await page
        .getByRole('button', { name: /Écouter : Cherchez le titre/ })
        .click();
    await overflow();
    await audit('works');
    await page.getByRole('link', { name: 'Revenir à l’accueil' }).click();
    await page.waitForURL('**/dashboard');
    await page
        .getByRole('link', { name: 'Voir les répétitions', exact: true })
        .click();
    await page.waitForURL('**/repetitions');
    await page
        .getByLabel('Rechercher une répétition', { exact: true })
        .waitFor();
    await overflow();
    await audit('rehearsals');
    await page
        .getByRole('button', { name: 'Grand texte', exact: true })
        .click();
    await page.reload();
    assert.equal(
        await page
            .locator('html')
            .evaluate((el) => el.classList.contains('comfortable-text')),
        false,
    );
    console.log(
        'Browser scenarios passed; JavaScript errors: ' + errors.length,
    );
    if (errors.length) console.log(JSON.stringify(errors));
    assert.equal(errors.length, 0);

    for (const [width, username] of [
        [320, credentials.chorister],
        [1440, credentials.administrator],
    ]) {
        await page.setViewportSize({ width, height: 900 });
        if (username !== credentials.chorister) {
            await context.clearCookies();
            await page.goto(origin + '/login');
            await page
                .getByLabel('Identifiant', { exact: true })
                .fill(username);
            await page
                .getByLabel('Mot de passe', { exact: true })
                .fill(credentials.password);
            await page
                .getByRole('button', { name: 'Se connecter', exact: true })
                .click();
            await page.waitForURL('**/dashboard');
        } else {
            await page
                .getByRole('link', { name: 'Revenir à l’accueil' })
                .click();
            await page.waitForURL('**/dashboard');
        }
        if (
            !(await page
                .locator('html')
                .evaluate((el) => el.classList.contains('comfortable-text')))
        ) {
            await page
                .getByRole('button', { name: 'Grand texte', exact: true })
                .click();
        }
        await overflow();
        if (width < 1024) {
            await page
                .getByRole('button', { name: 'Ouvrir le menu', exact: true })
                .click();
            await page
                .getByRole('dialog', { name: 'Navigation principale' })
                .waitFor();
            assert.equal(
                await page
                    .getByRole('button', { name: 'Fermer le menu' })
                    .evaluate((el) => el === document.activeElement),
                true,
            );
            await page.keyboard.press('Shift+Tab');
            assert.equal(
                await page
                    .locator('#sidebar a')
                    .last()
                    .evaluate((el) => el === document.activeElement),
                true,
            );
            await page.keyboard.press('Tab');
            assert.equal(
                await page
                    .getByRole('button', { name: 'Fermer le menu' })
                    .evaluate((el) => el === document.activeElement),
                true,
            );
            await audit('mobile-menu');
            await page.keyboard.press('Escape');
            assert.equal(
                await page
                    .getByRole('button', {
                        name: 'Ouvrir le menu',
                        exact: true,
                    })
                    .evaluate((el) => el === document.activeElement),
                true,
            );
            assert.equal(
                await page.locator('#sidebar').evaluate((el) => el.inert),
                true,
            );
        }
        await page
            .getByText('Voir les détails de la chorale', { exact: true })
            .click();
        await audit('dashboard-expanded-' + width);
        await page.evaluate(() => scrollTo(0, 0));
        await page.screenshot({
            path: artifacts + '/dashboard-' + width + '.png',
            fullPage: true,
        });
    }
    console.log(
        'Keyboard navigation, mobile reflow and role-specific scenarios passed.',
    );
    assert.equal(errors.length, 0, 'Unexpected browser JavaScript errors');
    const unsupportedContext = await browser.newContext({
        viewport: { width: 320, height: 844 },
    });
    await unsupportedContext.addInitScript(() => {
        Object.defineProperty(window, 'speechSynthesis', { value: undefined });
    });
    const unsupportedPage = await unsupportedContext.newPage();
    unsupportedPage.on('pageerror', (error) => errors.push(error.message));
    await unsupportedPage.goto(origin + '/login');
    await unsupportedPage
        .getByText('La lecture audio n’est pas disponible sur ce navigateur.', {
            exact: false,
        })
        .waitFor();
    assert.equal(
        await unsupportedPage
            .getByRole('button', { name: /Écouter :/ })
            .count(),
        0,
    );
    await unsupportedPage
        .getByRole('button', { name: 'Grand texte', exact: true })
        .click();
    assert.equal(
        await unsupportedPage
            .locator('html')
            .evaluate((el) => el.classList.contains('comfortable-text')),
        true,
    );
    await unsupportedContext.close();
    assert.equal(errors.length, 0);
    console.log(
        'Unsupported audio fallback passed. All browser scenarios passed.',
    );
} finally {
    await browser.close();
}
