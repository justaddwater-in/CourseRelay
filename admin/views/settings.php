<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap scormpleted-wrap">
    <h1><?php esc_html_e( 'Scormpleted Settings', 'scormpleted' ); ?></h1>
    <hr class="wp-header-end">
    <div id="scormpleted-settings-notice" class="notice" style="display:none;"></div>

    <form id="scormpleted-settings-form">
        <table class="form-table">
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Default Width', 'scormpleted' ); ?></label></th>
                <td>
                    <input type="text" name="settings[width]" class="small-text" value="<?php echo esc_attr( $settings['width'] ); ?>" placeholder="100%">
                    <p class="description"><?php esc_html_e( 'Default iframe width for all content. e.g. 100% or 800px', 'scormpleted' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Default Height', 'scormpleted' ); ?></label></th>
                <td>
                    <input type="text" name="settings[height]" class="small-text" value="<?php echo esc_attr( $settings['height'] ); ?>" placeholder="600">
                    <p class="description"><?php esc_html_e( 'Default iframe height in pixels (without px). e.g. 600', 'scormpleted' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Default Completion Type', 'scormpleted' ); ?></label></th>
                <td>
                    <select name="settings[completion_type]">
                        <option value="hide_button"            <?php selected( $settings['completion_type'], 'hide_button' ); ?>><?php esc_html_e( 'Hide Button (show on completion)', 'scormpleted' ); ?></option>
                        <option value="hidden_until_complete"  <?php selected( $settings['completion_type'], 'hidden_until_complete' ); ?>><?php esc_html_e( 'Show Button on Completion', 'scormpleted' ); ?></option>
                        <option value="disable_until_complete" <?php selected( $settings['completion_type'], 'disable_until_complete' ); ?>><?php esc_html_e( 'Enable Button on Completion', 'scormpleted' ); ?></option>
                        <option value="completion_move_nextlevel" <?php selected( $settings['completion_type'], 'completion_move_nextlevel' ); ?>><?php esc_html_e( 'Auto-redirect on Completion', 'scormpleted' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Debug Mode', 'scormpleted' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="settings[debug]" value="1" <?php checked( $settings['debug'], '1' ); ?>>
                        <?php esc_html_e( 'Write debug log to wp-content/scormpleted-debug.log (disable when not troubleshooting — log auto-rotates at 1MB)', 'scormpleted' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'scormpleted' ); ?></button>
            <span id="scormpleted-settings-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
        </p>
    </form>

    <hr>
    <h2><?php esc_html_e( 'Reset Learner Data', 'scormpleted' ); ?></h2>
    <p><?php esc_html_e( 'Use this to reset a learner\'s SCORM progress for testing.', 'scormpleted' ); ?></p>
    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'User ID', 'scormpleted' ); ?></th>
            <td><input type="number" id="reset-user-id" class="small-text" placeholder="e.g. 5"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Content ID', 'scormpleted' ); ?></th>
            <td><input type="number" id="reset-content-id" class="small-text" placeholder="e.g. 42"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Lesson/Post ID', 'scormpleted' ); ?></th>
            <td><input type="number" id="reset-post-id" class="small-text" placeholder="e.g. 100"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Course ID', 'scormpleted' ); ?></th>
            <td><input type="number" id="reset-course-id" class="small-text" placeholder="e.g. 90"></td>
        </tr>
    </table>
    <p>
        <button id="scormpleted-reset-btn" class="button button-secondary"><?php esc_html_e( 'Reset Learner Progress', 'scormpleted' ); ?></button>
    </p>
    <div id="scormpleted-reset-notice" class="notice" style="display:none;"></div>
</div>
