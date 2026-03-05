<?php

class SAD_Webhook_Dispatcher {

    private $webhook_url = 'https://n80n.softinator.org/webhook/e4653eb5-3d4a-48a8-9db8-5681a6988dd5';

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
        
        $result = $this->send_post_to_webhook( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Successfully sent!', 'sad-workflow-manager' ) ) );
    }

    /**
     * Trigger webhook when an article is created via WordPress Admin
     */
    public function trigger_on_save( $post_id, $post, $update ) {
        // Only trigger on creation, not on updates.
        if ( $update ) {
            return;
        }

        // Only for scholarly articles
        if ( 'scholarly_article' !== get_post_type( $post_id ) ) {
            return;
        }

        // Avoid double-triggering for Quick Submit articoli
        // Quick Submit fires 'sad_after_quick_submit' after everything is done.
        // Also it fires 'sad_before_create_article' before insert.
        if ( did_action( 'sad_before_create_article' ) || did_action( 'sad_after_quick_submit' ) ) {
            return;
        }

        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "SAD Webhook: Triggered for post ID $post_id via Admin Save." );
        }

        $this->send_post_to_webhook( $post_id );
    }

    /**
     * Trigger webhook when an article is created via Quick Submit
     * Fires only after files are uploaded.
     */
    public function trigger_on_quick_submit( $article_id, $user_id, $attachment_id ) {
        // Log for debugging
        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "SAD Webhook: Triggered for post ID $article_id via Quick Submit COMPLETION." );
        }
        
        $result = $this->send_post_to_webhook( $article_id );
        
        if ( is_wp_error( $result ) ) {
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "SAD Webhook Error: " . $result->get_error_message() );
            }
        } else {
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "SAD Webhook Success: Post ID $article_id sent." );
            }
        }
    }

    /**
     * Trigger webhook when an article is created via Quick Submit (DEPRECATED: too early)
     */
    public function trigger_on_article_creation( $post_id, $data ) {
        // This fires before files are ready in some cases.
        // We keep it for backward compatibility if needed, but log that it's too early.
        if ( class_exists( 'SAD_Logger' ) ) {
            // SAD_Logger::log( "SAD Webhook: trigger_on_article_creation fired for $post_id (possibly too early)" );
        }
    }

    /**
     * Internal method to send post data to webhook
     * 
     * @param int $post_id
     * @return bool|WP_Error
     */
    public function send_post_to_webhook( $post_id ) {
        $manuscript_url = $this->get_manuscript_url( $post_id );

        if ( ! $post_id || ! $manuscript_url ) {
            return new WP_Error( 'invalid_data', __( 'Invalid data or manuscript not found.', 'sad-workflow-manager' ) );
        }

        $body = array(
            'post_id'               => $post_id,
            'from'                  => 'GJ',
            'article_id'            => strtolower(get_post_meta( $post_id, 'file_id', true )),
            'manuscript_url'        => $manuscript_url,
            'title'                 => get_post_meta( $post_id, 'article_title', true ),
            'short_title_50'        => get_post_meta( $post_id, 'short_title_50', true ),
            'short_title_20'        => get_post_meta( $post_id, 'short_title_20', true ),
            'language'              => get_post_meta( $post_id, 'language', true ),
            'abstract'              => get_post_meta( $post_id, 'abstract', true ),
            'keywords'              => get_post_meta( $post_id, 'keywords', true ),
            'funding_statement'     => get_post_meta( $post_id, 'funding_statement', true ),
            'conflict_of_interest'  => get_post_meta( $post_id, 'conflict_of_interest', true ),
            'article_type'          => get_post_meta( $post_id, 'article_type', true ),
            'classification_number' => get_post_meta( $post_id, 'classification_number', true ),
            'authors'               => get_post_meta( $post_id, 'authors', true ),
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
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code >= 200 && $status_code < 300 ) {
            return true;
        } else {
            return new WP_Error( 'webhook_error', sprintf( __( 'Webhook returned error code %d', 'sad-workflow-manager' ), $status_code ) );
        }
    }
}
