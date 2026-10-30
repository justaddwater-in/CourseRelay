/**
 * CourseRelay — SCORM 1.2 Runtime API
 *
 * ARCHITECTURE:
 *   This script runs in the LAUNCH PAGE (the parent window).
 *   The SCORM content iframe calls window.parent.API.LMSxxx().
 *   We set window.API on this page so the iframe can find it.
 *
 * Flow:
 *   1. Launch page loads → this script runs → window.API is set
 *   2. iframe loads SCORM content
 *   3. SCORM content calls: var API = window.parent.API (finds it here)
 *   4. API.LMSSetValue() updates local cache + schedules AJAX commit
 *   5. API.LMSFinish() sends final commit synchronously
 *
 * GPL-2.0+
 */
(function (w) {
	'use strict';

	if (typeof COURSERELAY === 'undefined') {
		console.error( '[CourseRelay] COURSERELAY config not found on launch page.' );
		return;
	}

	var ajaxUrl     = COURSERELAY.ajax_url;
	var scormVer    = '1.2';
	var commitTimer = null;
	var initialized = false;
	var finished    = false;

	// cache is already declared globally: var cache = COURSERELAY.cache;
	// We reference it directly since it's on the same window.

	// ── AJAX helpers ────────────────────────────────────────────────────────────
	function buildPostBody(action) {
		var parts = [];
		parts.push( 'action=' + encodeURIComponent( action ) );
		parts.push( 'params%5Bnonce%5D=' + encodeURIComponent( COURSERELAY.nonce || '' ) );
		parts.push( 'params%5Bpost_id%5D=' + encodeURIComponent( COURSERELAY.post_id || 0 ) );
		parts.push( 'params%5Bscorm_version%5D=' + encodeURIComponent( scormVer ) );
		for (var key in cache) {
			if (Object.prototype.hasOwnProperty.call( cache, key )) {
				parts.push(
					'params%5Bdata%5D%5B' + encodeURIComponent( key ) + '%5D=' +
					encodeURIComponent( cache[key] !== null && cache[key] !== undefined ? cache[key] : '' )
				);
			}
		}
		return parts.join( '&' );
	}

	function sendAjax(action, async) {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', ajaxUrl, async !== false );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.send( buildPostBody( action ) );
		return xhr;
	}

	function scheduleCommit() {
		if (commitTimer) {
			clearTimeout( commitTimer );
		}
		commitTimer = setTimeout(
			function () {
				commitTimer = null;
				sendAjax( 'courserelay_commit', true );
			},
			500
		);
	}

	function shouldCommit() {
		var checks = COURSERELAY.commit_checks || [
			'cmi.suspend_data', 'cmi.core.lesson_status', 'cmi.core.lesson_location'
		];
		var last   = COURSERELAY._last || {};
		for (var i = 0; i < checks.length; i++) {
			if (cache[checks[i]] !== last[checks[i]]) {
				return true;
			}
		}
		return false;
	}

	function snapshotCache() {
		COURSERELAY._last = {};
		for (var k in cache) {
			if (Object.prototype.hasOwnProperty.call( cache, k )) {
				COURSERELAY._last[k] = cache[k];
			}
		}
	}

	// ── SCORM 1.2 API object ────────────────────────────────────────────────────
	var API = {

		LMSInitialize: function (param) {
			if (initialized) {
				return 'true';
			}
			initialized = true;
			finished    = false;
			snapshotCache();
			return 'true';
		},

		LMSFinish: function (param) {
			if (finished) {
				return 'true';
			}
			finished = true;
			if (commitTimer) {
				clearTimeout( commitTimer ); commitTimer = null; }
			// Synchronous so browser doesn't navigate away before it completes
			sendAjax( 'courserelay_finish', false );
			return 'true';
		},

		LMSGetValue: function (element) {
			var val = cache[element];
			return (val !== null && val !== undefined) ? String( val ) : '';
		},

		LMSSetValue: function (element, value) {
			if (finished) {
				return 'false';
			}
			cache[element] = value;

			// Stop the commit flood once lesson_status is a terminal value.
			// Only send one final batched commit to persist the completion, then stop.
			var statusKey    = 'cmi.core.lesson_status';
			var doneStatuses = { 'completed': true, 'passed': true, 'failed': true };
			if ( cache[statusKey] && doneStatuses[ cache[statusKey] ] ) {
				if ( ! COURSERELAY._completion_commit_sent) {
					COURSERELAY._completion_commit_sent = true;
					if (commitTimer) {
						clearTimeout( commitTimer );
					}
					commitTimer = setTimeout(
						function () {
							commitTimer = null;
							sendAjax( 'courserelay_commit', true );
						},
						2000
					);
				}
				return 'true';
			}

			if (shouldCommit()) {
				snapshotCache();
				scheduleCommit();
			}
			return 'true';
		},

		LMSCommit: function (param) {
			if (commitTimer) {
				clearTimeout( commitTimer ); commitTimer = null; }
			sendAjax( 'courserelay_commit', true );
			return 'true';
		},

		LMSGetLastError:   function () {
			return '0'; },
		LMSGetErrorString: function (errorCode) {
			return ''; },
		LMSGetDiagnostic:  function (errorCode) {
			return ''; }
	};

	// Compatibility bridge for packages declared SCORM 1.2 but coded against the
	// SCORM 2004 API. Keep the canonical storage in SCORM 1.2 CMI keys.
	var bridgeMap = {
		'cmi.completion_status': 'cmi.core.lesson_status',
		'cmi.location': 'cmi.core.lesson_location',
		'cmi.score.raw': 'cmi.core.score.raw',
		'cmi.session_time': 'cmi.core.session_time',
		'cmi.total_time': 'cmi.core.total_time',
		'cmi.mode': 'cmi.core.lesson_mode',
		'cmi.learner_id': 'cmi.core.student_id',
		'cmi.learner_name': 'cmi.core.student_name',
		'cmi.credit': 'cmi.core.credit',
		'cmi.entry': 'cmi.core.entry',
		'cmi.exit': 'cmi.core.exit',
		'cmi.suspend_data': 'cmi.suspend_data',
		'cmi.launch_data': 'cmi.launch_data',
		'cmi.interactions._count': 'cmi.interactions._count'
	};
	var API_1484_11 = {
		Initialize: function () { return API.LMSInitialize(''); },
		Terminate: function () { return API.LMSFinish(''); },
		GetValue: function (element) {
			if (element === 'cmi.success_status') {
				var status = API.LMSGetValue('cmi.core.lesson_status');
				return status === 'passed' || status === 'failed' ? status : 'unknown';
			}
			return API.LMSGetValue(bridgeMap[element] || element);
		},
		SetValue: function (element, value) {
			if (element === 'cmi.success_status') {
				return (value === 'passed' || value === 'failed') ? API.LMSSetValue('cmi.core.lesson_status', value) : 'true';
			}
			if (element === 'cmi.score.scaled' && (cache['cmi.core.score.raw'] === '' || cache['cmi.core.score.raw'] === undefined)) {
				var scaled = Number(value);
				if (!isNaN(scaled)) { API.LMSSetValue('cmi.core.score.raw', String(Math.round(scaled * 100))); }
				return 'true';
			}
			return API.LMSSetValue(bridgeMap[element] || element, value);
		},
		Commit: function () { return API.LMSCommit(''); },
		GetLastError: function () { return API.LMSGetLastError(); },
		GetErrorString: function (code) { return API.LMSGetErrorString(code); },
		GetDiagnostic: function (code) { return API.LMSGetDiagnostic(code); }
	};
	w.API_1484_11 = API_1484_11;
	if (w.parent && w.parent !== w) { try { w.parent.API_1484_11 = API_1484_11; } catch (e) {} }

	// Set on THIS window — the iframe will find it via window.parent.API
	w.API = API;

	// Some older content looks for it on window directly (when not in iframe)
	// or traverses up — safe to expose on both.
	if (w.parent && w.parent !== w) {
		try {
			w.parent.API = API; } catch (e) {
			}
	}

}(window));
