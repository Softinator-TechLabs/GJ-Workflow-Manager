<?php

/**
 * Class SAD_Workflow_Dashboard
 * 
 * Handles the dashboard widget for workflow deadlines.
 */
class SAD_Workflow_Dashboard {

    /**
     * Initialize the dashboard hooks.
     */
    public function init() {
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
    }

    /**
     * Add the dashboard widget.
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'sad_workflow_deadlines_widget',
            'SAD Workflow Deadlines',
            array( $this, 'display_widget' )
        );
    }

    /**
     * Display the widget content.
     */
    public function display_widget() {
        $args = array(
            'post_type'      => 'scholarly_article',
            'posts_per_page' => 10,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'article_progress',
                    'field'    => 'name',
                    'terms'    => 'Warning:',
                    'compare'  => 'LIKE' // Taxonomy queries don't support LIKE natively for names easily, but we can fetch them
                ),
            ),
        );

        // Better way: get all warning terms
        $terms = get_terms( array(
            'taxonomy'   => 'article_progress',
            'name__like' => 'Warning:',
            'fields'     => 'ids'
        ) );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            echo '<p>No active workflow warnings.</p>';
            return;
        }

        $args = array(
            'post_type'      => 'scholarly_article',
            'posts_per_page' => 10,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'article_progress',
                    'field'    => 'term_id',
                    'terms'    => $terms,
                    'operator' => 'IN'
                ),
            ),
        );

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Article</th><th>Warning</th><th>Deadline</th></tr></thead>';
            echo '<tbody>';
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_terms = get_the_terms( $post_id, 'article_progress' );
                $warning_label = '';
                foreach ( $post_terms as $term ) {
                    if ( strpos( $term->name, 'Warning:' ) === 0 ) {
                        $warning_label = str_replace( 'Warning: ', '', $term->name );
                        break;
                    }
                }

                // Try to find matching deadline
                $deadline = 'N/A';
                $workflow_steps = array( 'grammarly', 'review_files', 'acceptance', 'early_view', 'ejournal', 'doi_send', 'dispatch' );
                foreach ( $workflow_steps as $step ) {
                    $dl = get_post_meta( $post_id, "_sad_step_{$step}_deadline", true );
                    if ( $dl ) {
                        $deadline = date( 'Y-m-d', strtotime( $dl ) );
                    }
                }

                printf(
                    '<tr><td><a href="%s">%s</a></td><td><span style="color:red; font-weight:bold;">%s</span></td><td>%s</td></tr>',
                    get_edit_post_link( $post_id ),
                    get_the_title(),
                    esc_html( $warning_label ),
                    esc_html( $deadline )
                );
            }
            echo '</tbody></table>';
            wp_reset_postdata();
        } else {
            echo '<p>No active workflow warnings.</p>';
        }
    }
}
