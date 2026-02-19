<?php

class SAD_Rule_Engine {

    private $rule_model;
    private $tag_reasons = array();

    public function __construct() {
        $this->rule_model = new SAD_Rule_Model();
    }

    /**
     * Run rules on transition_post_status.
     *
     * @param string $new_status New Post Status.
     * @param string $old_status Old Post Status.
     * @param WP_Post $post Post Object.
     */
    public function run_rules_on_transition( $new_status, $old_status, $post ) {
         if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Engine: Transition detected for Post ID {$post->ID}. Old: $old_status -> New: $new_status" );
        }
        $this->run_rules( $post->ID, $post, $old_status );
    }

    /**
     * Run rules on save_post or transition.
     *
     * @param int $post_id Post ID.
     * @param WP_Post $post Post object.
     * @param string $old_status Optional. Previous status if available.
     */
    public function run_rules( $post_id, $post, $old_status = '' ) {

        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Engine: Fired for Post ID $post_id. Post Type: " . ( isset($post->post_type) ? $post->post_type : 'N/A' ) );
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: Autosave detected. Aborting." );
            }
            return;
        }

        // Validate Post Object
        if ( ! $post instanceof WP_Post ) {
             if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: \$post is not a WP_Post object. Type: " . gettype($post) );
            }
            return;
        }

        if ( 'scholarly_article' !== $post->post_type ) {
             if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: Post type mismatch. Actual: {$post->post_type}. Expected: scholarly_article." );
            }
            return;
        }

        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Engine: Retrieving rules..." );
        }

        // Get all rules
        $rules = $this->rule_model->get_rules();

        if ( empty( $rules ) ) {
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: No rules found in DB. Loading defaults." );
            }
            // Fallback for testing: Hardcoded rules if no DB rules exist
            // This allows us to test without building the UI first
            $rules = $this->get_default_rules();
        }

        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Engine: Found " . count($rules) . " rules to evaluate." );
            SAD_Logger::log( "Engine: Old status provided: '$old_status'" );
        }

        $tags_to_add = array();
        $tags_to_remove = array();

        // Apply Status Change (Process only the LAST matching rule's status change to avoid conflicts, or first? Let's do last.)
        $new_status_action = '';
        foreach ( $rules as $rule ) {
             if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: Checking Status Rule [{$rule['id']}]..." );
            }
             if ( $this->evaluate_rule( $rule, $post_id, $post, $old_status ) ) {
                 if ( class_exists( 'SAD_Logger' ) ) {
                    SAD_Logger::log( "Engine: Rule [{$rule['id']}] matched conditions." );
                }
                 if ( ! empty( $rule['action_new_status'] ) ) {
                     $new_status_action = $rule['action_new_status'];
                     if ( class_exists( 'SAD_Logger' ) ) {
                        SAD_Logger::log( "Engine: Proposed New Status from Action: $new_status_action" );
                    }
                 }
             }
        }

        foreach ( $rules as $rule ) {
            if ( $this->evaluate_rule( $rule, $post_id, $post, $old_status ) ) {
                // Construct Reason String
                $reasons = array();
                if ( ! empty( $rule['conditions'] ) ) {
                    foreach ( $rule['conditions'] as $cond ) {
                        $reasons[] = $cond['field'] . ' ' . str_replace('_', ' ', $cond['operator']) . ' ' . ( isset($cond['value']) ? $cond['value'] : '' );
                    }
                }
                $reason_str = ! empty( $reasons ) ? implode( ', ', $reasons ) : 'Manual Rule';

                if ( ! empty( $rule['action_add_tag'] ) ) {
                    $tags_to_add[] = $rule['action_add_tag'];
                    // Store reason and rule ID for this tag
                    $this->tag_reasons[ $rule['action_add_tag'] ] = array(
                        'reason'  => $reason_str,
                        'rule_id' => $rule['id']
                    );
                }
                if ( ! empty( $rule['action_remove_tag'] ) ) {
                    $tags_to_remove[] = $rule['action_remove_tag'];
                }
            }
        }

        // Apply changes
        if ( ! empty( $tags_to_add ) ) {
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: Adding Tags: " . implode(', ', $tags_to_add) );
            }
            $term_ids = wp_add_object_terms( $post_id, $tags_to_add, 'article_progress' );
            
            // Allow for wp_add_object_terms returning different structures or errors
             if ( ! is_wp_error( $term_ids ) ) {
                 // It might return array of IDs, or just IDs if single?
                 // Documentation says array of term IDs used.
                 // But wait, $tags_to_add is array of names. wp_add_object_terms handles creating/finding.
                 // We need to map the Name back to the ID to store the meta securely?
                 // Or just store the reason on the post meta keyed by the Term ID?
                 // Let's iterate the term IDs and find which name they correspond to (hard if multiple added).
                 
                 // Alternative: Store reason in a single array meta `_sad_tag_reasons` = { term_id: reason }
                 
                 $current_info = get_post_meta( $post_id, '_sad_tag_reasons', true );
                 if ( ! is_array( $current_info ) ) {
                     $current_info = array();
                 }
                 
                 foreach( $tags_to_add as $tag_name ) {
                     $term = get_term_by( 'name', $tag_name, 'article_progress' );
                     if ( $term && ! is_wp_error( $term ) ) {
                         if ( isset( $this->tag_reasons[$tag_name] ) ) {
                             $info = $this->tag_reasons[$tag_name];
                             $rule_id = $info['rule_id'];

                             // SYNC CHECK: If this rule previously added A DIFFERENT tag, remove the old one
                             foreach ( $current_info as $old_term_id => $old_data ) {
                                 if ( is_array( $old_data ) && isset( $old_data['rule_id'] ) && $old_data['rule_id'] === $rule_id ) {
                                     if ( $old_term_id != $term->term_id ) {
                                         // Rule has changed its tag name! Remove the old tag from post.
                                         wp_remove_object_terms( $post_id, (int)$old_term_id, 'article_progress' );
                                         unset( $current_info[ $old_term_id ] );
                                     }
                                 }
                             }

                             $current_info[ $term->term_id ] = $info;
                         }
                     }
                 }
                 update_post_meta( $post_id, '_sad_tag_reasons', $current_info );
             }
        }

        if ( ! empty( $tags_to_remove ) ) {
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: Removing Tags: " . implode(', ', $tags_to_remove) );
            }
            wp_remove_object_terms( $post_id, $tags_to_remove, 'article_progress' );
        }

       

        if ( ! empty( $new_status_action ) && $new_status_action !== $post->post_status ) {
            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: Updating status from '{$post->post_status}' to '$new_status_action'" );
            }
            
            // Prevent infinite loop (Unhook everything to be safe)
            remove_action( 'save_post', array( $this, 'run_rules' ), 20 );
            remove_action( 'transition_post_status', array( $this, 'run_rules_on_transition' ), 20 );
            
            $updated = wp_update_post( array(
                'ID' => $post_id,
                'post_status' => $new_status_action
            ) );

            if ( class_exists( 'SAD_Logger' ) ) {
                SAD_Logger::log( "Engine: Update Result: " . ( is_wp_error($updated) ? $updated->get_error_message() : $updated ) );
            }

            // Re-hook
            add_action( 'transition_post_status', array( $this, 'run_rules_on_transition' ), 20, 3 );
        }

    }

    /**
     * Evaluate a single rule.
     *
     * @param array $rule Rule definition.
     * @param int $post_id Post ID.
     * @param WP_Post $post Post Object.
     * @param string $old_status Old Status (optional).
     * @return bool True if conditions met.
     */
    private function evaluate_rule( $rule, $post_id, $post, $old_status = '', $visited_rules = array() ) {

        // Cycle Detection
        if ( isset( $rule['id'] ) ) {
            if ( in_array( $rule['id'], $visited_rules ) ) {
                if ( class_exists( 'SAD_Logger' ) ) {
                    SAD_Logger::log( "Engine: Cycle detected for Rule ID {$rule['id']}. Aborting evaluation." );
                }
                return false;
            }
            $visited_rules[] = $rule['id'];
        }

        // 1. Check Trigger Status
        if ( ! empty( $rule['trigger_status'] ) ) {
             // If trigger_status is array, check if current status is in it
             $trigger_statuses = is_array( $rule['trigger_status'] ) ? $rule['trigger_status'] : array( $rule['trigger_status'] );
             
             $status_matched = false;

             // Check Current Status
             if ( in_array( $post->post_status, $trigger_statuses ) ) {
                 $status_matched = true;
             }
             
             // Check Old Status (if transitioning away)
             if ( ! $status_matched && ! empty( $old_status ) && in_array( $old_status, $trigger_statuses ) ) {
                 $status_matched = true;
             }

             if ( ! $status_matched ) {
                 return false;
             }
        }

        // 2. Check Conditions
        if ( ! empty( $rule['conditions'] ) ) {
            foreach ( $rule['conditions'] as $condition ) {
                if ( ! $this->check_condition( $condition, $post_id, $post, $visited_rules ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check individual condition.
     *
     * @param array $condition Condition data (field, operator, value).
     * @param int $post_id Post ID.
     * @param WP_Post $post Post Object.
     * @return bool
     */
    private function check_condition( $condition, $post_id, $post, $visited_rules = array() ) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $expected_value = isset( $condition['value'] ) ? $condition['value'] : '';

        // Get value
        $actual_value = '';

        // --- Custom Integration: Rule Match (Inheritance) ---
        if ( strpos( $field, 'rule_match:' ) === 0 ) {
             $parts = explode( ':', $field );
             if ( isset( $parts[1] ) ) {
                 $target_rule_id = $parts[1];
                 $target_rule = $this->rule_model->get_rule( $target_rule_id );
                 
                 if ( $target_rule ) {
                     // Recursive Check
                     $is_match = $this->evaluate_rule( $target_rule, $post_id, $post, '', $visited_rules );
                     $actual_value = $is_match ? 'true' : 'false';
                 } else {
                     if ( class_exists( 'SAD_Logger' ) ) {
                        SAD_Logger::log( "Engine: Target Rule $target_rule_id not found." );
                     }
                 }
             }
        }
        // --- Custom Integration: Outliny Action Status ---
        elseif ( strpos( $field, 'outliny_action_status:' ) === 0 ) {
            $parts = explode( ':', $field );
            if ( isset( $parts[1] ) ) {
                $button_id = intval( $parts[1] );
                
                global $wpdb;
                $table_name = $wpdb->prefix . 'outliny_action_logs';

                // Check if table exists (cache this check?)
                // Use a more robust check or just try/catch if possible, but WPDB doesn't throw exceptions easily.
                // SHOW TABLES LIKE 'tableName' usually returns the table name if it exists.
                // However, let's just run the query and check for null, as get_var returns null if not found.
                // But if table doesn't exist, it prints a database error.
                // Let's use the standard WP method to check table existence if we want to be safe.
                // Or compare lower case.
                
                // $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;

                // if ( $table_exists ) {
                    $query = $wpdb->prepare(
                        "SELECT status FROM $table_name WHERE post_id = %d AND button_id = %d ORDER BY created_at DESC LIMIT 1",
                        $post_id,
                        $button_id
                    );
                    $status = $wpdb->get_var( $query );
                    
                    if ( class_exists( 'SAD_Logger' ) ) {
                        // Optional: Log only on error or extensive debug mode?
                        // SAD_Logger::log( "Engine: Outliny Check - Post: $post_id, Button: $button_id, Status: " . ($status ? $status : 'null') );
                    }

                    $actual_value = $status ? $status : ''; // 'success', 'error', etc.
                // }
            }
        }
        // --- End Custom Integration ---
        elseif ( in_array( $field, array( 'post_title', 'post_content', 'post_status' ) ) ) {
             $actual_value = $post->$field;
        } else {
            // Assume Pods or Meta
            // Try Pods first if available
            if ( function_exists( 'pods' ) ) {
                $pod = pods( 'scholarly_article', $post_id );
                $actual_value = $pod->field( $field );
                 // If Pods returns array (like file field), maybe handle it?
                 if ( is_array( $actual_value ) ) {
                     // For file fields, we might check if empty or count
                     // For now, let's just json_encode for comparison or check if not empty
                 }
            } else {
                $actual_value = get_post_meta( $post_id, $field, true );
            }
        }

        if ( class_exists( 'SAD_Logger' ) ) {
             $log_val = is_array($actual_value) ? json_encode($actual_value) : $actual_value;
             SAD_Logger::log( "Engine: Checking Condition - Field: $field | Operator: $operator | Expected: $expected_value | Actual: $log_val" );
        }

        // Evaluate
        switch ( $operator ) {
            case 'is':
                return $actual_value == $expected_value;
            case 'is_not':
                return $actual_value != $expected_value;
            case 'is_empty':
                return empty( $actual_value );
            case 'not_empty':
                return ! empty( $actual_value );
            case 'contains':
                return strpos( $actual_value, $expected_value ) !== false;
            default:
                return false;
        }
    }

    /**
     * Default rules for testing.
     */
    private function get_default_rules() {
        return array(
            array(
                'id' => 'default_1',
                'trigger_status' => array( 'draft', 'publish', 'pending' ),
                'conditions' => array(
                    array( 'field' => 'semantic_report_url', 'operator' => 'not_empty' )
                ),
                'action_add_tag' => 'Semantic Report Ready',
                'action_remove_tag' => ''
            ),
             array(
                'id' => 'default_2',
                'trigger_status' => array( 'reviewing' ),
                 'conditions' => array(
                    array( 'field' => 'reviewer_id', 'operator' => 'not_empty' )
                ),
                'action_add_tag' => 'Review Comments Ready',
                'action_remove_tag' => ''
            )
        );
    }

}
