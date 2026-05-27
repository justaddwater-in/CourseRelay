<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap scormpleted-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'SCORM Content', 'scormpleted' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=scormpleted-add' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Add New', 'scormpleted' ); ?>
    </a>
    <hr class="wp-header-end">

    <div id="scormpleted-notice" class="notice" style="display:none;"></div>

    <?php if ( empty( $pkgs ) ) : ?>
        <div class="scormpleted-empty">
            <p><?php esc_html_e( 'No SCORM content uploaded yet.', 'scormpleted' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=scormpleted-add' ) ); ?>"><?php esc_html_e( 'Upload your first package →', 'scormpleted' ); ?></a>
            </p>
        </div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Title', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Version', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Mastery Score', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Tracking', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Content ID', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Date', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'scormpleted' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $pkgs as $scormpleted_pkg ) : ?>
            <tr id="scormpleted-row-<?php echo esc_attr( $scormpleted_pkg->post_id ); ?>">
                <td>
                    <strong><?php echo esc_html( $scormpleted_pkg->post_title ); ?></strong>
                    <div class="row-actions">
                        <span class="edit">
                            <a href="#" class="scormpleted-edit-btn" data-id="<?php echo esc_attr( $scormpleted_pkg->post_id ); ?>"><?php esc_html_e( 'Edit', 'scormpleted' ); ?></a> |
                        </span>
                        <span class="trash">
                            <a href="#" class="scormpleted-delete-btn" data-id="<?php echo esc_attr( $scormpleted_pkg->post_id ); ?>" style="color:#a00;"><?php esc_html_e( 'Delete', 'scormpleted' ); ?></a>
                        </span>
                    </div>
                </td>
                <td>
                    <?php echo $scormpleted_pkg->version === 'scorm2004' ? 'SCORM 2004' : 'SCORM 1.2'; ?>
                </td>
                <td><?php echo esc_html( $scormpleted_pkg->mastery_score ); ?>%</td>
                <td><?php echo $scormpleted_pkg->completion_tracking ? '✅' : '❌'; ?></td>
                <td><code><?php echo esc_html( $scormpleted_pkg->post_id ); ?></code></td>
                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $scormpleted_pkg->post_date ) ); ?></td>
                <td>
                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'scormpleted_launch', 'content_id' => $scormpleted_pkg->post_id ), admin_url( 'admin-ajax.php' ) ) ); ?>" target="_blank" class="button button-small">
                        <?php esc_html_e( 'Preview', 'scormpleted' ); ?>
                    </a>
                </td>
            </tr>
            <!-- Inline edit row (hidden by default) -->
            <tr id="scormpleted-edit-<?php echo esc_attr( $scormpleted_pkg->post_id ); ?>" style="display:none;background:#f9f9f9;">
                <td colspan="7">
                    <div style="padding:16px;">
                        <h3 style="margin-top:0;"><?php esc_html_e( 'Edit Content', 'scormpleted' ); ?></h3>
                        <table class="form-table" style="width:auto;">
                            <tr>
                                <th><?php esc_html_e( 'Title', 'scormpleted' ); ?></th>
                                <td><input type="text" class="regular-text" name="content_title" value="<?php echo esc_attr( $scormpleted_pkg->post_title ); ?>"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Mastery Score (%)', 'scormpleted' ); ?></th>
                                <td><input type="number" class="small-text" name="mastery_score" min="0" max="100" value="<?php echo esc_attr( $scormpleted_pkg->mastery_score ); ?>"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Completion Tracking', 'scormpleted' ); ?></th>
                                <td><input type="checkbox" name="completion_tracking" value="1" <?php checked( $scormpleted_pkg->completion_tracking, 1 ); ?>> <?php esc_html_e( 'Enable', 'scormpleted' ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Completion Type', 'scormpleted' ); ?></th>
                                <td>
                                    <select name="completion_type">
                                        <option value="hide_button" <?php selected( $scormpleted_pkg->completion_type, 'hide_button' ); ?>><?php esc_html_e( 'Hide Button (show on completion)', 'scormpleted' ); ?></option>
                                        <option value="hidden_until_complete" <?php selected( $scormpleted_pkg->completion_type, 'hidden_until_complete' ); ?>><?php esc_html_e( 'Show Button on Completion', 'scormpleted' ); ?></option>
                                        <option value="disable_until_complete" <?php selected( $scormpleted_pkg->completion_type, 'disable_until_complete' ); ?>><?php esc_html_e( 'Enable Button on Completion', 'scormpleted' ); ?></option>
                                        <option value="completion_move_nextlevel" <?php selected( $scormpleted_pkg->completion_type, 'completion_move_nextlevel' ); ?>><?php esc_html_e( 'Auto-redirect on Completion', 'scormpleted' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Width', 'scormpleted' ); ?></th>
                                <td><input type="text" class="small-text" name="width" value="<?php echo esc_attr( $scormpleted_pkg->width ); ?>" placeholder="100%"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Height', 'scormpleted' ); ?></th>
                                <td><input type="text" class="small-text" name="height" value="<?php echo esc_attr( $scormpleted_pkg->height ); ?>" placeholder="600px"></td>
                            </tr>
                        </table>
                        <p>
                            <button class="button button-primary scormpleted-save-content" data-id="<?php echo esc_attr( $scormpleted_pkg->post_id ); ?>"><?php esc_html_e( 'Save Changes', 'scormpleted' ); ?></button>
                            <button class="button scormpleted-cancel-edit" data-id="<?php echo esc_attr( $scormpleted_pkg->post_id ); ?>"><?php esc_html_e( 'Cancel', 'scormpleted' ); ?></button>
                        </p>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="color:#666;margin-top:8px;"><?php
    // translators: %d is the number of SCORM packages.
    echo esc_html( sprintf( _n( '%d package', '%d packages', $total, 'scormpleted' ), $total ) );
    ?></p>
    <?php endif; ?>
</div>
