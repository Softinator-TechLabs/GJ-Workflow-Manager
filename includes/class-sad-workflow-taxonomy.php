<?php

class SAD_Workflow_Taxonomy {

    public function register_article_progress_taxonomy() {

        $labels = array(
            'name'                       => _x( 'Article Progress', 'Taxonomy General Name', 'sad-workflow-manager' ),
            'singular_name'              => _x( 'Article Progress', 'Taxonomy Singular Name', 'sad-workflow-manager' ),
            'menu_name'                  => __( 'Article Progress', 'sad-workflow-manager' ),
            'all_items'                  => __( 'All Progress Tags', 'sad-workflow-manager' ),
            'parent_item'                => __( 'Parent Item', 'sad-workflow-manager' ),
            'parent_item_colon'          => __( 'Parent Item:', 'sad-workflow-manager' ),
            'new_item_name'              => __( 'New Progress Tag Name', 'sad-workflow-manager' ),
            'add_new_item'               => __( 'Add New Progress Tag', 'sad-workflow-manager' ),
            'edit_item'                  => __( 'Edit Progress Tag', 'sad-workflow-manager' ),
            'update_item'                => __( 'Update Progress Tag', 'sad-workflow-manager' ),
            'view_item'                  => __( 'View Progress Tag', 'sad-workflow-manager' ),
            'separate_items_with_commas' => __( 'Separate tags with commas', 'sad-workflow-manager' ),
            'add_or_remove_items'        => __( 'Add or remove tags', 'sad-workflow-manager' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'sad-workflow-manager' ),
            'popular_items'              => __( 'Popular Tags', 'sad-workflow-manager' ),
            'search_items'               => __( 'Search Tags', 'sad-workflow-manager' ),
            'not_found'                  => __( 'Not Found', 'sad-workflow-manager' ),
            'no_terms'                   => __( 'No tags', 'sad-workflow-manager' ),
            'items_list'                 => __( 'Tags list', 'sad-workflow-manager' ),
            'items_list_navigation'      => __( 'Tags list navigation', 'sad-workflow-manager' ),
        );

        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => false,
            'public'                     => false, // Hidden from frontend
            'show_ui'                    => true,  // Visible in admin
            'show_admin_column'          => true,  // Show in post list table
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'rewrite'                    => false,
            'meta_box_cb'                => false, // Hide default metabox to prevent manual editing
        );

        register_taxonomy( 'article_progress', array( 'scholarly_article' ), $args );

    }

}
