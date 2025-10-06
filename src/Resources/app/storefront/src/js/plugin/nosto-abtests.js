import CookieStorage from 'src/helper/storage/cookie-storage.helper';

export default class NostoAbTests extends window.PluginBaseClass {
    init() {
        this.lastKnownValue = this.getNostoAbTests();
        this.lastKnownCookie = this.getNostoCookie();
        this.listenToLocalStorageChanges();
        this.overrideLocalStorage();
        this.startPolling();
        this.startCookiePolling();
        this.listenToPageEvents();

        // Set initial cookie if localStorage data already exists
        if (this.lastKnownValue) {
            this.setNostoCookie(this.lastKnownValue);
        }

        // Set initial localStorage if cookie data already exists
        if (this.lastKnownCookie) {
            this.updateLocalStorageFromCookie();
        }
    }

    getNostoAbTests() {
        try {
            const value = localStorage.getItem('nosto:abTests');
            return value ? JSON.parse(value) : null;
        } catch (e) {
            console.error('Error parsing nosto:abTests from localStorage:', e);
            return null;
        }
    }

    getNostoCookie() {
        try {
            const cookieValue = CookieStorage.getItem('nosto_ab_tests');
            return cookieValue ? JSON.parse(decodeURIComponent(cookieValue)) : null;
        } catch (e) {
            console.error('Error parsing nosto_ab_tests cookie:', e);
            return null;
        }
    }

    // Update localStorage from cookie (when PHP changes the cookie)
    updateLocalStorageFromCookie() {
        const cookieData = this.getNostoCookie();
        const currentLocalStorage = this.getNostoAbTests();

        // Only update if cookie data is different from localStorage
        if (cookieData && JSON.stringify(cookieData) !== JSON.stringify(currentLocalStorage)) {
            try {
                // Temporarily store the original setItem to avoid triggering our override
                const originalSetItem = localStorage.setItem;
                localStorage.setItem = originalSetItem;

                localStorage.setItem('nosto:abTests', JSON.stringify(cookieData));
                this.lastKnownValue = cookieData;

                // Restore our override
                this.overrideLocalStorage();
            } catch (e) {
                console.error('Error updating localStorage from cookie:', e);
            }
        }
    }

    // Listen for changes from other tabs/windows
    listenToLocalStorageChanges() {
        window.addEventListener('storage', (e) => {
            if (e.key === 'nosto:abTests') {
                const newValue = e.newValue ? JSON.parse(e.newValue) : null;
                if (newValue) {
                    this.setNostoCookie(newValue);
                }
                this.lastKnownValue = newValue;
            }
        });
    }

    // Override localStorage.setItem to catch same-tab changes
    overrideLocalStorage() {
        const originalSetItem = localStorage.setItem;
        const self = this;

        localStorage.setItem = function (key, value) {
            // Call original method first
            originalSetItem.apply(this, arguments);

            // Check if it's our target key
            if (key === 'nosto:abTests') {
                try {
                    const parsedValue = JSON.parse(value);
                    self.setNostoCookie(parsedValue);
                    self.lastKnownValue = parsedValue;
                } catch (e) {
                    console.error('Error parsing nosto:abTests value:', e);
                }
            }
        };
    }

    // Polling fallback to catch any missed localStorage changes
    startPolling() {
        this.pollInterval = setInterval(() => {
            const currentValue = this.getNostoAbTests();

            // Compare with last known value
            if (JSON.stringify(currentValue) !== JSON.stringify(this.lastKnownValue)) {
                if (currentValue) {
                    this.setNostoCookie(currentValue);
                }
                this.lastKnownValue = currentValue;
            }
        }, 500); // Check every 500ms
    }

    // Monitor cookie changes (for PHP updates)
    startCookiePolling() {
        this.cookiePollInterval = setInterval(() => {
            const currentCookie = this.getNostoCookie();

            // Compare with last known cookie value
            if (JSON.stringify(currentCookie) !== JSON.stringify(this.lastKnownCookie)) {
                this.updateLocalStorageFromCookie();
                this.lastKnownCookie = currentCookie;
            }
        }, 1000); // Check every 1000ms
    }

    // Listen for page events to sync immediately when user returns
    listenToPageEvents() {
        // When user returns to tab after server interaction
        window.addEventListener('focus', () => {
            this.updateLocalStorageFromCookie();
        });

        // When page becomes visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.updateLocalStorageFromCookie();
            }
        });
    }

    setNostoCookie(data) {
        if (CookieStorage.getItem('nosto_ab_tests')) {
            const cookie = CookieStorage.getItem('nosto_ab_tests');
            if (encodeURIComponent(JSON.stringify(data)) === cookie) {
                return;
            }
        }
        CookieStorage.setItem(
            'nosto_ab_tests',
            encodeURIComponent(JSON.stringify(data)),
            300
        );
        this.lastKnownCookie = data; // Update our tracked cookie value
    }

    // Cleanup method (call this when plugin is destroyed)
    destroy() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        if (this.cookiePollInterval) {
            clearInterval(this.cookiePollInterval);
        }

        // Remove event listeners
        window.removeEventListener('focus', this.updateLocalStorageFromCookie);
        document.removeEventListener('visibilitychange', this.updateLocalStorageFromCookie);

        // Note: We don't restore localStorage.setItem here as other instances might be using it
    }
}