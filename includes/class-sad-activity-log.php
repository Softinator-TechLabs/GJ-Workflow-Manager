<?php

class SAD_Activity_Log {



    public function add_meta_box() {
        add_meta_box(
            'sad_activity_log',
            __( 'Outliny Activity Log', 'sad-workflow-manager' ),
            array( $this, 'render_meta_box' ),
            'scholarly_article',
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'outliny_action_logs';

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            echo '<p>' . __( 'Outliny logs table not found.', 'sad-workflow-manager' ) . '</p>';
            return;
        }

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d ORDER BY created_at DESC LIMIT 20",
            $post->ID
        ) );

        // Show Current Progress Tags and Reasons
        $terms = get_the_terms( $post->ID, 'article_progress' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            $tag_reasons = get_post_meta( $post->ID, '_sad_tag_reasons', true );
            echo '<div class="sad-current-workflow-status" style="margin-bottom: 20px; padding: 15px; background: #f0f6fb; border-left: 4px solid #2271b1;">';
            echo '<h4>' . __( 'Current Workflow Progress', 'sad-workflow-manager' ) . '</h4>';
            echo '<ul style="margin: 0;">';
            foreach ( $terms as $term ) {
                $reason = 'Manual or Unknown Trigger';
                $type = 'rule';
                
                if ( is_array( $tag_reasons ) && isset( $tag_reasons[ $term->term_id ] ) ) {
                    $val = $tag_reasons[ $term->term_id ];
                     if ( is_array( $val ) ) {
                         $reason = $val['reason'];
                         $type = isset( $val['type'] ) ? $val['type'] : 'rule';
                    } else {
                         $reason = $val;
                    }
                }
                
                $color = '#2271b1'; // Default Rule
                if ( $type === 'ticket' ) $color = '#8e44ad'; // Purple
                if ( $type === 'invoice' ) $color = '#e67e22'; // Orange

                echo '<li style="margin-bottom: 5px;">';
                echo '<strong style="display:inline-block; min-width: 150px; color: ' . esc_attr($color) . ';">' . esc_html( $term->name ) . ':</strong> ';
                echo '<span style="color: #666; font-style: italic;">' . esc_html( $reason ) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if ( empty( $logs ) ) {
            echo '<p>' . __( 'No activity recorded yet.', 'sad-workflow-manager' ) . '</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __( 'Date/Time', 'sad-workflow-manager' ) . '</th>';
        echo '<th>' . __( 'User', 'sad-workflow-manager' ) . '</th>';
        echo '<th>' . __( 'Action', 'sad-workflow-manager' ) . '</th>';
        echo '<th>' . __( 'Status', 'sad-workflow-manager' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ( $logs as $log ) {
            $user_info = get_userdata( $log->user_id );
            $user_name = $user_info ? $user_info->display_name : 'Unknown';
            $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
            $date = date_i18n( $date_format, strtotime( $log->created_at ) );

            echo '<tr>';
            echo '<td>' . esc_html( $date ) . '</td>';
            echo '<td>' . esc_html( $user_name ) . '</td>';
            echo '<td>' . esc_html( $log->button_name ) . '</td>';
            echo '<td>' . esc_html( ucfirst( $log->status ) ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }
}
