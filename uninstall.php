<?php
/**
 * Uninstall cleanup. Generated policy pages are left in place.
 *
 * Runs after the plugin is deactivated, so no plugin code is loaded here and
 * the option names are repeated rather than read from Settings.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

const UNINSTALL_OPTION         = 'just_cookies_settings';
const UNINSTALL_NETWORK_OPTION = 'just_cookies_network_settings';

/**
 * Removes the plugin option from a single site.
 */
function delete_plugin_data() {
	delete_option( UNINSTALL_OPTION );
}

/**
 * Removes plugin data from every site, plus the network option.
 */
function uninstall() {
	if ( ! is_multisite() ) {
		delete_plugin_data();
		return;
	}

	// Each site stores its own option.
	$offset = 0;

	do {
		$site_ids = get_sites(
			array(
				'fields'                 => 'ids',
				'number'                 => 100,
				'offset'                 => $offset,
				'update_site_meta_cache' => false,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			delete_plugin_data();
			restore_current_blog();
		}

		$offset += 100;
	} while ( count( $site_ids ) === 100 );

	delete_site_option( UNINSTALL_NETWORK_OPTION );
}

uninstall();
