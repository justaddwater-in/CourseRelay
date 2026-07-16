<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap courserelay-wrap">
    <h1><?php esc_html_e( 'Add SCORM Content', 'courserelay' ); ?></h1>
    <hr class="wp-header-end">

    <div id="courserelay-upload-notice" class="notice" style="display:none;"></div>

    <form id="courserelay-upload-form" enctype="multipart/form-data">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="scorm-file"><?php esc_html_e( 'SCORM Package (.zip)', 'courserelay' ); ?> <span style="color:red;">*</span></label>
                </th>
                <td>
                    <input type="file" id="scorm-file" name="scorm_file" accept=".zip" required>
                    <p class="description"><?php esc_html_e( 'Upload a SCORM 1.2 or SCORM 2004 zip package. The imsmanifest.xml must be present at the root or one folder deep.', 'courserelay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="content-title"><?php esc_html_e( 'Title', 'courserelay' ); ?></label>
                </th>
                <td>
                    <input type="text" id="content-title" name="content_title" class="regular-text" placeholder="<?php esc_attr_e( 'Auto-detected from manifest if left blank', 'courserelay' ); ?>">
                </td>
            </tr>

            <tr><td colspan="2"><h2 class="title"><?php esc_html_e( 'Completion Settings', 'courserelay' ); ?></h2></td></tr>

            <tr>
                <th scope="row"><?php esc_html_e( 'Completion Tracking', 'courserelay' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="completion_tracking" value="1" checked>
                        <?php esc_html_e( 'Automatically mark LearnDash lesson complete when SCORM reports completion', 'courserelay' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="passing-percentage"><?php esc_html_e( 'Passing Percentage', 'courserelay' ); ?></label>
                </th>
                <td>
                    <input type="number" id="passing-percentage" name="mastery_score" class="small-text" min="0" max="100" value="80"> %
                    <p class="description"><?php esc_html_e( 'Score required to pass. Overrides the value in imsmanifest.xml.', 'courserelay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Completion Type', 'courserelay' ); ?></th>
                <td>
                    <select name="completion_type">
                        <option value="hide_button"><?php esc_html_e( 'Hide Button (show on completion)', 'courserelay' ); ?></option>
                        <option value="hidden_until_complete"><?php esc_html_e( 'Show Button on Completion', 'courserelay' ); ?></option>
                        <option value="disable_until_complete"><?php esc_html_e( 'Enable Button on Completion', 'courserelay' ); ?></option>
                        <option value="completion_move_nextlevel"><?php esc_html_e( 'Auto-redirect on Completion', 'courserelay' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr><td colspan="2"><h2 class="title"><?php esc_html_e( 'Display Settings', 'courserelay' ); ?></h2></td></tr>

            <tr>
                <th scope="row"><label for="content-width"><?php esc_html_e( 'Width', 'courserelay' ); ?></label></th>
                <td>
                    <input type="text" id="content-width" name="width" class="small-text" value="<?php echo esc_attr( $global['width'] ); ?>" placeholder="100%">
                    <p class="description"><?php esc_html_e( 'e.g. 100% or 800px', 'courserelay' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="content-height"><?php esc_html_e( 'Height', 'courserelay' ); ?></label></th>
                <td>
                    <input type="text" id="content-height" name="height" class="small-text" value="<?php echo esc_attr( $global['height'] ); ?>px" placeholder="600px">
                    <p class="description"><?php esc_html_e( 'e.g. 600px or 80vh', 'courserelay' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" id="courserelay-upload-btn" class="button button-primary">
                <?php esc_html_e( 'Upload &amp; Process', 'courserelay' ); ?>
            </button>
            <span id="courserelay-upload-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
        </p>
    </form>
</div>
