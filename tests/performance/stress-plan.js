export const stressStages = [
    { name: 'montee_5', seconds: 15, target: 5 },
    { name: 'palier_5', seconds: 30, target: 5, hold: true },
    { name: 'montee_10', seconds: 15, target: 10 },
    { name: 'palier_10', seconds: 45, target: 10, hold: true },
    { name: 'montee_20', seconds: 20, target: 20 },
    { name: 'palier_20', seconds: 45, target: 20, hold: true },
    { name: 'montee_30', seconds: 20, target: 30 },
    { name: 'palier_30', seconds: 45, target: 30, hold: true },
    { name: 'montee_50', seconds: 20, target: 50 },
    { name: 'palier_50', seconds: 45, target: 50, hold: true },
    { name: 'descente_5', seconds: 15, target: 5 },
    { name: 'recuperation_5', seconds: 30, target: 5, hold: true },
    { name: 'arret', seconds: 10, target: 0 },
];
export function stressPhaseAt(milliseconds) {
    let end = 0;
    for (const stage of stressStages) {
        end += stage.seconds * 1000;
        if (milliseconds < end) return stage.name;
    }
    return 'termine';
}
