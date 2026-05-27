/* global jQuery, ScormcompletedAdmin */
(function ($) {
    'use strict';

    var cfg = ScormcompletedAdmin || {};
    var ajaxUrl = cfg.ajax_url;
    var nonce   = cfg.nonce;
    var str     = cfg.strings || {};

    function showNotice(el, msg, type) {
        $(el)
            .removeClass('scormpleted-notice-success scormpleted-notice-error scormpleted-notice-info')
            .addClass('scormpleted-notice-' + (type || 'info'))
            .text(msg)
            .show();
    }

    // ── Upload form ─────────────────────────────────────────────────────────────
    $('#scormpleted-upload-form').on('submit', function (e) {
        e.preventDefault();

        var form    = this;
        var fd      = new FormData(form);
        fd.append('action', 'scormpleted_upload');
        fd.append('nonce', nonce);

        var $btn     = $('#scormpleted-upload-btn').prop('disabled', true).text(str.uploading || 'Uploading…');
        var $spinner = $('#scormpleted-upload-spinner').show();

        $.ajax({
            url:         ajaxUrl,
            method:      'POST',
            data:        fd,
            processData: false,
            contentType: false,
            success: function (res) {
                $spinner.hide();
                $btn.prop('disabled', false).text('Upload & Process');
                if (res.success) {
                    showNotice('#scormpleted-upload-notice', res.data.message, 'success');
                    setTimeout(function () {
                        window.location.href = res.data.redirect;
                    }, 1200);
                } else {
                    showNotice('#scormpleted-upload-notice', res.data || str.upload_error, 'error');
                }
            },
            error: function () {
                $spinner.hide();
                $btn.prop('disabled', false).text('Upload & Process');
                showNotice('#scormpleted-upload-notice', str.upload_error, 'error');
            }
        });
    });

    // ── Content list: edit / delete ────────────────────────────────────────────
    $(document).on('click', '.scormpleted-edit-btn', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#scormpleted-edit-' + id).toggle();
    });

    $(document).on('click', '.scormpleted-cancel-edit', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#scormpleted-edit-' + id).hide();
    });

    $(document).on('click', '.scormpleted-save-content', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var id   = $btn.data('id');
        var $row = $('#scormpleted-edit-' + id);

        var data = {
            action:               'scormpleted_update_content',
            nonce:                nonce,
            post_id:              id,
            content_title:        $row.find('[name="content_title"]').val(),
            mastery_score:        $row.find('[name="mastery_score"]').val(),
            completion_tracking:  $row.find('[name="completion_tracking"]').is(':checked') ? 1 : 0,
            completion_type:      $row.find('[name="completion_type"]').val(),
            width:                $row.find('[name="width"]').val(),
            height:               $row.find('[name="height"]').val(),
        };

        $btn.prop('disabled', true).text(str.saving || 'Saving…');
        $.post(ajaxUrl, data, function (res) {
            $btn.prop('disabled', false).text('Save Changes');
            if (res.success) {
                showNotice('#scormpleted-notice', res.data.message, 'success');
                $row.hide();
                location.reload();
            } else {
                showNotice('#scormpleted-notice', res.data || 'Error saving.', 'error');
            }
        });
    });

    $(document).on('click', '.scormpleted-delete-btn', function (e) {
        e.preventDefault();
        if ( ! confirm(str.confirm_delete || 'Delete this SCORM package?') ) return;

        var id = $(this).data('id');
        $.post(ajaxUrl, { action: 'scormpleted_delete_content', nonce: nonce, post_id: id }, function (res) {
            if (res.success) {
                $('#scormpleted-row-' + id).fadeOut(300, function () { $(this).remove(); });
                $('#scormpleted-edit-' + id).remove();
                showNotice('#scormpleted-notice', res.data.message, 'success');
            } else {
                showNotice('#scormpleted-notice', res.data || 'Error.', 'error');
            }
        });
    });

    // ── Settings form ───────────────────────────────────────────────────────────
    $('#scormpleted-settings-form').on('submit', function (e) {
        e.preventDefault();
        var $btn     = $(this).find('button[type=submit]').prop('disabled', true).text(str.saving || 'Saving…');
        var $spinner = $('#scormpleted-settings-spinner').show();
        var data     = $(this).serializeArray();
        data.push({ name: 'action', value: 'scormpleted_save_settings' });
        data.push({ name: 'nonce',  value: nonce });

        $.post(ajaxUrl, $.param(data), function (res) {
            $spinner.hide();
            $btn.prop('disabled', false).text('Save Settings');
            if (res.success) {
                showNotice('#scormpleted-settings-notice', res.data.message, 'success');
            } else {
                showNotice('#scormpleted-settings-notice', res.data || 'Error.', 'error');
            }
        });
    });

    // ── Reset learner ────────────────────────────────────────────────────────────
    $('#scormpleted-reset-btn').on('click', function (e) {
        e.preventDefault();
        var data = {
            action:     'scormpleted_reset_user',
            nonce:      nonce,
            user_id:    $('#reset-user-id').val(),
            content_id: $('#reset-content-id').val(),
            post_id:    $('#reset-post-id').val(),
            course_id:  $('#reset-course-id').val(),
        };
        $.post(ajaxUrl, data, function (res) {
            if (res.success) {
                showNotice('#scormpleted-reset-notice', res.data.message, 'success');
            } else {
                showNotice('#scormpleted-reset-notice', res.data || 'Error.', 'error');
            }
        });
    });

}(jQuery));
