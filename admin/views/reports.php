<?php
/**
 * Reports view — all data is prepared by Scormpleted_Admin::page_reports()
 * and passed as $scormpleted_report_data.
 *
 * @var array $scormpleted_report_data {
 *   int    $per_page
 *   int    $paged
 *   int    $filter_uid
 *   int    $filter_cid
 *   array  $completions
 *   int    $total
 * }
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$scormpleted_completions = $scormpleted_report_data['completions'];
$scormpleted_total       = $scormpleted_report_data['total'];
$scormpleted_filter_uid  = $scormpleted_report_data['filter_uid'];
$scormpleted_filter_cid  = $scormpleted_report_data['filter_cid'];
?>
<div class="wrap scormpleted-wrap">
    <h1><?php esc_html_e( 'Completion Reports', 'scormpleted' ); ?></h1>
    <hr class="wp-header-end">

    <form method="get" style="margin-bottom:16px;">
        <input type="hidden" name="page" value="scormpleted-reports">
        <label><?php esc_html_e( 'User ID:', 'scormpleted' ); ?>
            <input type="number" name="user_id" value="<?php echo esc_attr( $scormpleted_filter_uid ); ?>" class="small-text">
        </label>
        &nbsp;
        <label><?php esc_html_e( 'Content ID:', 'scormpleted' ); ?>
            <input type="number" name="content_id" value="<?php echo esc_attr( $scormpleted_filter_cid ); ?>" class="small-text">
        </label>
        &nbsp;
        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'scormpleted' ); ?></button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=scormpleted-reports' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'scormpleted' ); ?></a>
    </form>

    <?php if ( empty( $scormpleted_completions ) ) : ?>
        <p><?php esc_html_e( 'No completion records found.', 'scormpleted' ); ?></p>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'User', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Content', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Status', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Score', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Lesson', 'scormpleted' ); ?></th>
                <th><?php esc_html_e( 'Completed', 'scormpleted' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $scormpleted_completions as $scormpleted_row ) : ?>
            <?php
            $scormpleted_status_colors = array(
                'passed'    => '#008000',
                'completed' => '#0073aa',
                'failed'    => '#a00',
            );
            $scormpleted_color = isset( $scormpleted_status_colors[ $scormpleted_row->status ] )
                ? $scormpleted_status_colors[ $scormpleted_row->status ] : '#666';
            ?>
            <tr>
                <td>
                    <?php echo esc_html( $scormpleted_row->user_email ); ?>
                    <small style="display:block;color:#666;">(ID: <?php echo esc_html( $scormpleted_row->user_id ); ?>)</small>
                </td>
                <td>
                    <?php echo esc_html( $scormpleted_row->content_title ?: '—' ); ?>
                    <small style="display:block;color:#666;">(ID: <?php echo esc_html( $scormpleted_row->content_id ); ?>)</small>
                </td>
                <td>
                    <span style="color:<?php echo esc_attr( $scormpleted_color ); ?>;font-weight:600;">
                        <?php echo esc_html( ucfirst( $scormpleted_row->status ) ); ?>
                    </span>
                </td>
                <td><?php echo $scormpleted_row->score !== null ? esc_html( number_format( (float) $scormpleted_row->score, 1 ) ) . '%' : '—'; ?></td>
                <td>
                    <?php if ( $scormpleted_row->post_id ) : ?>
                        <a href="<?php echo esc_url( (string) get_edit_post_link( $scormpleted_row->post_id ) ); ?>">
                            <?php echo esc_html( get_the_title( $scormpleted_row->post_id ) ); ?>
                        </a>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $scormpleted_row->completed_at ) ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="color:#666;">
        <?php
        // translators: %d is the number of completion records found.
        echo esc_html( sprintf( __( '%d completion record(s) found.', 'scormpleted' ), $scormpleted_total ) );
        ?>
    </p>
    <?php endif; ?>
</div>
