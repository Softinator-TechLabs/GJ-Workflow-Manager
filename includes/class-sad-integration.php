<?php

class SAD_Integration {



    public function __construct() {
        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( 'SAD_Integration initialized.' );
        }

        // Outliny Hooks
        // handled in manager

        // Consolidated Save Hook (Handles Articles, Tickets, Invoices)
        add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );
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

}
