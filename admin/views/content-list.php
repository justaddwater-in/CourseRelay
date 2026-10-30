<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;}
// phpcs:disable WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap courserelay-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'SCORM Content', 'courserelay' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=courserelay-add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'courserelay' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['courserelay_upload'] ) && 'success' === sanitize_key( wp_unslash( $_GET['courserelay_upload'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'SCORM package uploaded successfully.', 'courserelay' ); ?></p></div>
	<?php endif; ?>

	<div id="courserelay-notice" class="notice" style="display:none;"></div>

	<?php if ( empty( $pkgs ) ) : ?>
		<div class="courserelay-empty">
			<p><?php esc_html_e( 'No SCORM content uploaded yet.', 'courserelay' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=courserelay-add' ) ); ?>"><?php esc_html_e( 'Upload your first package →', 'courserelay' ); ?></a>
			</p>
		</div>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Title', 'courserelay' ); ?></th>
				<th><?php esc_html_e( 'Version', 'courserelay' ); ?></th>
				<th><?php esc_html_e( 'Mastery Score', 'courserelay' ); ?></th>
				<th><?php esc_html_e( 'Tracking', 'courserelay' ); ?></th>
				<th><?php esc_html_e( 'Content ID', 'courserelay' ); ?></th>
				<th><?php esc_html_e( 'Date', 'courserelay' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'courserelay' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $pkgs as $courserelay_pkg ) : ?>
			<tr id="courserelay-row-<?php echo esc_attr( $courserelay_pkg->post_id ); ?>">
				<td>
					<strong><?php echo esc_html( $courserelay_pkg->post_title ); ?></strong>
					<div class="row-actions">
						<span class="edit">
							<a href="#" class="courserelay-edit-btn" data-id="<?php echo esc_attr( $courserelay_pkg->post_id ); ?>"><?php esc_html_e( 'Edit', 'courserelay' ); ?></a> |
						</span>
						<span class="trash">
							<a href="#" class="courserelay-delete-btn" data-id="<?php echo esc_attr( $courserelay_pkg->post_id ); ?>" style="color:#a00;"><?php esc_html_e( 'Delete', 'courserelay' ); ?></a>
						</span>
					</div>
				</td>
				<td>
					<?php echo $courserelay_pkg->version === 'scorm2004' ? 'SCORM 2004' : 'SCORM 1.2'; ?>
				</td>
				<td><?php echo esc_html( $courserelay_pkg->mastery_score ); ?>%</td>
				<td><?php echo $courserelay_pkg->completion_tracking ? '✅' : '❌'; ?></td>
				<td><code><?php echo esc_html( $courserelay_pkg->post_id ); ?></code></td>
				<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $courserelay_pkg->post_date ) ); ?></td>
				<td>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'action'     => 'courserelay_launch',
								'content_id' => $courserelay_pkg->post_id,
								'_wpnonce'   => wp_create_nonce( 'courserelay_launch' ),
							),
							admin_url( 'admin-ajax.php' )
						)
					);
					?>
								" target="_blank" class="button button-small">
						<?php esc_html_e( 'Preview', 'courserelay' ); ?>
					</a>
				</td>
			</tr>
			<!-- Inline edit row (hidden by default) -->
			<tr id="courserelay-edit-<?php echo esc_attr( $courserelay_pkg->post_id ); ?>" style="display:none;background:#f9f9f9;">
				<td colspan="7">
					<div style="padding:16px;">
						<h3 style="margin-top:0;"><?php esc_html_e( 'Edit Content', 'courserelay' ); ?></h3>
						<table class="form-table" style="width:auto;">
							<tr>
								<th><?php esc_html_e( 'Title', 'courserelay' ); ?></th>
								<td><input type="text" class="regular-text" name="content_title" value="<?php echo esc_attr( $courserelay_pkg->post_title ); ?>"></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Mastery Score (%)', 'courserelay' ); ?></th>
								<td><input type="number" class="small-text" name="mastery_score" min="0" max="100" value="<?php echo esc_attr( $courserelay_pkg->mastery_score ); ?>"></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Completion Tracking', 'courserelay' ); ?></th>
								<td><input type="checkbox" name="completion_tracking" value="1" <?php checked( $courserelay_pkg->completion_tracking, 1 ); ?>> <?php esc_html_e( 'Enable', 'courserelay' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Completion Type', 'courserelay' ); ?></th>
								<td>
									<select name="completion_type">
										<option value="hide_button" <?php selected( $courserelay_pkg->completion_type, 'hide_button' ); ?>><?php esc_html_e( 'Hide Button (show on completion)', 'courserelay' ); ?></option>
										<option value="hidden_until_complete" <?php selected( $courserelay_pkg->completion_type, 'hidden_until_complete' ); ?>><?php esc_html_e( 'Show Button on Completion', 'courserelay' ); ?></option>
										<option value="disable_until_complete" <?php selected( $courserelay_pkg->completion_type, 'disable_until_complete' ); ?>><?php esc_html_e( 'Enable Button on Completion', 'courserelay' ); ?></option>
										<option value="completion_move_nextlevel" <?php selected( $courserelay_pkg->completion_type, 'completion_move_nextlevel' ); ?>><?php esc_html_e( 'Auto-redirect on Completion', 'courserelay' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Width', 'courserelay' ); ?></th>
								<td><input type="text" class="small-text" name="width" value="<?php echo esc_attr( $courserelay_pkg->width ); ?>" placeholder="100%"></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Height', 'courserelay' ); ?></th>
								<td><input type="text" class="small-text" name="height" value="<?php echo esc_attr( $courserelay_pkg->height ); ?>" placeholder="600px"></td>
							</tr>
						</table>
						<p>
							<button class="button button-primary courserelay-save-content" data-id="<?php echo esc_attr( $courserelay_pkg->post_id ); ?>"><?php esc_html_e( 'Save Changes', 'courserelay' ); ?></button>
							<button class="button courserelay-cancel-edit" data-id="<?php echo esc_attr( $courserelay_pkg->post_id ); ?>"><?php esc_html_e( 'Cancel', 'courserelay' ); ?></button>
						</p>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p style="color:#666;margin-top:8px;">
		<?php
		// translators: %d is the number of SCORM packages.
		echo esc_html( sprintf( _n( '%d package', '%d packages', $total, 'courserelay' ), $total ) );
		?>
	</p>
	<?php endif; ?>
</div>

<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>
