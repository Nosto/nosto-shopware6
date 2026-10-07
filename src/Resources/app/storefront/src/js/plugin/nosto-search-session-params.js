import CookieStorage from 'src/helper/storage/cookie-storage.helper';

// The search session params used to be copied into this cookie. On shops with many cookies it made
// requests fail with "400 Request Header Or Cookie Too Large" (NS-14701). The server now fetches them
// from Nosto itself, so this plugin only removes the cookie left in shoppers' browsers.
const LEGACY_SESSION_PARAMS_COOKIE = 'nosto-search-session-params';

export default class NostoSearchSessionParams extends window.PluginBaseClass {
    init() {
        if (CookieStorage.getItem(LEGACY_SESSION_PARAMS_COOKIE)) {
            CookieStorage.removeItem(LEGACY_SESSION_PARAMS_COOKIE);
        }
    }
}
