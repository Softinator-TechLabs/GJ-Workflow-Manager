<?php
// Verification Script for SAD Workflow Manager - Enhanced Self-Healing (Backfilling Links)
// Usage: c:\xampp\php\php.exe c:\xampp\htdocs\outliny\wp-content\plugins\sad-workflow-manager\tests\verification_script.php

if ( ! defined( 'ABSPATH' ) ) {
    require_once( 'c:/xampp/htdocs/outliny/wp-load.php' );
}

function sad_log( $message ) {
    echo "[TEST] " . $message . "\n";
}

sad_log( "Starting Enhanced Self-Healing Verification..." );

// 1. Create Article & Ticket
$article_id = wp_insert_post( array( 
    'post_title' => 'Backfill Test Article', 
    'post_type' => 'scholarly_article',
    'post_status' => 'publish' 
) );

$ticket_id = wp_insert_post( array(
    'post_title'    => 'Backfill Test Ticket',
    'post_type'     => 'stt_ticket',
    'post_status'   => 'publish'
) );
update_post_meta( $ticket_id, '_stt_related_article', $article_id );
update_post_meta( $ticket_id, '_stt_status', 'open' );

// 2. Manually Add Tag WITHOUT related_id (Simulate Legacy Data)
wp_set_object_terms( $article_id, 'Ticket Created', 'article_progress' );
$term = get_term_by( 'name', 'Ticket Created', 'article_progress' );

// Manually set old meta format
$old_meta = array(
    $term->term_id => array(
        'reason' => 'Legacy Metadata',
        'type' => 'ticket',
        // related_id MISSING
    )
);
update_post_meta( $article_id, '_sad_tag_reasons', $old_meta );

sad_log( "Setup: Article #$article_id linked to #$ticket_id. Tag exists but NO related_id." );

// 3. Simulate Admin List View rendering
$admin = new SAD_Workflow_Admin( 'sad-workflow-manager', '1.0.0' );
$post_states = array();
$post_obj = get_post( $article_id );

// This triggers Enhanced Self-Healing
$new_states = $admin->add_progress_tags_to_title( $post_states, $post_obj );

// 4. Verify Backfill (Persistence)
$new_meta = get_post_meta( $article_id, '_sad_tag_reasons', true );
if ( isset( $new_meta[ $term->term_id ]['related_id'] ) && $new_meta[ $term->term_id ]['related_id'] == $ticket_id ) {
    sad_log( "PASS: Meta 'related_id' was backfilled correctly." );
} else {
    sad_log( "FAIL: Meta 'related_id' was NOT backfilled." );
    print_r( $new_meta );
}

// 5. Verify HTML Link
$html_found = false;
foreach ( $new_states as $key => $html ) {
    if ( strpos( $html, 'Ticket Created' ) !== false ) {
        // We know get_post_meta is called again in the display loop, so it should pick up the backfilled ID.
        // Wait, does it? logic says $tag_reasons = get_post_meta... inside the if(!empty($terms)) block.
        // And the update happened before that. So yes.
        
        if ( strpos( $html, '<a href="' ) !== false && strpos( $html, "post=$ticket_id" ) !== false ) {
            sad_log( "PASS: Tag is now a link." );
        } else {
             sad_log( "FAIL: Tag is NOT a link. HTML: " . htmlspecialchars( $html ) );
        }
        $html_found = true;
        break;
    }
}

if ( ! $html_found ) {
    sad_log( "FAIL: Tag HTML not found?" );
}


// Cleanup
wp_delete_post( $article_id, true );
wp_delete_post( $ticket_id, true );

sad_log( "\nBackfill Verification Complete." );
