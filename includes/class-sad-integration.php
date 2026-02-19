<?php

class SAD_Integration {



    public function __construct() {
        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( 'SAD_Integration initialized.' );
        }

        // Outliny Hooks
        // handled in manager (hook registration)
        // Implementation:
    }

    /**
     * Handle Outliny Session Completion
     * 
     * @param array $data {
     *     @type string $session_id
     *     @type string $final_status
     *     @type array  $final_data
     *     @type float  $execution_time
     * }
     */
    public function handle_outliny_completion( $data ) {
         if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Integration: Outliny Action Completed. Data: " . print_r($data, true) );
        }

        if ( empty( $data['final_status'] ) || empty( $data['final_data'] ) ) {
            return;
        }

        // We need post_id. It should be in final_data (passed from processor)
        // Or we might need to look it up via session if not present, but let's check final_data first.
        $post_id = isset( $data['final_data']['post_id'] ) ? intval( $data['final_data']['post_id'] ) : 0;

        if ( ! $post_id ) {
             // Fallback: Try to get from log if possible, or just fail safely
             // Outliny_Logger::get_log_detail... but expensive.
             if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Integration: No Post ID found in final_data. Aborting rule check." );
            }
             return;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        // Trigger Rule Engine
        if ( class_exists( 'SAD_Rule_Engine' ) ) {
             if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Integration: Triggering Rule Engine for Post #$post_id." );
            }
            $rule_engine = new SAD_Rule_Engine();
            // Pass current status as old status because we aren't changing status yet, just evaluating
            $rule_engine->run_rules( $post_id, $post, $post->post_status );
        }
        // Consolidated Save Hook (Handles Articles, Tickets, Invoices)
        add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );

        // Ticket Status Change Hook (for AJAX updates in Ticket Tracker)
        add_action( 'stt_ticket_status_changed', array( $this, 'handle_ticket_status_change' ), 10, 4 );
    }

    /**
     * Handle Ticket Status Change (triggered by STT Plugin AJAX)
     */
    public function handle_ticket_status_change( $ticket_id, $old_status, $new_status, $user_id ) {
        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Integration: Ticket #$ticket_id status changed from $old_status to $new_status. Triggering sync." );
        }
        $ticket = get_post( $ticket_id );
        if ( $ticket ) {
            $this->handle_ticket_save( $ticket_id, $ticket );
        }
    }

    /**
     * Central dispatcher for save_post events
     */
    public function handle_save_post( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Dispatch based on post type
        if ( 'stt_ticket' === $post->post_type ) {
            $this->handle_ticket_save( $post_id, $post );
        } elseif ( 'scholarly_article' === $post->post_type ) {
            $this->handle_article_save( $post_id, $post );
        } elseif ( 'sad_invoice' === $post->post_type ) {
            $this->handle_invoice_save( $post_id, $post );
        }
    }

    /**
     * Handle Ticket Save: Update linked Article tags
     */
    private function handle_ticket_save( $ticket_id, $ticket ) {
        $article_id = get_post_meta( $ticket_id, '_stt_related_article', true );
        
        if ( ! $article_id ) {
            // Try identifying article from other meta if needed, but strictly _stt_related_article is standard
            return;
        }

        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Integration: Ticket #$ticket_id saved. Syncing Article #$article_id." );
        }

        // Check if Ticket is Open/Active
        $status = get_post_meta( $ticket_id, '_stt_status', true );
        // Open statuses: open, in-progress. Closed: resolved, closed.
        $is_active = in_array( $status, array( 'open', 'in-progress' ) );

        if ( $is_active ) {
            $this->add_tag_with_type( $article_id, 'Ticket Created', 'ticket', "Linked Ticket #{$ticket_id} is Open", $ticket_id );
        } else {
            // Ticket is closed/resolved. Check if any OTHER open tickets exist for this article.
            $args = array(
                'post_type'  => 'stt_ticket',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_stt_related_article',
                        'value' => $article_id
                    ),
                    array(
                        'key'     => '_stt_status',
                        'value'   => array( 'open', 'in-progress' ),
                        'compare' => 'IN'
                    ),
                    array(
                        'key'     => 'ID', // Actually we need to exclude current ticket? 
                        // No, meta_query can't check post ID easily unless we use post__not_in in top level args
                    )
                ),
                'post__not_in' => array( $ticket_id ),
                'fields'       => 'ids',
                'posts_per_page' => 1
            );

            $other_open_tickets = get_posts( $args );

            if ( empty( $other_open_tickets ) ) {
                // No other open tickets. Safe to remove the tag.
                if ( class_exists( 'SAD_Logger' ) ) {
                    SAD_Logger::log( "Integration: Ticket #$ticket_id closed. No other open tickets found. Removing tag from Article #$article_id." );
                }
                $this->remove_tag_with_type( $article_id, 'Ticket Created', 'ticket' );
            } else {
                if ( class_exists( 'SAD_Logger' ) ) {
                     SAD_Logger::log( "Integration: Ticket #$ticket_id closed, but other open tickets exist. Keeping tag." );
                }
            }
        }
    }

    /**
     * Handle Article Save: Check for existing tickets
     */
    private function handle_article_save( $article_id, $article ) {
        // Query for ANY open tickets linked to this article
        $args = array(
            'post_type'  => 'stt_ticket',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'   => '_stt_related_article',
                    'value' => $article_id
                ),
                array(
                    'key'     => '_stt_status',
                    'value'   => array( 'open', 'in-progress' ),
                    'compare' => 'IN'
                )
            ),
            'fields'     => 'ids',
            'posts_per_page' => 1
        );

        $tickets = get_posts( $args );

        if ( ! empty( $tickets ) ) {
            $ticket_id = $tickets[0];
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Integration: Article #$article_id saved. Found active Ticket #$ticket_id. Applying tag." );
            }
            $this->add_tag_with_type( $article_id, 'Ticket Created', 'ticket', "Active Ticket #{$ticket_id} Found", $ticket_id );
        } else {
            // No open tickets found. Ensure tag is removed.
            // This acts as a cleanup sync.
            // Check if tag exists first to avoid unnecessary writes? remove_tag_with_type handles logic.
            $this->remove_tag_with_type( $article_id, 'Ticket Created', 'ticket' );
        }
    }

    /**
     * Handle Invoice Save/Update
     */
    private function handle_invoice_save( $post_id, $post ) {
        $article_id = get_post_meta( $post_id, 'article_id', true );
        if ( ! $article_id ) {
            return;
        }

        $status = get_post_meta( $post_id, 'status', true );
        $tag_name = '';
        $reason = '';

        if ( $status === 'sent' ) {
            $tag_name = 'Invoice Sent';
            $reason = "Invoice #{$post_id} Sent";
        } elseif ( $status === 'paid' ) {
            $tag_name = 'Invoice Paid';
            $reason = "Invoice #{$post_id} Paid";
        }

        if ( $tag_name ) {
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Integration: Invoice #$post_id updated ($status). Adding tag to Article #$article_id." );
            }
            $this->add_tag_with_type( $article_id, $tag_name, 'invoice', $reason, $post_id );
        }
    }

    /**
     * Helper to add tag with explicit type
     */
    private function add_tag_with_type( $post_id, $tag_name, $type, $reason, $related_id = 0 ) {
        // Add Term
        $term_ids = wp_add_object_terms( $post_id, $tag_name, 'article_progress' );
        
        if ( is_wp_error( $term_ids ) ) {
            return;
        }

        // Get Term ID (wp_add_object_terms returns array of IDs, or error)
        // If existing, it might return array of ints.
        // We need the ID of the specific tag we just added/ensured.
        $term = get_term_by( 'name', $tag_name, 'article_progress' );
        if ( ! $term ) {
            return;
        }

        // Update Meta
        $current_info = get_post_meta( $post_id, '_sad_tag_reasons', true );
        if ( ! is_array( $current_info ) ) {
            $current_info = array();
        }

        $current_info[ $term->term_id ] = array(
            'reason'     => $reason,
            'rule_id'    => 'integration_' . $type, // Virtual rule ID
            'type'       => $type, // 'ticket', 'invoice', 'rule' (default null/rule)
            'related_id' => $related_id
        );

        update_post_meta( $post_id, '_sad_tag_reasons', $current_info );
    }

    /**
     * Helper to remove tag and cleanup meta
     */
    private function remove_tag_with_type( $post_id, $tag_name, $type ) {
        // Remove Term
        wp_remove_object_terms( $post_id, $tag_name, 'article_progress' );
        
        // Remove from Meta
        $term = get_term_by( 'name', $tag_name, 'article_progress' );
        if ( ! $term ) {
            return;
        }

        $current_info = get_post_meta( $post_id, '_sad_tag_reasons', true );
        if ( is_array( $current_info ) && isset( $current_info[ $term->term_id ] ) ) {
            // Only remove if it matches the type (optional check, but good for safety)
            // Actually, we force remove for now based on logic above.
            unset( $current_info[ $term->term_id ] );
            update_post_meta( $post_id, '_sad_tag_reasons', $current_info );
            
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Integration: Removed tag '$tag_name' from Post #$post_id." );
            }
        }
    }

}
