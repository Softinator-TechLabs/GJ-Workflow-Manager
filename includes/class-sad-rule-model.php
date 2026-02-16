<?php

class SAD_Rule_Model {

    private $option_name = 'sad_workflow_rules';

    /**
     * Get all rules.
     *
     * @return array Rules array.
     */
    public function get_rules() {
        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Model: Fetching option '{$this->option_name}'" );
        }
        $rules = get_option( $this->option_name, array() );
        
        if ( class_exists( 'SAD_Logger' ) ) {
            SAD_Logger::log( "Model: Fetched rules. Type: " . gettype($rules) );
            if ( is_array($rules) ) {
                 SAD_Logger::log( "Model: Rule Count: " . count($rules) );
            } else {
                 SAD_Logger::log( "Model: Unexpected type for rules!" );
            }
        }
        return $rules;
    }

    /**
     * Save rules.
     *
     * @param array $rules Rules array.
     * @return bool True on success, false on failure.
     */
    public function save_rules( $rules ) {
        return update_option( $this->option_name, $rules );
    }

    /**
     * Add a single rule.
     *
     * @param array $rule Rule data.
     * @return bool True on success.
     */
    public function add_rule( $rule ) {
        $rules = $this->get_rules();
        // Ensure ID
        if ( empty( $rule['id'] ) ) {
            $rule['id'] = uniqid( 'rule_' );
        }
        $rules[] = $rule;
        return $this->save_rules( $rules );
    }
}
