<?php
/**
 * Reports view — all data is prepared by CourseRelay_Admin::page_reports()
 * and passed as $courserelay_report_data.
 *
 * @var array $courserelay_report_data {
 *   int    $per_page
 *   int    $paged
 *   int    $filter_uid
 *   int    $filter_cid
 *   array  $completions
 *   int    $total
 * }
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$courserelay_completions = $courserelay_report_data['completions'];
$courserelay_total       = $courserelay_report_data['total'];
$courserelay_filter_uid  = $courserelay_report_data['filter_uid'];
$courserelay_filter_cid  = $courserelay_report_data['filter_cid'];
?>
<div class="wrap courserelay-wrap">
    <h1><?php esc_html_e( 'Completion Reports', 'courserelay' ); ?></h1>
    <hr class="wp-header-end">

    <form method="get" style="margin-bottom:16px;">
        <input type="hidden" name="page" value="courserelay-reports">
        <label><?php esc_html_e( 'User ID:', 'courserelay' ); ?>
            <input type="number" name="user_id" value="<?php echo esc_attr( $courserelay_filter_uid ); ?>" class="small-text">
        </label>
        &nbsp;
        <label><?php esc_html_e( 'Content ID:', 'courserelay' ); ?>
            <input type="number" name="content_id" value="<?php echo esc_attr( $courserelay_filter_cid ); ?>" class="small-text">
        </label>
        &nbsp;
        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'courserelay' ); ?></button>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=courserelay-reports' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'courserelay' ); ?></a>
        &nbsp;
        <?php
        $courserelay_export_args = array( 'action' => 'courserelay_export_report' );
        if ( $courserelay_filter_uid ) $courserelay_export_args['user_id']    = $courserelay_filter_uid;
        if ( $courserelay_filter_cid ) $courserelay_export_args['content_id'] = $courserelay_filter_cid;
        $courserelay_export_url = wp_nonce_url(
            add_query_arg( $courserelay_export_args, admin_url( 'admin-post.php' ) ),
            'courserelay_export_report'
        );
        ?>
        <a href="<?php echo esc_url( $courserelay_export_url ); ?>" class="button button-secondary">
            <?php esc_html_e( 'Export CSV', 'courserelay' ); ?>
        </a>
    </form>

    <?php if ( empty( $courserelay_completions ) ) : ?>
        <p><?php esc_html_e( 'No completion records found.', 'courserelay' ); ?></p>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'User', 'courserelay' ); ?></th>
                <th><?php esc_html_e( 'Content', 'courserelay' ); ?></th>
                <th><?php esc_html_e( 'Status', 'courserelay' ); ?></th>
                <th><?php esc_html_e( 'Score', 'courserelay' ); ?></th>
                <th><?php esc_html_e( 'Lesson', 'courserelay' ); ?></th>
                <th><?php esc_html_e( 'Completed', 'courserelay' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $courserelay_completions as $courserelay_row ) : ?>
            <?php
            $courserelay_status_colors = array(
                'passed'    => '#008000',
                'completed' => '#0073aa',
                'failed'    => '#a00',
            );
            $courserelay_color = isset( $courserelay_status_colors[ $courserelay_row->status ] )
                ? $courserelay_status_colors[ $courserelay_row->status ] : '#666';
            ?>
            <tr>
                <td>
                    <?php echo esc_html( $courserelay_row->user_email ); ?>
                    <small style="display:block;color:#666;">(ID: <?php echo esc_html( $courserelay_row->user_id ); ?>)</small>
                </td>
                <td>
                    <?php echo esc_html( $courserelay_row->content_title ?: '—' ); ?>
                    <small style="display:block;color:#666;">(ID: <?php echo esc_html( $courserelay_row->content_id ); ?>)</small>
                </td>
                <td>
                    <span style="color:<?php echo esc_attr( $courserelay_color ); ?>;font-weight:600;">
                        <?php echo esc_html( ucfirst( $courserelay_row->status ) ); ?>
                    </span>
                </td>
                <td><?php echo $courserelay_row->score !== null ? esc_html( number_format( (float) $courserelay_row->score, 1 ) ) . '%' : '—'; ?></td>
                <td>
                    <?php if ( $courserelay_row->post_id ) : ?>
                        <a href="<?php echo esc_url( (string) get_edit_post_link( $courserelay_row->post_id ) ); ?>">
                            <?php echo esc_html( get_the_title( $courserelay_row->post_id ) ); ?>
                        </a>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $courserelay_row->completed_at ) ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="color:#666;">
        <?php
        // translators: %d is the number of completion records found.
        echo esc_html( sprintf( __( '%d completion record(s) found.', 'courserelay' ), $courserelay_total ) );
        ?>
    </p>
    <?php
    $courserelay_total_pages = (int) ceil( $courserelay_total / $courserelay_report_data['per_page'] );
    if ( $courserelay_total_pages > 1 ) :
        $courserelay_base_args = array( 'page' => 'courserelay-reports' );
        if ( $courserelay_filter_uid ) $courserelay_base_args['user_id']    = $courserelay_filter_uid;
        if ( $courserelay_filter_cid ) $courserelay_base_args['content_id'] = $courserelay_filter_cid;
        ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo wp_kses_post( paginate_links( array(
                    'base'      => add_query_arg( array_merge( $courserelay_base_args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ),
                    'format'    => '',
                    'prev_text' => __( '&laquo; Previous', 'courserelay' ),
                    'next_text' => __( 'Next &raquo;', 'courserelay' ),
                    'total'     => $courserelay_total_pages,
                    'current'   => $courserelay_report_data['paged'],
                ) ) );
                ?>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
