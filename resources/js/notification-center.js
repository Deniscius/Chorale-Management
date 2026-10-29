const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const request = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...options.headers },
        ...options,
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
};

const base64UrlToBytes = (value) => {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
};

export const notificationCenter = (urls) => ({
    open: false, loading: true, items: [], unread: 0, push: { available: false, subscribed: false }, registration: null,

    async init() {
        if ('serviceWorker' in navigator) {
            try { this.registration = await navigator.serviceWorker.register('/service-worker.js', { scope: '/' }); } catch (_) {}
        }
        await this.refresh(true);
        this.timer = window.setInterval(() => this.refresh(false), 60000);
        window.addEventListener('online', () => this.refresh(false));
        window.addEventListener('focus', () => this.refresh(false));
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refresh(false); });
    },

    async refresh(first = false) {
        try {
            const previous = this.unread;
            const data = await request(urls.index);
            this.items = data.items; this.unread = data.unread; this.push = data.push;
            if ((first && this.unread > 0) || (!first && this.unread > previous)) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: this.unread === 1 ? 'Vous avez une nouvelle information.' : `Vous avez ${this.unread} nouvelles informations.` } }));
            }
        } catch (_) {
            // Le prochain retour en ligne ou le prochain intervalle fera le rattrapage.
        } finally { this.loading = false; }
    },

    async read(item) {
        if (!item.read) {
            await request(urls.read.replace('__ID__', item.id), { method: 'POST', body: '{}' });
            item.read = true; this.unread = Math.max(0, this.unread - 1);
        }
        if (item.url?.startsWith('/')) window.location.assign(item.url);
    },

    async readAll() {
        await request(urls.readAll, { method: 'POST', body: '{}' });
        this.items.forEach((item) => item.read = true); this.unread = 0;
    },

    async enablePush() {
        if (!this.push.available || !this.registration || !('PushManager' in window)) return this.explainUnavailable();
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: 'Autorisez les notifications dans votre navigateur pour recevoir les rappels hors connexion.' } }));
            return;
        }
        try {
            const subscription = await this.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64UrlToBytes(this.push.publicKey) });
            const json = subscription.toJSON();
            await request(urls.subscribe, { method: 'POST', body: JSON.stringify({ ...json, contentEncoding: 'aes128gcm' }) });
            this.push.subscribed = true;
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Les rappels sur cet appareil sont activés.' } }));
        } catch (_) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'L’activation a échoué. Vérifiez les autorisations du navigateur puis réessayez.' } }));
        }
    },

    async disablePush() {
        const subscription = await this.registration?.pushManager.getSubscription();
        if (subscription) {
            await request(urls.unsubscribe, { method: 'DELETE', body: JSON.stringify({ endpoint: subscription.endpoint }) });
            await subscription.unsubscribe();
        }
        this.push.subscribed = false;
        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Les rappels sur cet appareil sont désactivés.' } }));
    },

    explainUnavailable() {
        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: 'Les notifications hors connexion seront disponibles après la configuration sécurisée du serveur.' } }));
    },
});
