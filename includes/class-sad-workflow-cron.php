<?php

/**
 * Class SAD_Workflow_Cron
 * 
 * Handles automated tracking of scholarly article workflow steps.
 */
class SAD_Workflow_Cron {

    /**
     * Initialize the cron hooks.
     */
    public function init() {
        add_action( 'sad_workflow_daily_check', array( $this, 'check_all_articles' ) );
        add_action( 'save_post_scholarly_article', array( $this, 'trigger_article_check' ) );
    }

    /**
     * Trigger a check for a specific article.
     * Useful for running immediately after a save operation.
     */
    public function trigger_article_check( $post_id ) {
        if ( get_post_type( $post_id ) === 'scholarly_article' ) {
            $this->process_article( $post_id );
        }
    }

    /**
     * Main entry point for the daily check.
     */
    public function check_all_articles() {
        $args = array(
            'post_type'      => 'scholarly_article',
            'post_status'    => array( 'publish', 'reviewing', 'unassigned', 'just-accepted', 'formating', 'earlyview-launched', 'ejournal-launched' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        $articles = get_posts( $args );

        if ( empty( $articles ) ) {
            return;
        }

        foreach ( $articles as $article_id ) {
            $this->process_article( $article_id );
        }
    }

    /**
     * Process a single article's workflow state.
     */
    public function process_article( $article_id ) {
        $post = get_post( $article_id );
        if ( ! $post ) return;

        $workflow_steps = $this->get_workflow_definition();
        $current_time = current_time( 'timestamp' );
        
        // Base date is the upload date
        $previous_step_date = strtotime( $post->post_date );
        
        foreach ( $workflow_steps as $step_key => $step ) {
            $is_completed = $this->check_step_completion( $article_id, $step );
            $completion_date = get_post_meta( $article_id, "_sad_step_{$step_key}_date", true );
            
            if ( $is_completed ) {
                if ( ! $completion_date ) {
                    $completion_date = current_time( 'mysql' );
                    update_post_meta( $article_id, "_sad_step_{$step_key}_date", $completion_date );
                }
                
                // Clear warning if exists
                $this->remove_warning_tag( $article_id, $step['label'] );
                
                // Set the date for the next step's deadline calculation
                $previous_step_date = strtotime( $completion_date );
                continue; // Move to next step
            }

            // Step is NOT completed. Check if it should even be active based on dependencies.
            $should_check_deadline = true;
            if ( isset( $step['condition'] ) && is_callable( $step['condition'] ) ) {
                if ( ! call_user_func( $step['condition'], $article_id ) ) {
                    $should_check_deadline = false;
                }
            }

            if ( $should_check_deadline ) {
                // Calculate deadline
                $deadline_timestamp = strtotime( "+{$step['days']} days", $previous_step_date );
                update_post_meta( $article_id, "_sad_step_{$step_key}_deadline", date( 'Y-m-d H:i:s', $deadline_timestamp ) );

                if ( $current_time > $deadline_timestamp ) {
                    $this->apply_warning_tag( $article_id, $step['label'], "Deadline exceeded for {$step['label']}" );
                } else {
                    // Not overdue yet, ensure no warning tag
                    $this->remove_warning_tag( $article_id, $step['label'] );
                }
            } else {
                // Condition not met (e.g., DOI Send for unpaid article)
                $this->remove_warning_tag( $article_id, $step['label'] );
                delete_post_meta( $article_id, "_sad_step_{$step_key}_deadline" );
            }

            // Once we hit a pending step, we stop calculating future deadlines 
            // as they depend on the completion of the current step.
            break;
        }
    }

    /**
     * Define the workflow steps.
     */
    private function get_workflow_definition() {
        return array(
            'grammarly' => array(
                'label' => 'Grammarly Report',
                'days'  => 2,
                'field' => 'pre_peer_review_report_url',
                'type'  => 'not_empty'
            ),
            'review_files' => array(
                'label' => 'Comment File + RAAR',
                'days'  => 5,
                'fields' => array( 'comments_file', 'raar_report_pdf' ),
                'type'  => 'all_not_empty'
            ),
            'acceptance' => array(
                'label' => 'Acceptance',
                'days'  => 8,
                'field' => 'article_acceptance_date',
                'type'  => 'not_empty'
            ),
            'early_view' => array(
                'label' => 'Early View Launch',
                'days'  => 20,
                'field' => 'early_view_individual_file',
                'type'  => 'not_empty'
            ),
            'ejournal' => array(
                'label' => 'E-Journal',
                'days'  => 10,
                'field' => 'research_article_pdf_url',
                'type'  => 'not_empty'
            ),
            'doi_send' => array(
                'label' => 'DOI Send',
                'days'  => 2,
                'field' => 'doi',
                'type'  => 'not_empty',
                'condition' => function( $article_id ) {
                    $payment_status = strtolower( get_post_meta( $article_id, 'payment_status', true ) );
                    return in_array( $payment_status, array( 'paid', 'completed', 'confirmed' ) );
                }
            ),
            'dispatch' => array(
                'label' => 'Article Dispatch',
                'days'  => 8,
                'condition' => function( $article_id ) {
                    return get_post_meta( $article_id, 'hardcopy', true ) === 'yes';
                },
                'custom_check' => function( $article_id ) {
                    $hardcopy = get_post_meta( $article_id, 'hardcopy', true );
                    $status = get_post_field( 'post_status', $article_id );
                    // If hardcopy is no longer 'yes' (replaced by URL) or status is dispatched
                    return ( $hardcopy !== 'yes' && ! empty( $hardcopy ) ) || $status === 'wc-hardcopy-is-dispa';
                }
            )
        );
    }

    /**
     * Check if a step is completed.
     */
    private function check_step_completion( $article_id, $step ) {
        if ( isset( $step['custom_check'] ) && is_callable( $step['custom_check'] ) ) {
            return call_user_func( $step['custom_check'], $article_id );
        }

        if ( $step['type'] === 'not_empty' ) {
            $val = get_post_meta( $article_id, $step['field'], true );
            return ! empty( $val );
        }

        if ( $step['type'] === 'all_not_empty' ) {
            foreach ( $step['fields'] as $field ) {
                if ( empty( get_post_meta( $article_id, $field, true ) ) ) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Apply a warning tag to the article.
     */
    private function apply_warning_tag( $article_id, $step_label, $reason ) {
        $tag_name = "Warning: {$step_label}";
        
        // Ensure term exists
        if ( ! term_exists( $tag_name, 'article_progress' ) ) {
            wp_insert_term( $tag_name, 'article_progress' );
        }

        // Add tag
        wp_set_object_terms( $article_id, $tag_name, 'article_progress', true );

        // Track reason for tooltip
        $tag_reasons = get_post_meta( $article_id, '_sad_tag_reasons', true );
        if ( ! is_array( $tag_reasons ) ) {
            $tag_reasons = array();
        }

        $term = get_term_by( 'name', $tag_name, 'article_progress' );
        if ( $term ) {
            $tag_reasons[ $term->term_id ] = array(
                'reason' => $reason,
                'type'   => 'warning',
                'date'   => current_time( 'mysql' )
            );
            update_post_meta( $article_id, '_sad_tag_reasons', $tag_reasons );
        }
    }

    /**
     * Remove a specific warning tag.
     */
    private function remove_warning_tag( $article_id, $step_label ) {
        $tag_name = "Warning: {$step_label}";
        $term = get_term_by( 'name', $tag_name, 'article_progress' );
        
        if ( $term && has_term( $term->term_id, 'article_progress', $article_id ) ) {
            wp_remove_object_terms( $article_id, $term->term_id, 'article_progress' );
            
            // Clean up reasons
            $tag_reasons = get_post_meta( $article_id, '_sad_tag_reasons', true );
            if ( is_array( $tag_reasons ) && isset( $tag_reasons[ $term->term_id ] ) ) {
                unset( $tag_reasons[ $term->term_id ] );
                update_post_meta( $article_id, '_sad_tag_reasons', $tag_reasons );
            }
        }
    }
}
