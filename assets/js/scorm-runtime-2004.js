/**
 * Scormpleted — SCORM 2004 Runtime API
 *
 * ARCHITECTURE: Same as scorm-runtime-12.js — runs in the launch page (parent).
 * SCORM 2004 content calls window.parent.API_1484_11.Initialize() etc.
 *
 * GPL-2.0+
 */
(function (w) {
    'use strict';

    if (typeof SCORMPLETED === 'undefined') {
        console.error('[Scormpleted] SCORMPLETED config not found on launch page.');
        return;
    }

    var ajaxUrl     = SCORMPLETED.ajax_url;
    var scormVer    = '2004';
    var commitTimer = null;
    var initialized = false;
    var finished    = false;

    function buildPostBody(action) {
        var parts = [];
        parts.push('action=' + encodeURIComponent(action));
        parts.push('params%5Bscorm_version%5D=' + encodeURIComponent(scormVer));
        for (var key in cache) {
            if (Object.prototype.hasOwnProperty.call(cache, key)) {
                parts.push(
                    'params%5Bdata%5D%5B' + encodeURIComponent(key) + '%5D=' +
                    encodeURIComponent(cache[key] !== null && cache[key] !== undefined ? cache[key] : '')
                );
            }
        }
        return parts.join('&');
    }

    function sendAjax(action, async) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, async !== false);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.send(buildPostBody(action));
        return xhr;
    }

    function scheduleCommit() {
        if (commitTimer) clearTimeout(commitTimer);
        commitTimer = setTimeout(function () {
            commitTimer = null;
            sendAjax('scormpleted_commit', true);
        }, 500);
    }

    function shouldCommit() {
        var checks = SCORMPLETED.commit_checks || [
            'cmi.suspend_data', 'cmi.completion_status', 'cmi.location'
        ];
        var last = SCORMPLETED._last || {};
        for (var i = 0; i < checks.length; i++) {
            if (cache[checks[i]] !== last[checks[i]]) return true;
        }
        return false;
    }

    function snapshotCache() {
        SCORMPLETED._last = {};
        for (var k in cache) {
            if (Object.prototype.hasOwnProperty.call(cache, k)) {
                SCORMPLETED._last[k] = cache[k];
            }
        }
    }

    // ── SCORM 2004 API object (API_1484_11) ─────────────────────────────────────
    var API_1484_11 = {

        Initialize: function (param) {
            if (initialized) return 'true';
            initialized = true;
            finished    = false;
            snapshotCache();
            return 'true';
        },

        Terminate: function (param) {
            if (finished) return 'true';
            finished = true;
            if (commitTimer) { clearTimeout(commitTimer); commitTimer = null; }
            sendAjax('scormpleted_finish', false); // synchronous
            return 'true';
        },

        GetValue: function (element) {
            var val = cache[element];
            return (val !== null && val !== undefined) ? String(val) : '';
        },

        SetValue: function (element, value) {
            if (finished) return 'false';
            cache[element] = value;

            // Stop flood after terminal completion/success status
            var completionKey = 'cmi.completion_status';
            var successKey    = 'cmi.success_status';
            var doneCompletion = { 'completed': true };
            var doneSuccess    = { 'passed': true, 'failed': true };
            var isDone = ( cache[completionKey] && doneCompletion[ cache[completionKey] ] ) ||
                         ( cache[successKey]    && doneSuccess[ cache[successKey] ] );

            if (isDone) {
                if (!SCORMPLETED._completion_commit_sent) {
                    SCORMPLETED._completion_commit_sent = true;
                    if (commitTimer) clearTimeout(commitTimer);
                    commitTimer = setTimeout(function () {
                        commitTimer = null;
                        sendAjax('scormpleted_commit', true);
                    }, 2000);
                }
                return 'true';
            }

            if (shouldCommit()) {
                snapshotCache();
                scheduleCommit();
            }
            return 'true';
        },

        Commit: function (param) {
            if (commitTimer) { clearTimeout(commitTimer); commitTimer = null; }
            sendAjax('scormpleted_commit', true);
            return 'true';
        },

        GetLastError:   function () { return '0'; },
        GetErrorString: function (c) { return ''; },
        GetDiagnostic:  function (c) { return ''; }
    };

    w.API_1484_11 = API_1484_11;

    if (w.parent && w.parent !== w) {
        try { w.parent.API_1484_11 = API_1484_11; } catch(e) {}
    }

}(window));
