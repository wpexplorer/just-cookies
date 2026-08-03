<?php
/**
 * Network-wide settings REST endpoint (multisite).
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the shared network defaults to network administrators.
 */
class NetworkSettings {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;

		if ( ! Plugin::is_network_active() ) {
			return;
		}

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the network settings route.
	 */
	public function register_routes() {
		register_rest_route(
			'just-cookies/v1',
			'/network-settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_network_options' );
	}

	/**
	 * Returns the current network settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( $this->settings->network_option_raw() );
	}

	/**
	 * Saves the network settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$input = $request->get_param( 'settings' );
		$clean = $this->settings->sanitize( is_array( $input ) ? $input : array() );

		update_site_option( Settings::NETWORK_OPTION, $clean );

		return rest_ensure_response( $this->settings->network_option_raw() );
	}
}
