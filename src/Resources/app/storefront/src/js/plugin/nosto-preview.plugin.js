export default class NostoPreviewPlugin extends window.PluginBaseClass {

    init() {
        this.syncPreviewStatus();
        this.syncWithCookies();
        this.listenToStorageChanges();
    }

    // Send preview status to server for session sync
    syncPreviewStatus() {
        const isNostoPreview = this.getPreviewStatus();
        const apiRoute = window.router?.['frontend.nosto.preview.toggle'];
        
        if (!apiRoute) {
            console.error('[Nosto] Route not found: frontend.nosto.preview.toggle');
            return;
        }

        const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (window.csrf?.token) {
            headers['X-CSRF-Token'] = window.csrf.token;
        }

        fetch(apiRoute, {
            method: 'POST',
            headers,
            body: JSON.stringify({ preview: isNostoPreview }),
        }).catch(err => {
            console.warn('[Nosto] Failed to send preview status:', err);
        });
    }

    // Listen for storage changes from other tabs
    listenToStorageChanges() {
        window.addEventListener('storage', (event) => {
            if (event.key === 'nosto:preview') {
                const newValue = event.newValue === 'true';
                this.setCookie('nosto_preview', newValue ? '1' : '0', 1);
                this.syncPreviewStatus();
            }
        });
    }

    // Get preview status from localStorage
    getPreviewStatus() {
        return localStorage.getItem('nosto:preview') === 'true';
    }

    // Sync localStorage with cookies and intercept localStorage changes
    syncWithCookies() {
        // Initial sync
        const localStorageValue = this.getPreviewStatus();
        this.setCookie('nosto_preview', localStorageValue ? '1' : '0', 1);

        // Intercept localStorage changes to auto-sync cookies
        const originalSetItem = localStorage.setItem;
        localStorage.setItem = (key, value) => {
            originalSetItem.call(localStorage, key, value);
            if (key === 'nosto:preview') {
                this.setCookie('nosto_preview', value === 'true' ? '1' : '0', 1);
            }
        };

        const originalRemoveItem = localStorage.removeItem;
        localStorage.removeItem = (key) => {
            originalRemoveItem.call(localStorage, key);
            if (key === 'nosto:preview') {
                this.setCookie('nosto_preview', '0', 1);
            }
        };
    }

    // Set cookie helper
    setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
    }
}
