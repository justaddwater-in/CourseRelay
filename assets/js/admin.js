/* global jQuery, CourseRelayAdmin */
(function ($) {
	'use strict';

	var cfg     = window.CourseRelayAdmin || {};
	var ajaxUrl = cfg.ajax_url;
	var nonce   = cfg.nonce;
	var str     = cfg.strings || {};

	function showNotice(el, msg, type) {
		$( el )
			.removeClass( 'courserelay-notice-success courserelay-notice-error courserelay-notice-info' )
			.addClass( 'courserelay-notice-' + (type || 'info') )
			.text( msg )
			.show();
	}

	// ── Upload form ─────────────────────────────────────────────────────────────
	$( document ).on(
		'submit',
		'#courserelay-upload-form',
		function (e) {
			e.preventDefault();

			var form = this;
			var fd   = new FormData( form );
			fd.set( 'action', 'courserelay_upload' );
			fd.set( 'nonce', nonce || $( form ).find( '[name="nonce"]' ).val() || '' );

			var $btn     = $( '#courserelay-upload-btn' ).prop( 'disabled', true ).text( str.uploading || 'Uploading…' );
			var $spinner = $( '#courserelay-upload-spinner' ).show();

			$.ajax(
				{
					url:         ajaxUrl,
					method:      'POST',
					data:        fd,
					processData: false,
					contentType: false,
					success: function (res) {
						$spinner.hide();
						$btn.prop( 'disabled', false ).text( 'Upload & Process' );
						if (res.success) {
							showNotice( '#courserelay-upload-notice', res.data.message, 'success' );
							setTimeout(
								function () {
									window.location.href = res.data.redirect;
								},
								1200
							);
						} else {
							showNotice( '#courserelay-upload-notice', res.data || str.upload_error, 'error' );
						}
					},
					error: function () {
						$spinner.hide();
						$btn.prop( 'disabled', false ).text( 'Upload & Process' );
						showNotice( '#courserelay-upload-notice', str.upload_error, 'error' );
					}
				}
			);
		}
	);

	// ── Content list: edit / delete ────────────────────────────────────────────
	$( document ).on(
		'click',
		'.courserelay-edit-btn',
		function (e) {
			e.preventDefault();
			var id = $( this ).data( 'id' );
			$( '#courserelay-edit-' + id ).toggle();
		}
	);

	$( document ).on(
		'click',
		'.courserelay-cancel-edit',
		function (e) {
			e.preventDefault();
			var id = $( this ).data( 'id' );
			$( '#courserelay-edit-' + id ).hide();
		}
	);

	$( document ).on(
		'click',
		'.courserelay-save-content',
		function (e) {
			e.preventDefault();
			var $btn = $( this );
			var id   = $btn.data( 'id' );
			var $row = $( '#courserelay-edit-' + id );

			var data = {
				action:               'courserelay_update_content',
				nonce:                nonce,
				post_id:              id,
				content_title:        $row.find( '[name="content_title"]' ).val(),
				mastery_score:        $row.find( '[name="mastery_score"]' ).val(),
				completion_tracking:  $row.find( '[name="completion_tracking"]' ).is( ':checked' ) ? 1 : 0,
				completion_type:      $row.find( '[name="completion_type"]' ).val(),
				width:                $row.find( '[name="width"]' ).val(),
				height:               $row.find( '[name="height"]' ).val(),
			};

			$btn.prop( 'disabled', true ).text( str.saving || 'Saving…' );
			$.post(
				ajaxUrl,
				data,
				function (res) {
					$btn.prop( 'disabled', false ).text( 'Save Changes' );
					if (res.success) {
						showNotice( '#courserelay-notice', res.data.message, 'success' );
						$row.hide();
						location.reload();
					} else {
						showNotice( '#courserelay-notice', res.data || 'Error saving.', 'error' );
					}
				}
			);
		}
	);

	$( document ).on(
		'click',
		'.courserelay-delete-btn',
		function (e) {
			e.preventDefault();
			if ( ! confirm( str.confirm_delete || 'Delete this SCORM package?' ) ) {
				return;
			}

			var id = $( this ).data( 'id' );
			$.post(
				ajaxUrl,
				{ action: 'courserelay_delete_content', nonce: nonce, post_id: id },
				function (res) {
					if (res.success) {
						$( '#courserelay-row-' + id ).fadeOut(
							300,
							function () {
								$( this ).remove(); }
						);
						$( '#courserelay-edit-' + id ).remove();
						showNotice( '#courserelay-notice', res.data.message, 'success' );
					} else {
						showNotice( '#courserelay-notice', res.data || 'Error.', 'error' );
					}
				}
			);
		}
	);

	// ── Settings form ───────────────────────────────────────────────────────────
	$( '#courserelay-settings-form' ).on(
		'submit',
		function (e) {
			e.preventDefault();
			var $btn     = $( this ).find( 'button[type=submit]' ).prop( 'disabled', true ).text( str.saving || 'Saving…' );
			var $spinner = $( '#courserelay-settings-spinner' ).show();
			var data     = $( this ).serializeArray();
			data.push( { name: 'action', value: 'courserelay_save_settings' } );
			data.push( { name: 'nonce',  value: nonce } );

			$.post(
				ajaxUrl,
				$.param( data ),
				function (res) {
					$spinner.hide();
					$btn.prop( 'disabled', false ).text( 'Save Settings' );
					if (res.success) {
						showNotice( '#courserelay-settings-notice', res.data.message, 'success' );
					} else {
						showNotice( '#courserelay-settings-notice', res.data || 'Error.', 'error' );
					}
				}
			);
		}
	);

	// ── Reset learner ────────────────────────────────────────────────────────────
	$( '#courserelay-reset-btn' ).on(
		'click',
		function (e) {
			e.preventDefault();
			var data = {
				action:     'courserelay_reset_user',
				nonce:      nonce,
				user_id:    $( '#reset-user-id' ).val(),
				content_id: $( '#reset-content-id' ).val(),
				post_id:    $( '#reset-post-id' ).val(),
				course_id:  $( '#reset-course-id' ).val(),
			};
			$.post(
				ajaxUrl,
				data,
				function (res) {
					if (res.success) {
						showNotice( '#courserelay-reset-notice', res.data.message, 'success' );
					} else {
						showNotice( '#courserelay-reset-notice', res.data || 'Error.', 'error' );
					}
				}
			);
		}
	);

}(jQuery));
