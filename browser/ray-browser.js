/*!
 * DockRay browser error collector.
 *
 * Sends JavaScript errors to the host application, never straight to the
 * panel: authenticating from the browser would mean shipping the project
 * private key in page source. The application forwards them with its own key.
 *
 * Configured through a global object the host renders before this file:
 *
 *   window.DockRayConfig = {
 *     endpoint: '/wp-json/dockray/v1/browser-error',
 *     token: 'csrf-token',          // opaque, passed back to the host
 *     release: '1.4.0',             // optional
 *     sampleRate: 1,                // optional, 0..1
 *     maxEvents: 10,                // optional, per page view
 *     ignore: ['ResizeObserver']    // optional, substrings
 *   };
 */
(function (window, document) {
    'use strict';

    var config = window.DockRayConfig;

    if (!config || !config.endpoint || typeof window.fetch !== 'function') {
        return;
    }

    var maxEvents = typeof config.maxEvents === 'number' ? config.maxEvents : 10;
    var sampleRate = typeof config.sampleRate === 'number' ? config.sampleRate : 1;
    var ignore = config.ignore || [];
    var sent = 0;
    var seen = {};

    function ignored(message) {
        for (var i = 0; i < ignore.length; i++) {
            if (message.indexOf(ignore[i]) !== -1) {
                return true;
            }
        }

        return false;
    }

    /*
     * One broken handler inside a render loop can fire thousands of times a
     * second. The fingerprint keeps a page view to one report per distinct
     * error, and maxEvents caps the rest.
     */
    function duplicate(fingerprint) {
        if (seen[fingerprint]) {
            return true;
        }

        seen[fingerprint] = true;

        return false;
    }

    function frames(error) {
        if (!error || typeof error.stack !== 'string') {
            return [];
        }

        var parsed = [];
        var lines = error.stack.split('\n');

        for (var i = 0; i < lines.length && parsed.length < 30; i++) {
            var match = lines[i].match(/(?:at\s+(.*?)\s+\()?(?:\()?((?:https?|file|blob):\/\/[^\s)]+?|<anonymous>):(\d+):(\d+)\)?/);

            if (match) {
                parsed.push({
                    'function': match[1] || '?',
                    filename: match[2],
                    lineno: parseInt(match[3], 10) || 0,
                    colno: parseInt(match[4], 10) || 0
                });
            }
        }

        return parsed;
    }

    function report(type, message, error, handler) {
        if (sent >= maxEvents || !message || ignored(message)) {
            return;
        }

        if (sampleRate < 1 && Math.random() > sampleRate) {
            return;
        }

        var stack = frames(error);
        var top = stack.length ? stack[0] : null;
        var fingerprint = type + '|' + message + '|' + (top ? top.filename + ':' + top.lineno : '');

        if (duplicate(fingerprint)) {
            return;
        }

        sent++;

        var body = {
            type: type,
            message: String(message).slice(0, 1024),
            stack: stack,
            url: window.location.href,
            referrer: document.referrer || '',
            handler: handler,
            level: 'error'
        };

        if (config.release) {
            body.release = config.release;
        }

        send(body);
    }

    /*
     * `keepalive` lets the request outlive the page, which matters for an
     * error thrown while the visitor is already navigating away. sendBeacon is
     * the fallback because it survives unload on browsers where fetch does not.
     */
    function send(body) {
        var payload = JSON.stringify(body);

        try {
            window.fetch(config.endpoint, {
                method: 'POST',
                keepalive: true,
                credentials: 'same-origin',
                headers: headers(),
                body: payload
            })['catch'](function () {});
        } catch (error) {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(config.endpoint, payload);
            }
        }
    }

    function headers() {
        var result = { 'Content-Type': 'application/json' };

        if (config.token) {
            result['X-DockRay-Token'] = config.token;
        }

        return result;
    }

    window.addEventListener('error', function (event) {
        if (event.error || event.message) {
            report(
                event.error && event.error.name ? event.error.name : 'Error',
                event.error && event.error.message ? event.error.message : event.message,
                event.error,
                'onerror'
            );
        }
    });

    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;

        report(
            reason && reason.name ? reason.name : 'UnhandledRejection',
            reason && reason.message ? reason.message : String(reason),
            reason,
            'unhandledrejection'
        );
    });

    window.DockRay = {
        captureException: function (error) {
            report(error && error.name ? error.name : 'Error', error && error.message, error, 'manual');
        },
        captureMessage: function (message) {
            report('Message', message, null, 'manual');
        }
    };
})(window, document);
