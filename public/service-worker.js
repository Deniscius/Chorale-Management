self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data?.json() || {}; } catch (_) {}
    const path = typeof data.url === 'string' && data.url.startsWith('/') ? data.url : '/dashboard';
    event.waitUntil(self.registration.showNotification(data.title || 'Nouvelle information', {
        body: data.body || 'Ouvrez votre espace pour consulter cette information.',
        icon: data.icon || '/brand-icon.svg', badge: '/brand-icon.svg',
        tag: data.tag || 'chorale-notification', renotify: true,
        data: { url: path },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/dashboard', self.location.origin).href;
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const existing = windows.find((client) => client.url.startsWith(self.location.origin));
        if (existing) { existing.navigate(target); return existing.focus(); }
        return clients.openWindow(target);
    }));
});
