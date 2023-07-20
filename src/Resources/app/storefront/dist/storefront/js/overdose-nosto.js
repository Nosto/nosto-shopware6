"use strict";(self.webpackChunk=self.webpackChunk||[]).push([["overdose-nosto"],{3880:(t,e,i)=>{var s,n,o,r=i(6285),a=i(9068),l=i(1966);class c extends r.Z{init(){window.Nosto={},Nosto.addProductToCart=(t,e,i)=>{this._addMultipleToCart([{productId:t,skuId:t,quantity:i}],e)},Nosto.addMultipleProductsToCart=(t,e)=>{this._addMultipleToCart(t,e)},Nosto.addSkuToCart=(t,e,i)=>{const s={quantity:i};this._addMultipleToCart([{...t,...s}],e)},this._nostoElementId=this.el.nextElementSibling.id?this.el.nextElementSibling.id:""}_resolveContextSlotId(t){return t&&t.closest(".nosto_element")&&t.closest(".nosto_element").getAttribute("id")?t.closest(".nosto_element").getAttribute("id"):this._nostoElementId}_addMultipleToCart(t,e){const i={lineItems:{},redirectTo:this.options.redirectTo};t.forEach((t=>{i.lineItems[t.skuId]={id:t.skuId,quantity:Number.isInteger(t.quantity)?t.quantity:1,type:"product",referencedId:t.skuId,stackable:1,removable:1},this.$emitter.publish("addRecommendationToCart",{productId:t.productId,elementId:this._resolveContextSlotId(e)})})),this._openOffCanvasCarts(this.options.action,JSON.stringify(i))}_openOffCanvasCarts(t,e){const i=a.Z.getPluginInstances("OffCanvasCart");l.Z.iterate(i,(i=>this._openOffCanvasCart(i,t,e)))}_openOffCanvasCart(t,e,i){t.openOffCanvas(e,i,(()=>{this.$emitter.publish("openOffCanvasCart")}))}}s=c,o={redirectTo:"frontend.cart.offcanvas",action:"/checkout/line-item/add"},(n=function(t){var e=function(t,e){if("object"!=typeof t||null===t)return t;var i=t[Symbol.toPrimitive];if(void 0!==i){var s=i.call(t,e||"default");if("object"!=typeof s)return s;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===e?String:Number)(t)}(t,"string");return"symbol"==typeof e?e:String(e)}(n="options"))in s?Object.defineProperty(s,n,{value:o,enumerable:!0,configurable:!0,writable:!0}):s[n]=o;var d=i(6656),h=i(3206);class u{static setCookie(t,e,i){var s="";if(i){var n=new Date;n.setTime(n.getTime()+24*i*60*60*1e3),s="; expires="+n.toUTCString()}document.cookie=t+"="+(e||"")+s+"; path=/"}static getCookie(t){for(var e=t+"=",i=document.cookie.split(";"),s=0;s<i.length;s++){for(var n=i[s];" "==n.charAt(0);)n=n.substring(1,n.length);if(0==n.indexOf(e))return n.substring(e.length,n.length)}return null}}class f extends r.Z{init(){if(u.getCookie("od-nosto-track-allow")){if(this.storage=d.Z,this.options.initializeAfter)return null!==this.storage.getItem(this.options.nostoInitializedStorageKey)?this._initNosto():this.registerEvents();this._initNosto()}}onNostoCookieConsentAllowed(){this._initNosto()}registerEvents(){window.addEventListener("scroll",this._prepareForInitialization.bind(this),{once:!0})}_prepareForInitialization(){this.storage.setItem(this.options.nostoInitializedStorageKey,""),this._initNosto()}_initNosto(){const t="nostojs";if(window[t]=window[t]||function(e){(window[t].q=window[t].q||[]).push(e)},this.options.accountID){const t=document.createElement("script");t.type="text/javascript",t.setAttribute("async",!0),t.src="//connect.nosto.com/include/"+this.options.accountID,document.body.appendChild(t),this.registerSubscribers()}}registerSubscribers(){this._cartWidgetElement=h.Z.querySelector(document,"[data-cart-widget]",!1),this._cartWidget=!1!==this._cartWidgetElement&&PluginManager.getPluginInstanceFromElement(this._cartWidgetElement,"CartWidget"),this.cartWidgetSubscriber(),this.nostoSubscriber()}cartWidgetSubscriber(){!1!==this._cartWidget&&this._cartWidget.$emitter.subscribe("fetch",(()=>{nostojs((t=>{t.resendCartTagging()}))}))}nostoSubscriber(){const t=PluginManager.getPluginInstances("NostoPlugin");l.Z.iterate(t,(t=>{t.$emitter.subscribe("addRecommendationToCart",(t=>{nostojs((e=>{e.recommendedProductAddedToCart(t.detail.productId,t.detail.elementId),this.options.reloadRecommendations&&e.loadRecommendations()}))}))}))}}!function(t,e,i){(e=function(t){var e=function(t,e){if("object"!=typeof t||null===t)return t;var i=t[Symbol.toPrimitive];if(void 0!==i){var s=i.call(t,e||"default");if("object"!=typeof s)return s;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===e?String:Number)(t)}(t,"string");return"symbol"==typeof e?e:String(e)}(e))in t?Object.defineProperty(t,e,{value:i,enumerable:!0,configurable:!0,writable:!0}):t[e]=i}(f,"options",{nostoInitializedStorageKey:"nostoInitializedStorageKey"});var p=i(3139);class g extends p.Z{_getDisabledFiltersParamsFromParams(t){const e=t.order,i=super._getDisabledFiltersParamsFromParams(t);return"od-recommendation"===e&&(i.order=e),i}}var v=i(8553);document.$emitter.subscribe(v.Du,(function(t){t.detail["od-nosto-track-allow"]&&l.Z.iterate(PluginManager.getPluginInstances("NostoConfiguration"),(t=>{t.onNostoCookieConsentAllowed()}))}));const m=window.PluginManager;m.register("NostoPlugin",c,"[data-nosto-cart-plugin]"),m.register("NostoConfiguration",f,"[data-nosto-configuration]"),m.override("Listing",g,"[data-listing]")},8553:(t,e,i)=>{i.d(e,{Du:()=>d,UK:()=>h,ZP:()=>u});var s=i(6285),n=i(7606),o=i(2615),r=i(3637),a=i(7474),l=i(8254),c=i(4690);const d="CookieConfiguration_Update",h="CookieConfiguration_CloseOffCanvas";class u extends s.Z{init(){this.lastState={active:[],inactive:[]},this._httpClient=new l.Z,this._registerEvents()}_registerEvents(){const{submitEvent:t,buttonOpenSelector:e,customLinkSelector:i,globalButtonAcceptAllSelector:s}=this.options;Array.from(document.querySelectorAll(e)).forEach((e=>{e.addEventListener(t,this.openOffCanvas.bind(this))})),Array.from(document.querySelectorAll(i)).forEach((e=>{e.addEventListener(t,this._handleCustomLink.bind(this))})),Array.from(document.querySelectorAll(s)).forEach((e=>{e.addEventListener(t,this._acceptAllCookiesFromCookieBar.bind(this))}))}_registerOffCanvasEvents(){const{submitEvent:t,buttonSubmitSelector:e,buttonAcceptAllSelector:i,wrapperToggleSelector:s}=this.options,o=this._getOffCanvas();if(o){const r=o.querySelector(e),a=o.querySelector(i),l=Array.from(o.querySelectorAll('input[type="checkbox"]')),c=Array.from(o.querySelectorAll(s));r&&r.addEventListener(t,this._handleSubmit.bind(this,n.Z)),a&&a.addEventListener(t,this._acceptAllCookiesFromOffCanvas.bind(this,n.Z)),l.forEach((e=>{e.addEventListener(t,this._handleCheckbox.bind(this))})),c.forEach((e=>{e.addEventListener(t,this._handleWrapperTrigger.bind(this))}))}}_handleCustomLink(t){t.preventDefault(),this.openOffCanvas()}_handleUpdateListener(t,e){const i=this._getUpdatedCookies(t,e);document.$emitter.publish(d,i)}_getUpdatedCookies(t,e){const{lastState:i}=this,s={};return t.forEach((t=>{i.inactive.includes(t)&&(s[t]=!0)})),e.forEach((t=>{i.active.includes(t)&&(s[t]=!1)})),s}openOffCanvas(t){const{offCanvasPosition:e}=this.options,i=window.router["frontend.cookie.offcanvas"],s=a.Z.isXS();this._hideCookieBar(),o.Z.open(i,!1,this._onOffCanvasOpened.bind(this,t),e,void 0,void 0,s)}closeOffCanvas(t){o.Z.close(),"function"==typeof t&&t()}_onOffCanvasOpened(t){this._registerOffCanvasEvents(),this._setInitialState(),this._setInitialOffcanvasState(),PluginManager.initializePlugins(),"function"==typeof t&&t()}_hideCookieBar(){const t=PluginManager.getPluginInstances("CookiePermission");t&&t[0]&&(t[0]._hideCookieBar(),t[0]._removeBodyPadding())}_setInitialState(t=null){const e=t||this._getCookies("all"),i=[],s=[];e.forEach((({cookie:t,required:e})=>{n.Z.getItem(t)||e?i.push(t):s.push(t)})),this.lastState={active:i,inactive:s}}_setInitialOffcanvasState(){const t=this.lastState.active,e=this._getOffCanvas();t.forEach((t=>{const i=e.querySelector(`[data-cookie="${t}"]`);i.checked=!0,this._childCheckboxEvent(i)}))}_handleWrapperTrigger(t){t.preventDefault();const{entriesActiveClass:e,entriesClass:i,groupClass:s}=this.options,{target:n}=t,o=this._findParentEl(n,i,s);if(o){o.classList.contains(e)?o.classList.remove(e):o.classList.add(e)}}_handleCheckbox(t){const{parentInputClass:e}=this.options,{target:i}=t;(i.classList.contains(e)?this._parentCheckboxEvent:this._childCheckboxEvent).call(this,i)}_findParentEl(t,e,i=null){for(;t&&!t.classList.contains(i);){if(t.classList.contains(e))return t;t=t.parentElement}return null}_isChecked(t){return!!t.checked}_parentCheckboxEvent(t){const{groupClass:e}=this.options,i=this._isChecked(t),s=this._findParentEl(t,e);this._toggleWholeGroup(i,s)}_childCheckboxEvent(t){const{groupClass:e}=this.options,i=this._isChecked(t),s=this._findParentEl(t,e);this._toggleParentCheckbox(i,s)}_toggleWholeGroup(t,e){Array.from(e.querySelectorAll("input")).forEach((e=>{e.checked=t}))}_toggleParentCheckbox(t,e){const{parentInputSelector:i}=this.options,s=Array.from(e.querySelectorAll(`input:not(${i})`)),n=Array.from(e.querySelectorAll(`input:not(${i}):checked`));if(s.length>0){const t=e.querySelector(i);if(t){const e=n.length>0,i=e&&n.length!==s.length;t.checked=e,t.indeterminate=i}}}_handleSubmit(){const t=this._getCookies("active"),e=this._getCookies("inactive"),{cookiePreference:i}=this.options,s=[],o=[];e.forEach((({cookie:t})=>{o.push(t),n.Z.getItem(t)&&n.Z.removeItem(t)})),t.forEach((({cookie:t,value:e,expiration:i})=>{s.push(t),t&&e&&n.Z.setItem(t,e,i)})),n.Z.setItem(i,"1","30"),this._handleUpdateListener(s,o),this.closeOffCanvas(document.$emitter.publish(h))}acceptAllCookies(t=!1){if(!t)return this._handleAcceptAll(),void this.closeOffCanvas();c.Z.create(this.el);const e=window.router["frontend.cookie.offcanvas"];this._httpClient.get(e,(t=>{const e=(new DOMParser).parseFromString(t,"text/html");this._handleAcceptAll(e),c.Z.remove(this.el),this._hideCookieBar()}))}_acceptAllCookiesFromCookieBar(){return this.acceptAllCookies(!0)}_acceptAllCookiesFromOffCanvas(){return this.acceptAllCookies()}_handleAcceptAll(t=null){const e=this._getCookies("all",t);this._setInitialState(e);const{cookiePreference:i}=this.options;e.forEach((({cookie:t,value:e,expiration:i})=>{t&&e&&n.Z.setItem(t,e,i)})),n.Z.setItem(i,"1","30"),this._handleUpdateListener(e.map((({cookie:t})=>t)),[])}_getCookies(t="all",e=null){const{cookieSelector:i}=this.options;return e||(e=this._getOffCanvas()),Array.from(e.querySelectorAll(i)).filter((e=>{switch(t){case"all":return!0;case"active":return this._isChecked(e);case"inactive":return!this._isChecked(e);default:return!1}})).map((t=>{const{cookie:e,cookieValue:i,cookieExpiration:s,cookieRequired:n}=t.dataset;return{cookie:e,value:i,expiration:s,required:n}}))}_getOffCanvas(){const t=r.Z?r.Z.getOffCanvas():[];return!!(t&&t.length>0)&&t[0]}}var f,p,g;f=u,p="options",g={offCanvasPosition:"left",submitEvent:"click",cookiePreference:"cookie-preference",cookieSelector:"[data-cookie]",buttonOpenSelector:".js-cookie-configuration-button button",buttonSubmitSelector:".js-offcanvas-cookie-submit",buttonAcceptAllSelector:".js-offcanvas-cookie-accept-all",globalButtonAcceptAllSelector:".js-cookie-accept-all-button",wrapperToggleSelector:".offcanvas-cookie-entries span",parentInputSelector:".offcanvas-cookie-parent-input",customLinkSelector:`[href="${window.router["frontend.cookie.offcanvas"]}"]`,entriesActiveClass:"offcanvas-cookie-entries--active",entriesClass:"offcanvas-cookie-entries",groupClass:"offcanvas-cookie-group",parentInputClass:"offcanvas-cookie-parent-input"},(p=function(t){var e=function(t,e){if("object"!=typeof t||null===t)return t;var i=t[Symbol.toPrimitive];if(void 0!==i){var s=i.call(t,e||"default");if("object"!=typeof s)return s;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===e?String:Number)(t)}(t,"string");return"symbol"==typeof e?e:String(e)}(p))in f?Object.defineProperty(f,p,{value:g,enumerable:!0,configurable:!0,writable:!0}):f[p]=g},3139:(t,e,i)=>{i.d(e,{Z:()=>p});var s,n,o,r=i(6285),a=i(8254),l=i(1966),c=i(3206),d=i(5944),h=i(5362),u=i(6510),f=i(46);class p extends r.Z{init(){this._registry=[],this.httpClient=new a.Z,this._urlFilterParams=d.parse(u.Z.getSearch()),this._filterPanel=c.Z.querySelector(document,this.options.filterPanelSelector,!1),this._filterPanelActive=!!this._filterPanel,this._filterPanelActive&&(this._showResetAll=!1,this.activeFilterContainer=c.Z.querySelector(document,this.options.activeFilterContainerSelector)),this._cmsProductListingWrapper=c.Z.querySelector(document,this.options.cmsProductListingWrapperSelector,!1),this._cmsProductListingWrapperActive=!!this._cmsProductListingWrapper,this._allFiltersInitializedDebounce=f.Z.debounce(this.sendDisabledFiltersRequest.bind(this),100),this._registerEvents()}refreshRegistry(){const t=this._registry.filter((t=>document.body.contains(t.el)));this.init(),this._registry=t,window.PluginManager.initializePlugins()}changeListing(t=!0,e={}){this._buildRequest(t,e),this._filterPanelActive&&this._buildLabels()}registerFilter(t){this._registry.push(t),this._setFilterState(t),this.options.disableEmptyFilter&&this._allFiltersInitializedDebounce()}_setFilterState(t){if(Object.keys(this._urlFilterParams).length>0&&"function"==typeof t.setValuesFromUrl){if(!t.setValuesFromUrl(this._urlFilterParams)||!this._filterPanelActive)return;this._showResetAll=!0,this._buildLabels()}}deregisterFilter(t){this._registry=this._registry.filter((e=>e!==t))}_fetchValuesOfRegisteredFilters(){const t={};return this._registry.forEach((e=>{const i=e.getValues();Object.keys(i).forEach((e=>{Object.prototype.hasOwnProperty.call(t,e)?Object.values(i[e]).forEach((i=>{t[e].push(i)})):t[e]=i[e]}))})),t}_mapFilters(t){const e={};return Object.keys(t).forEach((i=>{let s=t[i];Array.isArray(s)&&(s=s.join("|"));`${s}`.length&&(e[i]=s)})),e}_buildRequest(t=!0,e={}){const i=this._fetchValuesOfRegisteredFilters(),s=this._mapFilters(i);this._filterPanelActive&&(this._showResetAll=!!Object.keys(s).length),this.options.params&&Object.keys(this.options.params).forEach((t=>{s[t]=this.options.params[t]})),Object.entries(e).forEach((([t,e])=>{s[t]=e}));let n=d.stringify(s);this.sendDataRequest(n),delete s.slots,delete s["no-aggregations"],delete s["reduce-aggregations"],delete s["only-aggregations"],n=d.stringify(s),t&&this._updateHistory(n),this.options.scrollTopListingWrapper&&this._scrollTopOfListing()}_scrollTopOfListing(){const t=this._cmsProductListingWrapper.getBoundingClientRect();if(t.top>=0)return;const e=t.top+window.scrollY-this.options.scrollOffset;window.scrollTo({top:e,behavior:"smooth"})}_getDisabledFiltersParamsFromParams(t){const e=Object.assign({},{"only-aggregations":1,"reduce-aggregations":1},t);return delete e.p,delete e.order,delete e["no-aggregations"],e}_updateHistory(t){u.Z.push(u.Z.getLocation().pathname,t,{})}_buildLabels(){let t="";this._registry.forEach((e=>{const i=e.getLabels();i.length&&i.forEach((e=>{t+=this.getLabelTemplate(e)}))})),this.activeFilterContainer.innerHTML=t;const e=c.Z.querySelectorAll(this.activeFilterContainer,`.${this.options.activeFilterLabelRemoveClass}`,!1);t.length&&(this._registerLabelEvents(e),this.createResetAllButton())}_registerLabelEvents(t){l.Z.iterate(t,(t=>{t.addEventListener("click",(()=>this.resetFilter(t)))}))}createResetAllButton(){this.activeFilterContainer.insertAdjacentHTML("beforeend",this.getResetAllButtonTemplate());const t=c.Z.querySelector(this.activeFilterContainer,this.options.resetAllFilterButtonSelector);t.removeEventListener("click",this.resetAllFilter.bind(this)),t.addEventListener("click",this.resetAllFilter.bind(this)),this._showResetAll||t.remove()}resetFilter(t){this._registry.forEach((e=>{e.reset(t.dataset.id)})),this._buildRequest(),this._buildLabels()}resetAllFilter(){this._registry.forEach((t=>{t.resetAll()})),this._buildRequest(),this._buildLabels()}getLabelTemplate(t){return`\n        <span class="${this.options.activeFilterLabelClass}">\n            ${this.getLabelPreviewTemplate(t)}\n            ${t.label}\n            <button class="${this.options.activeFilterLabelRemoveClass}"\n                    data-id="${t.id}">\n                &times;\n            </button>\n        </span>\n        `}getLabelPreviewTemplate(t){const e=this.options.activeFilterLabelPreviewClass;return t.previewHex?`\n                <span class="${e}" style="background-color: ${t.previewHex};"></span>\n            `:t.previewImageUrl?`\n                <span class="${e}" style="background-image: url('${t.previewImageUrl}');"></span>\n            `:""}getResetAllButtonTemplate(){return`\n        <button class="${this.options.resetAllFilterButtonClasses}">\n            ${this.options.snippets.resetAllButtonText}\n        </button>\n        `}addLoadingIndicatorClass(){this._filterPanel.classList.add(this.options.loadingIndicatorClass)}removeLoadingIndicatorClass(){this._filterPanel.classList.remove(this.options.loadingIndicatorClass)}addLoadingElementLoaderClass(){this._cmsProductListingWrapper.classList.add(this.options.loadingElementLoaderClass)}removeLoadingElementLoaderClass(){this._cmsProductListingWrapper.classList.remove(this.options.loadingElementLoaderClass)}sendDataRequest(t){this._filterPanelActive&&this.addLoadingIndicatorClass(),this._cmsProductListingWrapperActive&&this.addLoadingElementLoaderClass(),this.options.disableEmptyFilter&&this.sendDisabledFiltersRequest(),this.httpClient.get(`${this.options.dataUrl}?${t}`,(t=>{this.renderResponse(t),this._filterPanelActive&&this.removeLoadingIndicatorClass(),this._cmsProductListingWrapperActive&&this.removeLoadingElementLoaderClass()}))}sendDisabledFiltersRequest(){const t=this._fetchValuesOfRegisteredFilters(),e=this._mapFilters(t);this.options.params&&Object.keys(this.options.params).forEach((t=>{e[t]=this.options.params[t]})),this._allFiltersInitializedDebounce=()=>{};const i=this._getDisabledFiltersParamsFromParams(e);this.httpClient.get(`${this.options.filterUrl}?${d.stringify(i)}`,(t=>{const e=JSON.parse(t);this._registry.forEach((t=>{"function"==typeof t.refreshDisabledState&&t.refreshDisabledState(e,i)}))}))}renderResponse(t){h.Z.replaceFromMarkup(t,this.options.cmsProductListingSelector,!1),this._registry.forEach((t=>{"function"==typeof t.afterContentChange&&t.afterContentChange()})),window.PluginManager.initializePlugins(),this.$emitter.publish("Listing/afterRenderResponse",{response:t})}_registerEvents(){window.onpopstate=this._onWindowPopstate.bind(this)}_onWindowPopstate(){this.refreshRegistry(),this._registry.forEach((t=>{0===Object.keys(this._urlFilterParams).length&&(this._urlFilterParams.p=1),this._setFilterState(t)})),this.options.disableEmptyFilter&&this._allFiltersInitializedDebounce(),this.changeListing(!1)}}s=p,o={dataUrl:"",filterUrl:"",params:{},filterPanelSelector:".filter-panel",cmsProductListingSelector:".cms-element-product-listing",cmsProductListingWrapperSelector:".cms-element-product-listing-wrapper",activeFilterContainerSelector:".filter-panel-active-container",activeFilterLabelClass:"filter-active",activeFilterLabelRemoveClass:"filter-active-remove",activeFilterLabelPreviewClass:"filter-active-preview",resetAllFilterButtonClasses:"filter-reset-all btn btn-sm btn-outline-danger",resetAllFilterButtonSelector:".filter-reset-all",loadingIndicatorClass:"is-loading",loadingElementLoaderClass:"has-element-loader",disableEmptyFilter:!1,snippets:{resetAllButtonText:"Reset all"},scrollTopListingWrapper:!0,scrollOffset:15},(n=function(t){var e=function(t,e){if("object"!=typeof t||null===t)return t;var i=t[Symbol.toPrimitive];if(void 0!==i){var s=i.call(t,e||"default");if("object"!=typeof s)return s;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===e?String:Number)(t)}(t,"string");return"symbol"==typeof e?e:String(e)}(n="options"))in s?Object.defineProperty(s,n,{value:o,enumerable:!0,configurable:!0,writable:!0}):s[n]=o},2615:(t,e,i)=>{i.d(e,{Z:()=>a});var s=i(3637),n=i(8254),o=i(7906);let r=null;class a extends s.Z{static open(t=!1,e=!1,i=null,n="left",o=!0,r=s.Z.REMOVE_OFF_CANVAS_DELAY(),a=!1,l=""){if(!t)throw new Error("A url must be given!");s.r._removeExistingOffCanvas();const c=s.r._createOffCanvas(n,a,l,o);this.setContent(t,e,i,o,r),s.r._openOffcanvas(c)}static setContent(t,e,i,s,l){const c=new n.Z;super.setContent(`<div class="offcanvas-content-container">${o.Z.getTemplate()}</div>`,s,l),r&&r.abort();const d=t=>{super.setContent(t,s,l),"function"==typeof i&&i(t)};r=e?c.post(t,e,a.executeCallback.bind(this,d)):c.get(t,a.executeCallback.bind(this,d))}static executeCallback(t,e){"function"==typeof t&&t(e),window.PluginManager.initializePlugins()}}},3637:(t,e,i)=>{i.d(e,{Z:()=>d,r:()=>c});var s=i(9658),n=i(2005),o=i(1966);const r="offcanvas",a=350;class l{constructor(){this.$emitter=new n.Z}open(t,e,i,s,n,o,r){this._removeExistingOffCanvas();const a=this._createOffCanvas(i,o,r,s);this.setContent(t,s,n),this._openOffcanvas(a,e)}setContent(t,e,i){const s=this.getOffCanvas();s[0]&&(s[0].innerHTML=t,this._registerEvents(i))}setAdditionalClassName(t){this.getOffCanvas()[0].classList.add(t)}getOffCanvas(){return document.querySelectorAll(`.${r}`)}close(t){const e=this.getOffCanvas();o.Z.iterate(e,(t=>{bootstrap.Offcanvas.getInstance(t).hide()})),setTimeout((()=>{this.$emitter.publish("onCloseOffcanvas",{offCanvasContent:e})}),t)}goBackInHistory(){window.history.back()}exists(){return this.getOffCanvas().length>0}_openOffcanvas(t,e){l.bsOffcanvas.show(),window.history.pushState("offcanvas-open",""),"function"==typeof e&&e()}_registerEvents(t){const e=s.Z.isTouchDevice()?"touchend":"click",i=this.getOffCanvas();o.Z.iterate(i,(e=>{const s=()=>{setTimeout((()=>{e.remove(),this.$emitter.publish("onCloseOffcanvas",{offCanvasContent:i})}),t),e.removeEventListener("hide.bs.offcanvas",s)};e.addEventListener("hide.bs.offcanvas",s)})),window.addEventListener("popstate",this.close.bind(this,t),{once:!0});const n=document.querySelectorAll(".js-offcanvas-close");o.Z.iterate(n,(i=>i.addEventListener(e,this.close.bind(this,t))))}_removeExistingOffCanvas(){l.bsOffcanvas=null;const t=this.getOffCanvas();return o.Z.iterate(t,(t=>t.remove()))}_getPositionClass(t){return"left"===t?"offcanvas-start":"right"===t?"offcanvas-end":`offcanvas-${t}`}_createOffCanvas(t,e,i,s){const n=document.createElement("div");if(n.classList.add(r),n.classList.add(this._getPositionClass(t)),!0===e&&n.classList.add("is-fullwidth"),i){const t=typeof i;if("string"===t)n.classList.add(i);else{if(!Array.isArray(i))throw new Error(`The type "${t}" is not supported. Please pass an array or a string.`);i.forEach((t=>{n.classList.add(t)}))}}return document.body.appendChild(n),l.bsOffcanvas=new bootstrap.Offcanvas(n,{backdrop:!1!==s||"static"}),n}}const c=Object.freeze(new l);class d{static open(t,e=null,i="left",s=!0,n=350,o=!1,r=""){c.open(t,e,i,s,n,o,r)}static setContent(t,e=!0,i=350){c.setContent(t,e,i)}static setAdditionalClassName(t){c.setAdditionalClassName(t)}static close(t=350){c.close(t)}static exists(){return c.exists()}static getOffCanvas(){return c.getOffCanvas()}static REMOVE_OFF_CANVAS_DELAY(){return a}}}},t=>{t.O(0,["vendor-node","vendor-shared"],(()=>{return e=3880,t(t.s=e);var e}));t.O()}]);