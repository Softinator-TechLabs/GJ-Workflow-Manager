<?php
// Verification Script for SAD Workflow Manager - Ticket Closure Logic
// Usage: c:\xampp\php\php.exe c:\xampp\htdocs\outliny\wp-content\plugins\sad-workflow-manager\tests\verification_ticket_closure.php

if ( ! defined( 'ABSPATH' ) ) {
    require_once( 'c:/xampp/htdocs/outliny/wp-load.php' );
}

function sad_log( $message ) {
    echo "[TEST] " . $message . "\n";
}

sad_log( "Starting Ticket Closure Verification..." );

// 1. Create Article
$article_id = wp_insert_post( array( 
    'post_title' => 'Closure Test Article', 
    'post_type' => 'scholarly_article',
    'post_status' => 'publish' 
) );

// 2. Create Two Open Tickets linked to Article
$ticket1_id = wp_insert_post( array(
    'post_title'    => 'Closure Test Ticket 1',
    'post_type'     => 'stt_ticket',
    'post_status'   => 'publish'
) );
update_post_meta( $ticket1_id, '_stt_related_article', $article_id );
update_post_meta( $ticket1_id, '_stt_status', 'open' );

$ticket2_id = wp_insert_post( array(
    'post_title'    => 'Closure Test Ticket 2',
    'post_type'     => 'stt_ticket',
    'post_status'   => 'publish'
) );
update_post_meta( $ticket2_id, '_stt_related_article', $article_id );
update_post_meta( $ticket2_id, '_stt_status', 'open' );

// Trigger Ticket Save to Apply Tags
// Note: We need to trigger the hook manually or save the post.
// wp_insert_post triggers save_post, but we updated meta separately.
// Let's trigger save_post explicitly for Ticket 1 to ensure tag is added.
do_action( 'save_post', $ticket1_id, get_post( $ticket1_id ), true );

// Verify Tag Exists
$terms = wp_get_object_terms( $article_id, 'article_progress' );
$term_names = wp_list_pluck( $terms, 'name' );
if ( in_array( 'Ticket Created', $term_names ) ) {
    sad_log( "Setup PASS: Tag added initially." );
} else {
    sad_log( "Setup FAIL: Tag NOT added initially." );
}

// 3. Close Ticket 1 (Tag should remain because Ticket 2 is open)
update_post_meta( $ticket1_id, '_stt_status', 'resolved' );
// Simulate AJAX status change hook
do_action( 'stt_ticket_status_changed', $ticket1_id, 'open', 'resolved', 1 );

$terms = wp_get_object_terms( $article_id, 'article_progress' );
$term_names = wp_list_pluck( $terms, 'name' );
if ( in_array( 'Ticket Created', $term_names ) ) {
    sad_log( "PASS: Tag preserved after closing Ticket 1 (Ticket 2 still open)." );
} else {
    sad_log( "FAIL: Tag removed prematurely." );
}

// 4. Close Ticket 2 (Tag should be removed)
update_post_meta( $ticket2_id, '_stt_status', 'closed' );
// Simulate AJAX status change hook
do_action( 'stt_ticket_status_changed', $ticket2_id, 'open', 'closed', 1 );

$terms = wp_get_object_terms( $article_id, 'article_progress' );
$term_names = wp_list_pluck( $terms, 'name' );
if ( ! in_array( 'Ticket Created', $term_names ) ) {
    sad_log( "PASS: Tag removed after closing Ticket 2 (No open tickets)." );
} else {
    sad_log( "FAIL: Tag NOT removed after all tickets closed." );
}

// Cleanup
wp_delete_post( $article_id, true );
wp_delete_post( $ticket1_id, true );
wp_delete_post( $ticket2_id, true );

sad_log( "\nClosure Verification Complete." );
