<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap courserelay-wrap">
    <h1><?php esc_html_e( 'CourseRelay Settings', 'courserelay' ); ?></h1>
    <hr class="wp-header-end">
    <div id="courserelay-settings-notice" class="notice" style="display:none;"></div>

    <form id="courserelay-settings-form">
        <table class="form-table">
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Default Width', 'courserelay' ); ?></label></th>
                <td>
                    <input type="text" name="settings[width]" class="small-text" value="<?php echo esc_attr( $settings['width'] ); ?>" placeholder="100%">
                    <p class="description"><?php esc_html_e( 'Default iframe width for all content. e.g. 100% or 800px', 'courserelay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Default Height', 'courserelay' ); ?></label></th>
                <td>
                    <input type="text" name="settings[height]" class="small-text" value="<?php echo esc_attr( $settings['height'] ); ?>" placeholder="600">
                    <p class="description"><?php esc_html_e( 'Default iframe height in pixels (without px). e.g. 600', 'courserelay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Default Completion Type', 'courserelay' ); ?></label></th>
                <td>
                    <select name="settings[completion_type]">
                        <option value="hide_button"            <?php selected( $settings['completion_type'], 'hide_button' ); ?>><?php esc_html_e( 'Hide Button (show on completion)', 'courserelay' ); ?></option>
                        <option value="hidden_until_complete"  <?php selected( $settings['completion_type'], 'hidden_until_complete' ); ?>><?php esc_html_e( 'Show Button on Completion', 'courserelay' ); ?></option>
                        <option value="disable_until_complete" <?php selected( $settings['completion_type'], 'disable_until_complete' ); ?>><?php esc_html_e( 'Enable Button on Completion', 'courserelay' ); ?></option>
                        <option value="completion_move_nextlevel" <?php selected( $settings['completion_type'], 'completion_move_nextlevel' ); ?>><?php esc_html_e( 'Auto-redirect on Completion', 'courserelay' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Debug Mode', 'courserelay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="settings[debug]" value="1" <?php checked( $settings['debug'], '1' ); ?>>
                        <?php esc_html_e( 'Write debug log to wp-content/courserelay-debug.log (disable when not troubleshooting — log auto-rotates at 1MB)', 'courserelay' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'courserelay' ); ?></button>
            <span id="courserelay-settings-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
        </p>
    </form>

    <hr>
    <h2><?php esc_html_e( 'Reset Learner Data', 'courserelay' ); ?></h2>
    <p><?php esc_html_e( 'Use this to reset a learner\'s SCORM progress for testing.', 'courserelay' ); ?></p>
    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'User ID', 'courserelay' ); ?></th>
            <td><input type="number" id="reset-user-id" class="small-text" placeholder="e.g. 5"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Content ID', 'courserelay' ); ?></th>
            <td><input type="number" id="reset-content-id" class="small-text" placeholder="e.g. 42"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Lesson/Post ID', 'courserelay' ); ?></th>
            <td><input type="number" id="reset-post-id" class="small-text" placeholder="e.g. 100"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Course ID', 'courserelay' ); ?></th>
            <td><input type="number" id="reset-course-id" class="small-text" placeholder="e.g. 90"></td>
        </tr>
    </table>
    <p>
        <button id="courserelay-reset-btn" class="button button-secondary"><?php esc_html_e( 'Reset Learner Progress', 'courserelay' ); ?></button>
    </p>
    <div id="courserelay-reset-notice" class="notice" style="display:none;"></div>
</div>
