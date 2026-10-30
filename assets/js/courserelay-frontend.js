/**
 * CourseRelay — Frontend completion watcher
 *
 * Polls (and listens for postMessage from the SCORM iframe) to detect when
 * a SCORM package has been completed, then shows/enables the LearnDash
 * "Mark Complete" button or auto-redirects to the next step, depending on
 * the content's configured completion type.
 *
 * Localized data is provided via CourseRelayLD (see class-learndash.php).
 *
 * GPL-2.0+
 */
(function ($) {
	'use strict';

	if (typeof CourseRelayLD === 'undefined') {
		return;
	}

	var cfg  = CourseRelayLD;
	var type = cfg.completion_type;
	var contentIds = Array.isArray(cfg.content_ids) && cfg.content_ids.length ? cfg.content_ids : [cfg.content_id];

	// Check for completion after SCORM iframe posts a message
	// or after a poll interval
	window.addEventListener(
		'message',
		function (e) {
			if (e.data && e.data.courserelay_completed) {
				onScormCompleted();
			}
		}
	);

	// Ask the server whether every tracked package in this step is complete.
	function checkCompletion() {
		var checks = contentIds.map(function (contentId) {
			return $.post(
				cfg.ajax_url,
				{ action: 'courserelay_check_completion', nonce: cfg.nonce, content_id: contentId, post_id: cfg.post_id }
			);
		});
		$.when.apply( $, checks ).done(
			function () {
				// jQuery passes (data, status, xhr) for ONE request, but an array of
				// [data, status, xhr] per request when several are combined.
				var results = checks.length === 1
					? [ arguments[0] ]
					: Array.prototype.slice.call( arguments ).map( function (entry) {
						return entry && entry[0]; } );
				var allCompleted = results.length === contentIds.length && results.every(
					function (res) {
						return res && res.success && res.data && res.data.completed; }
				);
				if (allCompleted) {
					onScormCompleted();
				}
			}
		);
	}

	// Poll every 3s in case postMessage isn't used
	var pollInterval = setInterval( checkCompletion, 3000 );

	// Re-check immediately when the popup player is closed.
	window.addEventListener( 'courserelay:closed', checkCompletion );

	// If already complete on load, act immediately
	if (cfg.is_completed) {
		onScormCompleted();
	}

	function onScormCompleted() {
		clearInterval( pollInterval );

		if (type === cfg.types.redirect) {
			// Auto-redirect to next step
			if (cfg.next_url) {
				window.location.href = cfg.next_url;
			}
			return;
		}

		if (type === cfg.types.hide || type === cfg.types.hidden) {
			// Show the Mark Complete button
			$( '#sfwd-mark-complete, .sfwd-mark-complete, .learndash_mark_complete_button' ).show();
			return;
		}

		if (type === cfg.types.disable) {
			// Enable the disabled button
			$( '#learndash_mark_complete_button, .learndash_mark_complete_button' )
				.prop( 'disabled', false )
				.removeClass( 'courserelay-btn-disabled' )
				.css( { 'opacity': '1', 'cursor': 'pointer', 'pointer-events': 'auto' } );
			return;
		}
	}

}(jQuery));
