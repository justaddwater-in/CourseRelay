<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap scormpleted-wrap">
    <h1><?php esc_html_e( 'Add SCORM Content', 'scormpleted' ); ?></h1>
    <hr class="wp-header-end">

    <div id="scormpleted-upload-notice" class="notice" style="display:none;"></div>

    <form id="scormpleted-upload-form" enctype="multipart/form-data">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="scorm-file"><?php esc_html_e( 'SCORM Package (.zip)', 'scormpleted' ); ?> <span style="color:red;">*</span></label>
                </th>
                <td>
                    <input type="file" id="scorm-file" name="scorm_file" accept=".zip" required>
                    <p class="description"><?php esc_html_e( 'Upload a SCORM 1.2 or SCORM 2004 zip package. The imsmanifest.xml must be present at the root or one folder deep.', 'scormpleted' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="content-title"><?php esc_html_e( 'Title', 'scormpleted' ); ?></label>
                </th>
                <td>
                    <input type="text" id="content-title" name="content_title" class="regular-text" placeholder="<?php esc_attr_e( 'Auto-detected from manifest if left blank', 'scormpleted' ); ?>">
                </td>
            </tr>

            <tr><td colspan="2"><h2 class="title"><?php esc_html_e( 'Completion Settings', 'scormpleted' ); ?></h2></td></tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Completion Tracking', 'scormpleted' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="completion_tracking" value="1" checked>
                        <?php esc_html_e( 'Automatically mark LearnDash lesson complete when SCORM reports completion', 'scormpleted' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="passing-percentage"><?php esc_html_e( 'Passing Percentage', 'scormpleted' ); ?></label>
                </th>
                <td>
                    <input type="number" id="passing-percentage" name="mastery_score" class="small-text" min="0" max="100" value="80"> %
                    <p class="description"><?php esc_html_e( 'Score required to pass. Overrides the value in imsmanifest.xml.', 'scormpleted' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Completion Type', 'scormpleted' ); ?></th>
                <td>
                    <select name="completion_type">
                        <option value="hide_button"><?php esc_html_e( 'Hide Button (show on completion)', 'scormpleted' ); ?></option>
                        <option value="hidden_until_complete"><?php esc_html_e( 'Show Button on Completion', 'scormpleted' ); ?></option>
                        <option value="disable_until_complete"><?php esc_html_e( 'Enable Button on Completion', 'scormpleted' ); ?></option>
                        <option value="completion_move_nextlevel"><?php esc_html_e( 'Auto-redirect on Completion', 'scormpleted' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr><td colspan="2"><h2 class="title"><?php esc_html_e( 'Display Settings', 'scormpleted' ); ?></h2></td></tr>

            <tr>
                <th scope="row"><label for="content-width"><?php esc_html_e( 'Width', 'scormpleted' ); ?></label></th>
                <td>
                    <input type="text" id="content-width" name="width" class="small-text" value="<?php echo esc_attr( $global['width'] ); ?>" placeholder="100%">
                    <p class="description"><?php esc_html_e( 'e.g. 100% or 800px', 'scormpleted' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="content-height"><?php esc_html_e( 'Height', 'scormpleted' ); ?></label></th>
                <td>
                    <input type="text" id="content-height" name="height" class="small-text" value="<?php echo esc_attr( $global['height'] ); ?>px" placeholder="600px">
                    <p class="description"><?php esc_html_e( 'e.g. 600px or 80vh', 'scormpleted' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" id="scormpleted-upload-btn" class="button button-primary">
                <?php esc_html_e( 'Upload &amp; Process', 'scormpleted' ); ?>
            </button>
            <span id="scormpleted-upload-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
        </p>
    </form>
</div>
