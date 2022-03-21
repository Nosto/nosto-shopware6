import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';

export default class NostoConfiguration extends Plugin {
    static options = {
        nostoAfterFirstInteraction: 'afterFirstInteraction'
    };

    init() {
        this.storage = Storage;

        if (this.options.initializeAfter) {
            if (this.storage.getItem(this.options.nostoAfterFirstInteraction) !== null) {
                return this._initNosto();
            } else {
                return this.registerEvents();
            }
        }

        this._initNosto()
    }

    registerEvents() {
        window.addEventListener('scroll', this._prepareForInitialization.bind(this), {once: true});
    }

    _prepareForInitialization() {
        this.storage.setItem(this.options.nostoAfterFirstInteraction, '')
        this._initNosto();
    }

    _initNosto() {

        let name = "nostojs";
        window[name] = window[name] || function (cb) {
            (window[name].q = window[name].q || []).push(cb);
        };

        if (this.options.accountID) {
            let script = document.createElement('script');
            script.type = 'text/javascript';
            script.setAttribute('async', true);
            script.src = '//connect.nosto.com/include/' + this.options.accountID;

            document.body.appendChild(script);
        }
    }
}