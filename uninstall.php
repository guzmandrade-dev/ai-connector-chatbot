<?php
/**
 * Uninstall handler — removes all plugin data.
 *
 * @package AI_Connector_Chatbot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete the settings option.
delete_option( 'aicc_settings' );

// Delete rate-limit transients.
//
// There is no WordPress API for deleting transients by prefix, so a direct
// query is required. It runs once, during uninstall, so caching does not apply.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE %s
		    OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_aicc_rate_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_aicc_rate_' ) . '%'
	)
);

// Delete all knowledge base articles.
$aicc_kb_posts = get_posts(
	array(
		'post_type'      => 'aicc_article',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $aicc_kb_posts as $aicc_post_id ) {
	wp_delete_post( $aicc_post_id, true );
}
