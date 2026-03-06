<?php

/**
 * Handles the article withdrawal process.
 * 
 * @package SAD_Workflow_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAD_Withdrawal_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		
		// AJAX Handlers
		add_action( 'wp_ajax_sad_withdraw_cleanup_authors', array( $this, 'ajax_cleanup_authors' ) );
		add_action( 'wp_ajax_sad_withdraw_cleanup_files', array( $this, 'ajax_cleanup_files' ) );
		add_action( 'wp_ajax_sad_withdraw_finalize_status', array( $this, 'ajax_finalize_status' ) );
	}

	/**
	 * Add the withdrawal metabox.
	 */
	public function add_meta_box() {
		add_meta_box(
			'sad-withdrawal-metabox',
			__( 'Withdraw this Article', 'sad-workflow-manager' ),
			array( $this, 'render_meta_box' ),
			'scholarly_article',
			'side',
			'high'
		);
	}

	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_meta_box( $post ) {
		// Only show for certain statuses or roles if needed, but for now, show for all scholarly articles.
		?>
		<div class="sad-withdrawal-container">
			<p class="description">
				<?php _e( 'Withdrawal will delete unique authors, remove all associated files (S3 & Local), and set status to Rejected.', 'sad-workflow-manager' ); ?>
			</p>
			<button type="button" id="sad-withdraw-article-btn" class="button button-link-delete" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
				<?php _e( 'Withdraw this article', 'sad-workflow-manager' ); ?>
			</button>
		</div>
		
		<!-- Withdrawal Modal -->
		<div id="sad-withdrawal-modal" class="sad-withdraw-modal" style="display:none;">
			<div class="sad-withdraw-modal-content">
				<div class="sad-withdraw-modal-header">
					<h2><?php _e( 'Article Withdrawal', 'sad-workflow-manager' ); ?></h2>
					<span class="sad-withdraw-modal-close">&times;</span>
				</div>
				<div class="sad-withdraw-modal-body">
					<div id="sad-withdrawal-init">
						<p><strong><?php _e( 'WARNING: This action is irreversible.', 'sad-workflow-manager' ); ?></strong></p>
						<p><?php _e( 'This process will:', 'sad-workflow-manager' ); ?></p>
						<ul>
							<li><?php _e( 'Identify and delete authors only associated with this article.', 'sad-workflow-manager' ); ?></li>
							<li><?php _e( 'Permanently delete all uploaded files from S3 and local storage.', 'sad-workflow-manager' ); ?></li>
							<li><?php _e( 'Purge all associated CDN caches.', 'sad-workflow-manager' ); ?></li>
							<li><?php _e( 'Set the article status to Rejected.', 'sad-workflow-manager' ); ?></li>
						</ul>
						<p><?php _e( 'Please type <strong>WITHDRAW</strong> to confirm:', 'sad-workflow-manager' ); ?></p>
						<input type="text" id="sad-withdrawal-confirm-input" class="widefat" placeholder="WITHDRAW">
						<div class="sad-withdraw-modal-footer">
							<button type="button" id="sad-withdrawal-start-btn" class="button button-primary button-large" disabled>
								<?php _e( 'Confirm and Start Withdrawal', 'sad-workflow-manager' ); ?>
							</button>
						</div>
					</div>
					<div id="sad-withdrawal-progress" style="display:none;">
						<div class="sad-progress-steps">
							<div class="sad-progress-step" data-step="authors">
								<span class="sad-step-icon"></span>
								<span class="sad-step-label"><?php _e( 'Cleaning up Authors...', 'sad-workflow-manager' ); ?></span>
							</div>
							<div class="sad-progress-step" data-step="files">
								<span class="sad-step-icon"></span>
								<span class="sad-step-label"><?php _e( 'Removing Files & Cache...', 'sad-workflow-manager' ); ?></span>
							</div>
							<div class="sad-progress-step" data-step="status">
								<span class="sad-step-icon"></span>
								<span class="sad-step-label"><?php _e( 'Updating Article Status...', 'sad-workflow-manager' ); ?></span>
							</div>
						</div>
						<div id="sad-withdrawal-logs" class="sad-logs-container">
							<!-- Logs will appear here -->
						</div>
					</div>
					<div id="sad-withdrawal-complete" style="display:none;">
						<div class="sad-success-message">
							<span class="dashicons dashicons-yes-alt"></span>
							<p><?php _e( 'Article successfully withdrawn.', 'sad-workflow-manager' ); ?></p>
						</div>
						<button type="button" class="button button-primary sad-withdraw-modal-close-btn"><?php _e( 'Close and Reload', 'sad-workflow-manager' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Cleanup Authors.
	 */
	public function ajax_cleanup_authors() {
		check_ajax_referer( 'sad_withdraw_nonce', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'sad-workflow-manager' ) ) );
		}

		// if ( ! current_user_can( 'edit_post', $post_id ) ) {
		// 	wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sad-workflow-manager' ) ) );
		// }

		$authors = get_post_meta( $post_id, 'authors', true );
		if ( ! is_array( $authors ) ) {
			$authors = array();
		}

		// Also check the primary author (post_author)
		$post = get_post( $post_id );
		if ( $post && $post->post_author ) {
			// Add primary author to the list if not already there
			$primary_exists = false;
			foreach ( $authors as $author ) {
				if ( isset( $author['user_id'] ) && (int)$author['user_id'] === (int)$post->post_author ) {
					$primary_exists = true;
					break;
				}
			}
			if ( ! $primary_exists ) {
				$authors[] = array( 'user_id' => $post->post_author );
			}
		}

		$deleted_count = 0;
		$kept_count = 0;
		$logs = array();

		require_once( ABSPATH . 'wp-admin/includes/user.php' );

		foreach ( $authors as $author ) {
			$user_id = isset( $author['user_id'] ) ? absint( $author['user_id'] ) : 0;
			if ( ! $user_id ) continue;

			$user = get_userdata( $user_id );
			if ( ! $user ) continue;

			// Check if user is only associated with this article.
			// Use the SAD_Article_Access class if available.
			if ( class_exists( 'SAD_Article_Access' ) ) {
				$user_articles = SAD_Article_Access::get_user_articles( $user_id );
			} else {
				// Fallback search
				global $wpdb;
				$user_articles = $wpdb->get_col( $wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'authors' AND meta_value LIKE %s",
					'%"user_id";i:' . $user_id . ';%'
				) );
				// Also check primary author
				$primary_authored = get_posts( array(
					'post_type' => 'scholarly_article',
					'author' => $user_id,
					'fields' => 'ids',
					'posts_per_page' => -1
				) );
				$user_articles = array_unique( array_merge( $user_articles, $primary_authored ) );
			}

			if ( count( $user_articles ) <= 1 && ( empty( $user_articles ) || (int)$user_articles[0] === $post_id ) ) {
				// Check if they are just an author (not admin/editor)
				if ( in_array( 'sad_author', $user->roles ) && ! in_array( 'administrator', $user->roles ) && ! in_array( 'editor', $user->roles ) ) {
					wp_delete_user( $user_id );
					$deleted_count++;
					$logs[] = sprintf( __( 'Deleted author: %s (%s)', 'sad-workflow-manager' ), $user->display_name, $user->user_email );
				} else {
					$kept_count++;
					$logs[] = sprintf( __( 'Kept user (special role): %s', 'sad-workflow-manager' ), $user->display_name );
				}
			} else {
				$kept_count++;
				$logs[] = sprintf( __( 'Kept author (associated with other papers): %s', 'sad-workflow-manager' ), $user->display_name );
			}
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Author cleanup complete. Deleted: %d, Kept: %d.', 'sad-workflow-manager' ), $deleted_count, $kept_count ),
			'logs' => $logs
		) );
	}

	/**
	 * AJAX: Cleanup Files.
	 */
	public function ajax_cleanup_files() {
		check_ajax_referer( 'sad_withdraw_nonce', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'sad-workflow-manager' ) ) );
		}

		// if ( ! current_user_can( 'edit_post', $post_id ) ) {
		// 	wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sad-workflow-manager' ) ) );
		// }

		$logs = array();

		// 1. file-sync cleanup
		if ( class_exists( 'GJDL_File_Manager' ) ) {
			$file_manager = new GJDL_File_Manager();
			$post_type_fields = get_option( 'gjdl_posttype_fields', array() );
			
			if ( isset( $post_type_fields['scholarly_article']['fields'] ) ) {
				foreach ( $post_type_fields['scholarly_article']['fields'] as $field ) {
					$meta_key = $field['meta_key'];
					$url = get_post_meta( $post_id, $meta_key, true );
					
					if ( $url ) {
						$result = $file_manager->delete_external_file( $url );
						if ( is_wp_error( $result ) ) {
							$logs[] = sprintf( __( 'File-sync error (%s): %s', 'sad-workflow-manager' ), $meta_key, $result->get_error_message() );
						} else {
							$logs[] = sprintf( __( 'Deleted S3 file from field: %s', 'sad-workflow-manager' ), $meta_key );
							// Purge Cloudflare cache
							$file_manager->purge_cloudflare_cache( $url );
							$logs[] = sprintf( __( 'Purged Cloudflare cache: %s', 'sad-workflow-manager' ), $url );
						}
						// Also delete the internal metadata
						$file_manager->delete_file_metadata( $post_id, $meta_key );
					}
				}
			}
		}

		// 2. author-dashboard files cleanup (Meta + DB)
		$art_files = get_post_meta( $post_id, '_sad_article_files', true );
		if ( is_array( $art_files ) ) {
			foreach ( $art_files as $field_key => $file_data ) {
				// Some entries are indexed by field key, some are numerically indexed arrays
				$data = ( is_array( $file_data ) && isset( $file_data['s3_key'] ) ) ? $file_data : ( is_array( $art_files ) ? $file_data : null );
				
				if ( $data && ! empty( $data['s3_key'] ) ) {
					if ( class_exists( 'GJDL_S3_Client' ) ) {
						$s3_client = GJDL_S3_Client::get_instance();
						// Use the stored bucket if available, otherwise default to private
						$bucket = ! empty( $data['s3_bucket'] ) ? $data['s3_bucket'] : 'private';
						$s3_client->delete_object( $bucket, $data['s3_key'] );
						$logs[] = sprintf( __( 'Deleted author-dashboard S3 file from bucket "%s": %s', 'sad-workflow-manager' ), $bucket, $data['s3_key'] );

						// Purge Cloudflare if it's a public/image bucket
						if ( in_array( $bucket, array( 'public', 'image' ) ) ) {
							$public_url = home_url( ltrim( $data['s3_key'], '/' ) );
							$file_manager = new GJDL_File_Manager();
							$file_manager->purge_cloudflare_cache( $public_url );
							$logs[] = sprintf( __( 'Purged Cloudflare cache for: %s', 'sad-workflow-manager' ), $public_url );
						}
					}
				}
			}
			delete_post_meta( $post_id, '_sad_article_files' );
		}

		// Cleanup author-dashboard DB table
		global $wpdb;
		$table_name = $wpdb->prefix . 'sad_submission_files';
		// Check if table exists first to avoid errors
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
			$wpdb->delete( $table_name, array( 'submission_id' => $post_id ) );
			$logs[] = __( 'Deleted records from sad_submission_files table.', 'sad-workflow-manager' );
		}

		// Try to delete by file_id prefix if available (author-dashboard + others)
		$file_id_meta = get_post_meta( $post_id, 'file_id', true );
		if ( $file_id_meta && class_exists( 'GJDL_Loader' ) ) {
			$s3_loader_component = GJDL_Loader::get_instance()->get_component( 's3_client' );
			if ( $s3_loader_component ) {
				foreach ( array( 'private', 'public', 'image' ) as $bucket_type ) {
					$aw_client = $s3_loader_component->get_client( $bucket_type );
					$aw_config = GJDL_Settings::get_bucket_config( $bucket_type );
					if ( $aw_client && ! empty( $aw_config['bucket'] ) ) {
						$aw_prefix = sprintf( '%s_%d/', strtolower( $file_id_meta ), $post_id );
						try {
							$aw_objects = $aw_client->listObjectsV2( array(
								'Bucket' => $aw_config['bucket'],
								'Prefix' => $aw_prefix
							) );
							if ( ! empty( $aw_objects['Contents'] ) ) {
								foreach ( $aw_objects['Contents'] as $aw_obj ) {
									$aw_client->deleteObject( array(
										'Bucket' => $aw_config['bucket'],
										'Key'    => $aw_obj['Key']
									) );
									$logs[] = sprintf( __( 'Deleted file by prefix from bucket "%s": %s', 'sad-workflow-manager' ), $bucket_type, $aw_obj['Key'] );

									// Purge Cloudflare if it's a public/image bucket
									if ( in_array( $bucket_type, array( 'public', 'image' ) ) ) {
										$public_url = home_url( ltrim( $aw_obj['Key'], '/' ) );
										$file_manager = new GJDL_File_Manager();
										$file_manager->purge_cloudflare_cache( $public_url );
										$logs[] = sprintf( __( 'Purged Cloudflare cache by prefix: %s', 'sad-workflow-manager' ), $public_url );
									}
								}
							}
						} catch ( \Exception $e ) {
							// Prefix cleanup error is non-fatal
							$logs[] = sprintf( __( 'Prefix cleanup error for bucket "%s" (skipping): %s', 'sad-workflow-manager' ), $bucket_type, $e->getMessage() );
						}
					}
				}
			}
		}

		// 3. WP Attachments cleanup
		$attachments = get_posts( array(
			'post_type' => 'attachment',
			'posts_per_page' => -1,
			'post_parent' => $post_id,
			'fields' => 'ids'
		) );

		foreach ( $attachments as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
			$logs[] = sprintf( __( 'Deleted WP attachment ID: %d', 'sad-workflow-manager' ), $attachment_id );
		}

		wp_send_json_success( array(
			'message' => __( 'Files and cache cleaned up successfully.', 'sad-workflow-manager' ),
			'logs' => $logs
		) );
	}

	/**
	 * AJAX: Finalize Status.
	 */
	public function ajax_finalize_status() {
		check_ajax_referer( 'sad_withdraw_nonce', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'sad-workflow-manager' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sad-workflow-manager' ) ) );
		}

		// Force status to rejected
		$result = wp_update_post( array(
			'ID' => $post_id,
			'post_status' => 'rejected'
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Clear any other specific meta if needed
		update_post_meta( $post_id, '_sad_withdrawal_date', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_sad_withdrawn_by', get_current_user_id() );

		wp_send_json_success( array(
			'message' => __( 'Article status updated to Rejected.', 'sad-workflow-manager' ),
			'logs' => array( __( 'Status changed to "rejected".', 'sad-workflow-manager' ) )
		) );
	}
}
