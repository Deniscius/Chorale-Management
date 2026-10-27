// Only explicit help text is spoken; never scrape forms or private page content.
export function accessibilityHelp(browser = window) {
    let utterance = null;
    let generation = 0;
    return {
        available: Boolean(browser.speechSynthesis && browser.SpeechSynthesisUtterance),
        large: false,
        speaking: false,
        message: '',
        init() {
            try { this.large = browser.localStorage.getItem('chorale-large-text') === 'true'; } catch { /* Optional storage. */ }
            this.applySize();
        },
        applySize() {
            browser.document.documentElement.classList.toggle('comfortable-text', this.large);
        },
        toggleSize() {
            this.large = !this.large;
            this.applySize();
            try { browser.localStorage.setItem('chorale-large-text', String(this.large)); } catch { /* Still works without storage. */ }
        },
        stop() {
            generation++;
            if (utterance) {
                utterance.onend = null;
                utterance.onerror = null;
                browser.speechSynthesis.cancel();
                utterance = null;
            }
            this.speaking = false;
        },
        read(text) {
            this.stop();
            if (!this.available || !text) return;
            const current = generation;
            try {
                utterance = new browser.SpeechSynthesisUtterance(text);
                utterance.lang = 'fr-FR';
                utterance.rate = 0.85;
                const voice = browser.speechSynthesis.getVoices().find(v => v.lang?.startsWith('fr'));
                if (voice) utterance.voice = voice;
                utterance.onend = () => {
                    if (current === generation) { this.speaking = false; utterance = null; }
                };
                utterance.onerror = () => {
                    if (current === generation) {
                        this.speaking = false;
                        utterance = null;
                        this.message = 'La lecture audio est indisponible. Vous pouvez demander de l’aide à un proche.';
                    }
                };
                this.message = '';
                this.speaking = true;
                browser.speechSynthesis.speak(utterance);
            } catch {
                this.stop();
                this.message = 'La lecture audio est indisponible sur cet appareil.';
            }
        },
        destroy() { this.stop(); },
    };
}
