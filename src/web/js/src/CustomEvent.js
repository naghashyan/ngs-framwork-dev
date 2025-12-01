/**
 * patch for a customEvent constructor
 * for a ie9 and high
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
 * @mail levon@naghashyan.com
     * @year 2007-2026
     * @version 5.0.0
 */
(function () {
  if ( typeof window.CustomEvent === "function" ) return false;
  function CustomEvent(event, params) {
    params = params || {
        bubbles: false,
        cancelable: false,
        detail: undefined
      };
    var evt = document.createEvent('CustomEvent');
    evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
    return evt;
  }

  CustomEvent.prototype = window.Event.prototype;
  window.CustomEvent = CustomEvent;
})();