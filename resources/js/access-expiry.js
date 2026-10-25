// This improves the idle-page experience. The server remains the access authority.
export function accessClock(now = Date.now) {
    let offset;
    return (expires, server) => {
        offset ??= now() - server;
        return expires + offset - now();
    };
}

export function trackAccessExpiry({ remainingMs, navigate, now = Date.now, schedule = setTimeout, cancel = clearTimeout }) {
    let timer;
    let finished = false;
    const deadline = now() + remainingMs;
    function check() {
        if (finished) return;
        if (timer !== undefined) cancel(timer);
        const remaining = deadline - now();
        if (remaining <= 0) {
            finished = true;
            navigate();
        } else {
            timer = schedule(check, Math.min(remaining, 2147483000));
        }
    }
    check();
    return { check, stop() { finished = true; if (timer !== undefined) cancel(timer); } };
}

if (typeof document !== 'undefined') {
    let tracker;
    const remaining = accessClock();
    const initialize = () => {
        tracker?.stop();
        tracker = undefined;
        const expires = Date.parse(document.querySelector('meta[name="tenant-access-expires"]')?.content || '');
        const server = Date.parse(document.querySelector('meta[name="tenant-server-time"]')?.content || '');
        const billing = document.querySelector('meta[name="billing-url"]')?.content;
        if (!Number.isFinite(expires) || !Number.isFinite(server) || !billing) return;
        tracker = trackAccessExpiry({ remainingMs: remaining(expires, server), navigate: () => window.location.replace(billing) });
    };
    initialize();
    document.addEventListener('livewire:navigated', initialize);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tracker?.check(); });
    window.addEventListener('pageshow', () => tracker?.check());
}
