<?php
/**
 * Bootstrap loader.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Loads plugin components and wires shared hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings component.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Cookie tables component.
	 *
	 * @var CookieTables
	 */
	public $cookie_tables;

	/**
	 * Plugin integration registry.
	 *
	 * @var Integrations
	 */
	public $integrations;

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers components. Classes load on demand through autoload.php.
	 */
	private function __construct() {
		$this->settings      = new Settings();
		$this->integrations  = new Integrations();
		$this->cookie_tables = new CookieTables( $this->settings );

		$config = new ConsentConfig( $this->settings, $this->cookie_tables );

		new Embeds( $this->settings );
		new Banner( $this->settings, $config );
		new Shortcodes( $this->settings, $this->cookie_tables );
		new NetworkSettings( $this->settings );
		new PolicyPages( $this->settings );
		new Analytics( $this->settings );
		new Admin( $this->settings );

	}

	/**
	 * Whether the plugin is network-activated on multisite. When true, the
	 * plugin is configured network-wide; otherwise it is configured per-site.
	 *
	 * @return bool
	 */
	public static function is_network_active() {
		if ( ! is_multisite() ) {
			return false;
		}
		$plugins = get_site_option( 'active_sitewide_plugins', array() );
		return isset( $plugins[ plugin_basename( PLUGIN_FILE ) ] );
	}

	/**
	 * Whether this is an admin screen.
	 *
	 * is_admin() is true for admin-ajax.php as well, which is where themes
	 * commonly render front-end content — "load more" listings above all. Those
	 * responses carry embeds that have to be blocked like any other, so AJAX is
	 * treated as a front-end request.
	 *
	 * @return bool
	 */
	public static function is_admin_request() {
		return is_admin() && ! wp_doing_ajax();
	}

	/**
	 * Whether the current request is an admin screen or a builder/preview
	 * context where the banner and embed blocking should not run.
	 *
	 * @return bool
	 */
	public static function is_editing_context() {
		if ( self::is_admin_request() ) {
			return true;
		}
		if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
			return true; // WPBakery front-end editor.
		}
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}
		return false;
	}
}
