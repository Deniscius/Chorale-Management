import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const artifacts = path.resolve('test-results/activity-reports');
await mkdir(artifacts, { recursive: true });
execFileSync(process.env.PHP_BINARY || 'php', ['tests/browser/prepare-activity-reports.php', artifacts], { stdio: 'inherit' });
const browser = await chromium.launch({
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
    args: ['--no-sandbox'],
});
const results = [];
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    // Self-contained test documents must never fetch remote assets or production data.
    await page.route('http**://**', (route) => route.abort());
    for (const name of ['activity-landscape', 'activity-portrait', 'attendance', 'rehearsal', 'members', 'commission', 'period', 'sample']) {
        await page.emulateMedia({ media: 'screen' });
        await page.setViewportSize({ width: 1440, height: 1100 });
        await page.setContent(await readFile(path.join(artifacts, name + '.html'), 'utf8'));
        await page.locator('.document-letterhead').waitFor();
        await page.waitForFunction(() => [...document.images].every((image) => image.complete && image.naturalWidth > 0));
        const geometry = await page.locator('.document-letterhead').evaluate((image) => {
            const rect = image.getBoundingClientRect();
            return { natural: image.naturalWidth / image.naturalHeight, displayed: rect.width / rect.height, width: rect.width, height: rect.height };
        });
        assert.ok(Math.abs(geometry.natural - geometry.displayed) < 0.01, name + ': distorted header');
        assert.ok(Math.abs(geometry.width - 210 * 96 / 25.4) < 2, name + ': wrong physical header width');
        if (name === 'activity-landscape') await page.screenshot({ path: path.join(artifacts, 'activity-desktop.png') });
        await page.emulateMedia({ media: 'print' });
        await page.pdf({ path: path.join(artifacts, name + '.pdf'), preferCSSPageSize: true, printBackground: true });
        if (name === 'activity-landscape') {
            const printed = execFileSync('pdftotext', [path.join(artifacts, name + '.pdf'), '-'], { encoding: 'utf8' });
            assert.ok(printed.includes('Choriste Démo 45'), 'Last attendance row missing from PDF');
            assert.ok(printed.includes('Point financier'), 'Accounting section missing from PDF');
        }
        await page.emulateMedia({ media: 'screen' });
        await page.setViewportSize({ width: 390, height: 844 });
        const mobile = await page.evaluate(() => ({ width: innerWidth, scrollWidth: document.documentElement.scrollWidth }));
        assert.ok(mobile.scrollWidth <= mobile.width + 1, name + ': document overflows the mobile viewport');
        const mobileRatio = await page.locator('.document-letterhead').evaluate((image) => {
            const rect = image.getBoundingClientRect();
            return rect.width / rect.height;
        });
        assert.ok(Math.abs(geometry.natural - mobileRatio) < 0.01, name + ': distorted mobile header');
        if (name === 'activity-landscape') await page.screenshot({ path: path.join(artifacts, 'activity-mobile.png') });
        results.push({ name, ...geometry, mobile });
    }
    assert.deepEqual(errors, []);
    await writeFile(path.join(artifacts, 'geometry.json'), JSON.stringify(results, null, 2));
    console.log('Eight report layouts: proportional headers, PDFs, mobile sizing and no JavaScript errors verified.');
} finally {
    await browser.close();
}
