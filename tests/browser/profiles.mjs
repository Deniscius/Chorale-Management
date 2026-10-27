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
const artifacts = 'test-results/profiles';
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
    await page.waitForLoadState('networkidle');
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
    const { page: member } = await session(credentials.chorister);
    await member.goto(origin + '/profile');
    await member.locator('#first_name').fill('Membre');
    await member.locator('#last_name').fill('Validation');
    await member
        .getByLabel('Date de naissance', { exact: true })
        .fill('1992-04-12');
    await member.getByLabel('Téléphone', { exact: true }).fill('+22890000000');
    await member
        .getByLabel('Profession ou activité', { exact: true })
        .fill('Activité de test');
    await member
        .getByRole('button', {
            name: 'Enregistrer mes informations',
            exact: true,
        })
        .click();
    await member
        .getByRole('status')
        .filter({ hasText: 'Vos informations ont été enregistrées.' })
        .waitFor();
    assert.equal(
        await member
            .getByLabel('Date de naissance', { exact: true })
            .inputValue(),
        '1992-04-12',
    );
    await audit('personal-profile', member);
    await member.setViewportSize({ width: 320, height: 844 });
    await member
        .getByRole('button', { name: 'Grand texte', exact: true })
        .click();
    assert.equal(
        await member.evaluate(
            () => document.documentElement.scrollWidth > innerWidth,
        ),
        false,
        'Profile overflow',
    );
    await audit('personal-profile-mobile', member);
    const { page: admin } = await session(credentials.administrator);
    await admin.goto(origin + '/utilisateurs');
    const row = admin
        .getByRole('row')
        .filter({ hasText: credentials.chorister });
    await row
        .getByRole('link', { name: 'Commissions et accès', exact: true })
        .click();
    await admin.waitForURL('**/commissions');
    const assignmentUrl = admin.url();
    const instrument = admin
        .locator('fieldset')
        .filter({
            has: admin.locator('legend', {
                hasText: 'Commission des instruments',
            }),
        });
    const finance = admin
        .locator('fieldset')
        .filter({ has: admin.locator('legend', { hasText: 'Trésorerie' }) });
    await instrument
        .getByLabel('Responsable — gestion', { exact: true })
        .check();
    await finance.getByLabel('Membre — consultation', { exact: true }).check();
    await audit('commission-checkboxes', admin);
    await admin
        .getByRole('button', {
            name: 'Enregistrer les affectations',
            exact: true,
        })
        .click();
    await admin
        .getByText(
            'Affectations enregistrées. Les accès suivent les commissions actives et le rôle général.',
            { exact: true },
        )
        .waitFor();
    assert.equal(
        (await member.request.get(assignmentUrl)).status(),
        403,
        'Member must not administer their own rights',
    );
    await member.goto(origin + '/instruments');
    await member
        .getByRole('button', { name: 'Ajouter du matériel', exact: true })
        .waitFor();
    assert.equal(
        (await member.request.get(origin + '/transactions')).status(),
        200,
        'Finance consultation must be allowed',
    );
    await instrument
        .getByLabel('Responsable — gestion', { exact: true })
        .uncheck();
    const revoked = admin.waitForResponse(
        (response) =>
            response.url().includes('/update') &&
            response.request().method() === 'POST',
    );
    await admin
        .getByRole('button', {
            name: 'Enregistrer les affectations',
            exact: true,
        })
        .click();
    await revoked;
    await admin
        .getByText(
            'Affectations enregistrées. Les accès suivent les commissions actives et le rôle général.',
            { exact: true },
        )
        .waitFor();
    await member.goto(origin + '/instruments');
    await member
        .getByRole('heading', {
            name: 'Inventaire des instruments',
            exact: true,
        })
        .waitFor();
    assert.equal(
        await member
            .getByRole('button', { name: 'Ajouter du matériel', exact: true })
            .count(),
        0,
        'Leader removal must revoke management',
    );
    await admin.setViewportSize({ width: 320, height: 844 });
    await admin
        .getByRole('button', { name: 'Grand texte', exact: true })
        .click();
    assert.equal(
        await admin.evaluate(
            () => document.documentElement.scrollWidth > innerWidth,
        ),
        false,
        'Assignments overflow',
    );
    await audit('commission-checkboxes-mobile', admin);
    await admin.screenshot({
        path: artifacts + '/commission-checkboxes-mobile.png',
        fullPage: true,
    });
    assert.equal(errors.length, 0, JSON.stringify(errors));
    console.log(
        'Profile changes, commission checkboxes, consultation, write access and revocation passed.',
    );
} finally {
    await browser.close();
}
