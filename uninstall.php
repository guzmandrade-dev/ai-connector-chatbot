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
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_aicc_rate_' ) . '%'
	)
);
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_aicc_rate_' ) . '%'
	)
);

// Delete all knowledge base articles.
$kb_posts = get_posts( [
	'post_type'      => 'aicc_article',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
] );

foreach ( $kb_posts as $post_id ) {
	wp_delete_post( $post_id, true );
}