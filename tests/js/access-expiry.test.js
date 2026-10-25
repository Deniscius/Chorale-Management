import test from 'node:test';
import assert from 'node:assert/strict';
import { trackAccessExpiry, accessClock } from '../../resources/js/access-expiry.js';

function clock(remainingMs) {
    let current = 1000, redirects = 0, latest;
    const tracker = trackAccessExpiry({ remainingMs, now: () => current, navigate: () => redirects++, schedule: (callback, delay) => { latest = { callback, delay }; return 1; }, cancel: () => {} });
    return { tracker, advance: ms => { current += ms; }, redirects: () => redirects, timer: () => latest };
}

test('an idle page reaches billing at the boundary, once only', () => {
    const c = clock(2000);
    assert.equal(c.redirects(), 0);
    c.advance(1999); c.tracker.check();
    assert.equal(c.redirects(), 0);
    c.advance(1); c.tracker.check(); c.tracker.check();
    assert.equal(c.redirects(), 1);
});

test('a resumed or restored page checks its elapsed deadline immediately', () => {
    const c = clock(5000);
    c.advance(20000); c.tracker.check();
    assert.equal(c.redirects(), 1);
});

test('long licences do not overflow browser timers and replaced trackers are stopped', () => {
    const c = clock(365 * 86400000);
    assert.equal(c.timer().delay, 2147483000);
    c.tracker.stop(); c.advance(366 * 86400000); c.timer().callback();
    assert.equal(c.redirects(), 0);
});

test('restoring an old navigation snapshot does not restart the original trial duration', () => {
    let time = 100000;
    const remaining = accessClock(() => time);
    assert.equal(remaining(5000, 1000), 4000);
    time += 5000;
    assert.equal(remaining(5000, 1000), -1000);
});
