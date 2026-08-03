<?php
/**
 * Admin settings pages (React panel host) for site and network scopes.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings pages and mounts the React app.
 */
class Admin {

	const SLUG = 'just-cookies-settings';

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

		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'network_admin_menu', array( $this, 'register_network_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_reset_route' ) );

		$basename = plugin_basename( PLUGIN_FILE );
		add_filter( "plugin_action_links_{$basename}", array( $this, 'add_settings_action_link' ) );
		add_filter( "network_admin_plugin_action_links_{$basename}", array( $this, 'add_network_settings_action_link' ) );
	}

	/**
	 * Adds a Settings link to the plugins list row.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_settings_action_link( $links ) {
		// Network-activated installs are configured in the network admin.
		if ( Plugin::is_network_active() ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
				esc_html__( 'Settings', 'just-cookies' )
			)
		);

		return $links;
	}

	/**
	 * Adds a Settings link to the network plugins list row.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_network_settings_action_link( $links ) {
		if ( ! Plugin::is_network_active() ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( network_admin_url( 'settings.php?page=' . self::SLUG ) ),
				esc_html__( 'Settings', 'just-cookies' )
			)
		);

		return $links;
	}

	/**
	 * Registers the settings reset endpoint.
	 */
	public function register_reset_route() {
		register_rest_route(
			'just-cookies/v1',
			'/reset-settings',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_settings' ),
				'permission_callback' => function ( \WP_REST_Request $request ) {
					return $request->get_param( 'network' )
						? current_user_can( 'manage_network_options' )
						: current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Deletes the stored option so everything reverts to inherited defaults.
	 *
	 * The consent revision is bumped rather than cleared: a reset changes what
	 * visitors consented to, so their stored consent is stale and everyone is
	 * re-prompted.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function reset_settings( \WP_REST_Request $request ) {
		if ( $request->get_param( 'network' ) ) {
			$stored   = get_site_option( Settings::NETWORK_OPTION, array() );
			$revision = is_array( $stored ) && ! empty( $stored['revision'] ) ? absint( $stored['revision'] ) : 0;

			delete_site_option( Settings::NETWORK_OPTION );
			update_site_option( Settings::NETWORK_OPTION, array( 'revision' => $revision + 1 ) );
		} else {
			$stored   = get_option( Settings::OPTION, array() );
			$revision = is_array( $stored ) && ! empty( $stored['revision'] ) ? absint( $stored['revision'] ) : 0;

			delete_option( Settings::OPTION );

			// The revision is network-wide on a network-activated subsite.
			if ( ! Plugin::is_network_active() ) {
				update_option( Settings::OPTION, array( 'revision' => $revision + 1 ) );
			}
		}

		return rest_ensure_response( array( 'revision' => $revision + 1 ) );
	}

	/**
	 * Adds the per-site Settings submenu page. When network-activated this page
	 * exposes only the site's presentation settings, and is left out entirely
	 * when the network locks those too.
	 */
	public function register_settings_page() {
		if ( $this->settings->site_settings_locked() ) {
			return;
		}

		add_options_page(
			__( 'Just Cookies', 'just-cookies' ),
			__( 'Just Cookies', 'just-cookies' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Adds the network Settings submenu page (network-activated multisite).
	 */
	public function register_network_settings_page() {
		if ( ! Plugin::is_network_active() ) {
			return;
		}

		add_submenu_page(
			'settings.php',
			__( 'Just Cookies', 'just-cookies' ),
			__( 'Just Cookies', 'just-cookies' ),
			'manage_network_options',
			self::SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Renders the title and the mount point.
	 *
	 * The title is printed here rather than by the panel so it is on screen
	 * while the settings request is still in flight.
	 */
	public function render_settings_page() {
		$title = is_network_admin()
			? __( 'Just Cookies — Network Defaults', 'just-cookies' )
			: __( 'Just Cookies', 'just-cookies' );

		printf(
			'<div class="wrap"><h1>%s</h1><hr class="wp-header-end"><div id="just-cookies-admin-root"></div></div>',
			esc_html( $title )
		);
	}

	/**
	 * Enqueues the bundle for whichever plugin screen is open. The two screens
	 * are separate entries, so neither carries the other's code.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$hook = (string) $hook;

		if ( str_contains( $hook, self::SLUG ) ) {
			$this->enqueue_bundle( 'admin-settings', $this->settings_data() );
		}
	}

	/**
	 * Enqueues one built entry and inlines its data payload.
	 *
	 * @param string $entry Build entry name.
	 * @param array  $data  Data exposed as window.justCookiesAdmin.
	 */
	private function enqueue_bundle( $entry, array $data ) {
		$asset_file = PLUGIN_DIR . "build/{$entry}.asset.php";
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset  = require $asset_file;
		$handle = 'just-cookies-' . $entry;

		wp_enqueue_script(
			$handle,
			PLUGIN_URL . "build/{$entry}.js",
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		if ( file_exists( PLUGIN_DIR . "build/{$entry}.css" ) ) {
			wp_enqueue_style(
				$handle,
				PLUGIN_URL . "build/{$entry}.css",
				array( 'wp-components' ),
				$asset['version']
			);
		}

		wp_add_inline_script(
			$handle,
			'window.justCookiesAdmin = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Settings panel payload.
	 *
	 * @return array
	 */
	private function settings_data() {
		$is_network = is_network_admin();
		$limited    = ( ! $is_network && Plugin::is_network_active() );

		$integrations = Plugin::instance()->integrations;

		// Limited to plugins whose files are present on the server.
		$installed = $integrations->installed_keys();
		$labels    = $integrations->labels();

		$choices = array();
		foreach ( $installed as $key ) {
			$choices[] = array(
				'key'    => $key,
				'label'  => isset( $labels[ $key ] ) ? $labels[ $key ] : $key,
				'active' => $integrations->is_active( $key ),
			);
		}

		$provider_labels = Plugin::instance()->cookie_tables->provider_labels();
		$providers       = array();
		foreach ( $this->settings->embed_provider_catalog() as $key ) {
			$providers[] = array(
				'key'   => $key,
				'label' => isset( $provider_labels[ $key ] ) ? $provider_labels[ $key ] : ucfirst( $key ),
			);
		}

		return array(
			'mode'         => $is_network ? 'network' : 'site',
			'version'      => VERSION,
			'integrations' => $choices,
			'embeds'       => $providers,
			// Fallback for unset fields: network config on a subsite, else defaults.
			'defaults'     => $this->settings->with_default_text(
				$limited ? $this->settings->network_settings() : $this->settings->defaults()
			),
			'limitedTo'    => $limited ? $this->settings->per_site_keys() : null,
			'pages'        => $this->policy_page_choices(),
		);
	}

	/**
	 * Published pages on the site that owns the policy pages, for the
	 * policy page dropdowns.
	 *
	 * @return array[] Arrays of id/title.
	 */
	private function policy_page_choices() {
		$site_id  = $this->settings->network_owns_policy_pages() ? get_main_site_id() : get_current_blog_id();
		$switched = false;

		if ( get_current_blog_id() !== $site_id ) {
			switch_to_blog( $site_id );
			$switched = true;
		}

		$pages   = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) );
		$choices = array();

		foreach ( (array) $pages as $page ) {
			$choices[] = array(
				'id'    => (string) $page->ID,
				'title' => $page->post_title ? $page->post_title : '#' . $page->ID,
			);
		}

		if ( $switched ) {
			restore_current_blog();
		}

		return $choices;
	}
}
