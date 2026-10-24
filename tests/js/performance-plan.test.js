import test from 'node:test';
import assert from 'node:assert/strict';
import { stressStages, stressPhaseAt } from '../performance/stress-plan.js';
test('stress plan holds every target and includes recovery', () => {
    assert.deepEqual(stressStages.filter(x => x.hold).map(x => x.target), [5, 10, 20, 30, 50, 5]);
    assert.equal(stressStages.reduce((n, x) => n + x.seconds, 0), 355);
    assert.equal(Math.max(...stressStages.map(x => x.target)), 50);
});
test('phase boundaries distinguish ramps from sustained plateaus', () => {
    assert.equal(stressPhaseAt(0), 'montee_5');
    assert.equal(stressPhaseAt(14999), 'montee_5');
    assert.equal(stressPhaseAt(15000), 'palier_5');
    assert.equal(stressPhaseAt(60000), 'palier_10');
    assert.equal(stressPhaseAt(235000), 'montee_50');
    assert.equal(stressPhaseAt(300000), 'descente_5');
    assert.equal(stressPhaseAt(315000), 'recuperation_5');
    assert.equal(stressPhaseAt(355000), 'termine');
});
