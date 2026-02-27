<?php

class SAD_Webhook_Dispatcher {

    private $webhook_url = 'https://n80n.softinator.org/webhook-test/e4653eb5-3d4a-48a8-9db8-5681a6988dd5';

    public function add_meta_box() {
        add_meta_box(
            'sad_webhook_dispatcher',
            __( 'Webhook Dispatcher', 'sad-workflow-manager' ),
            array( $this, 'render_meta_box' ),
            'scholarly_article',
            'side',
            'default'
        );
    }

    /**
     * Get the manuscript URL for an article
     * 
     * @param int $post_id
     * @return string|bool URL or false if not found
     */
    private function get_manuscript_url( $post_id ) {
        // 1. Try simple meta field
        $manuscript_url = get_post_meta( $post_id, 'article_file_manuscript_url', true );
        if ( ! empty( $manuscript_url ) ) {
            return $manuscript_url;
        }

        // 2. Try the Structured File System (_sad_article_files)
        if ( class_exists( 'SAD_Pods_File_Fields' ) ) {
            $files = SAD_Pods_File_Fields::get_files_for_frontend( $post_id );
            foreach ( $files as $file ) {
                if ( isset( $file['file_type'] ) && $file['file_type'] === 'manuscript' && ! empty( $file['url'] ) ) {
                    return $file['url'];
                }
            }
        }

        return false;
    }

    public function render_meta_box( $post ) {
        $manuscript_url = $this->get_manuscript_url( $post->ID );
        
        ?>
        <div class="sad-webhook-box">
            <p><strong>Post ID:</strong> <?php echo esc_html( $post->ID ); ?></p>
            <p><strong>Article ID:</strong> <?php echo esc_html( get_post_meta( $post->ID, 'file_id', true ) ); ?></p>
            <p><strong>Manuscript URL:</strong> <br>
                <?php if ( $manuscript_url ) : ?>
                    <a href="<?php echo esc_url( $manuscript_url ); ?>" target="_blank" style="word-break: break-all;">
                        <?php echo esc_html( basename( parse_url($manuscript_url, PHP_URL_PATH) ) ); ?>
                    </a>
                <?php else : ?>
                    <span style="color: #d63638;"><?php _e( 'Not Found', 'sad-workflow-manager' ); ?></span>
                <?php endif; ?>
            </p>
            
            <button type="button" 
                    id="sad-send-to-webhook" 
                    class="button button-primary" 
                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                    data-manuscript-url="<?php echo esc_attr( $manuscript_url ); ?>"
                    <?php disabled( empty( $manuscript_url ) ); ?>>
                <?php _e( 'Send to Webhook', 'sad-workflow-manager' ); ?>
            </button>
            <span id="sad-webhook-spinner" class="spinner" style="float: none; margin: 0 5px; vertical-align: middle;"></span>
            <div id="sad-webhook-response" style="margin-top: 10px; font-weight: 500;"></div>
        </div>
        <?php
    }

    public function ajax_send_webhook() {
        check_ajax_referer( 'sad_webhook_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sad-workflow-manager' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        
        // Always fetch fresh URL in case of S3 pre-signed expiration
        $manuscript_url = $this->get_manuscript_url( $post_id );

        if ( ! $post_id || ! $manuscript_url ) {
            wp_send_json_error( array( 'message' => __( 'Invalid data or manuscript not found.', 'sad-workflow-manager' ) ) );
        }

        $body = array(
            'post_id'        => $post_id,
            'from'           => 'GJ',
            'article_id'     => strtolower(get_post_meta( $post_id, 'file_id', true )),
            'manuscript_url' => $manuscript_url
        );

        $response = wp_remote_post( $this->webhook_url, array(
            'method'      => 'POST',
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'body'        => json_encode( $body ),
            'cookies'     => array()
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code >= 200 && $status_code < 300 ) {
            wp_send_json_success( array( 'message' => __( 'Successfully sent!', 'sad-workflow-manager' ) ) );
        } else {
            wp_send_json_error( array( 'message' => sprintf( __( 'Webhook returned error code %d', 'sad-workflow-manager' ), $status_code ) ) );
        }
    }
}
