<?php

class SAD_Workflow_Admin {

    private $plugin_name;
    private $version;
    private $rule_model;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->rule_model = new SAD_Rule_Model();
    }

    /**
     * Enqueue Admin Styles
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/sad-workflow-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Enqueue Admin Scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/rule-builder.js', array( 'jquery' ), $this->version, false );

        wp_localize_script( $this->plugin_name, 'sad_workflow_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'sad_save_rules' )
        ));
    }

    /**
     * Add Menu Page
     */
    public function add_menu_page() {
        add_submenu_page(
            'options-general.php',
            'SAD Workflow Rules',
            'Workflow Rules',
            'manage_options',
            'sad_workflow_rules',
            array( $this, 'display_page' )
        );
    }

    /**
     * Display Page Callback
     */
    public function display_page() {
        $rules = $this->rule_model->get_rules();
        
        // Fetch all registered statuses
        // This should include those registered by extended-post-status if it uses register_post_status
        $statuses = get_post_stati( array( 'show_in_admin_all_list' => true ), 'objects' );
        
        // Also try to get them via Extended_Post_Status_Admin if available, for robustness
        // But get_post_stati should work if they registered properly.
        // Let's also check distinct post_status from DB just in case? No, that's heavy.
        // Let's try to get all statuses that are "internal" false?
        $all_statuses = get_post_stati( array(), 'objects' );

        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/rule-builder-display.php';
    }

    /**
     * AJAX: Save Rules
     */
    public function ajax_save_rules() {
        check_ajax_referer( 'sad_save_rules', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $rules_json = isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : '';
        if ( empty( $rules_json ) ) {
            wp_send_json_error( 'No data' );
        }

        $rules = json_decode( $rules_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'Invalid JSON' );
        }

        if ( $this->rule_model->save_rules( $rules ) ) {
            wp_send_json_success( 'Rules saved' );
        } else {
             // Maybe no changes were made, or error. 
             // update_option returns false if value is same.
             wp_send_json_success( 'Rules saved (or unchanged)' );
        }
    }

    /**
     * Display post states in the admin list.
     *
     * @param array   $post_states An array of post display states.
     * @param WP_Post $post        The current post object.
     * @return array
     */
    public function add_progress_tags_to_title( $post_states, $post ) {
        if ( 'scholarly_article' !== $post->post_type ) {
            return $post_states;
        }

        $terms = get_the_terms( $post->ID, 'article_progress' );
        
        // Initialize terms array if null
        if ( ! $terms || is_wp_error( $terms ) ) {
            $terms = array();
        }

        // Get current meta once to check state
        $tag_reasons = get_post_meta( $post->ID, '_sad_tag_reasons', true );
        if ( ! is_array( $tag_reasons ) ) {
            $tag_reasons = array();
        }

        // --- DYNAMIC CHECK / SELF-HEALING (Missing Tags & Missing Links) ---
        $has_ticket_tag = false;
        $ticket_tag_term_id = 0;
        $needs_link_update = false;

        foreach ( $terms as $term ) {
            if ( $term->name === 'Ticket Created' ) {
                $has_ticket_tag = true;
                $ticket_tag_term_id = $term->term_id;
                // Check if related_id is missing in existing meta
                if ( empty( $tag_reasons[ $term->term_id ]['related_id'] ) ) {
                    $needs_link_update = true;
                }
                break;
            }
        }

        if ( ! $has_ticket_tag || $needs_link_update ) {
             $args = array(
                'post_type'  => 'stt_ticket',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_stt_related_article',
                        'value' => $post->ID
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
                
                if ( ! $has_ticket_tag ) {
                    // SYNC: Tag is missing entirely -> Add it
                    wp_set_object_terms( $post->ID, 'Ticket Created', 'article_progress', true );
                    $ticket_term = get_term_by( 'name', 'Ticket Created', 'article_progress' );
                    if ( $ticket_term ) {
                        $ticket_tag_term_id = $ticket_term->term_id;
                        $terms[] = $ticket_term; // Add to display list
                    }
                }

                // Update Metadata if we found a term ID (either existing or newly added)
                if ( $ticket_tag_term_id ) {
                    $tag_reasons[ $ticket_tag_term_id ] = array(
                        'reason'     => "Linked Ticket #{$ticket_id} is Open (Auto-Sync)",
                        'rule_id'    => 'integration_ticket',
                        'type'       => 'ticket',
                        'related_id' => $ticket_id
                    );
                    update_post_meta( $post->ID, '_sad_tag_reasons', $tag_reasons );
                    
                    // Update local var for the display loop below
                    // Note: display loop gets meta fresh or we pass it? 
                    // The display loop below calls get_post_meta again, so it will pick this up.
                }
            }
        }
        // -------------------------------------

        if ( ! empty( $terms ) ) {
            // Get reasons meta (refresh if we just updated)
            $tag_reasons = get_post_meta( $post->ID, '_sad_tag_reasons', true );
            
            foreach ( $terms as $term ) {
                $reason = '';
                $type = 'rule'; // Default
                $related_id = 0;
                
                if ( is_array( $tag_reasons ) && isset( $tag_reasons[ $term->term_id ] ) ) {
                    $val = $tag_reasons[ $term->term_id ];
                    if ( is_array( $val ) ) {
                         $reason = $val['reason'];
                         $type = isset( $val['type'] ) ? $val['type'] : 'rule';
                         $related_id = isset( $val['related_id'] ) ? $val['related_id'] : 0;
                    } else {
                         $reason = $val;
                    }
                } else {
                    $reason = 'Manual or Unknown Trigger';
                }
                
                $html = sprintf(
                    '<span class="sad-tag-state sad-tag-type-%s" data-reason="%s">%s</span>',
                    esc_attr( $type ),
                    esc_attr( 'Rule Trigger: ' . $reason ),
                    esc_html( $term->name )
                );

                // Wrap in link if related_id exists
                if ( $related_id ) {
                    $edit_link = get_edit_post_link( $related_id );
                    if ( $edit_link ) {
                        $html = sprintf( '<a href="%s" style="text-decoration:none;">%s</a>', esc_url( $edit_link ), $html );
                    }
                }
                
                $post_states[ 'sad_tag_' . $term->term_id ] = $html;
            }
        }

        return $post_states;
    }

}
