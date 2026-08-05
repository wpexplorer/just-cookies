<?php
/**
 * Settings model and REST registration.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Stores plugin settings in a single option, exposed to the block editor
 * settings endpoint (/wp/v2/settings) for the React admin panel.
 */
class Settings {

	const OPTION         = 'just_cookies_settings';
	const NETWORK_OPTION = 'just_cookies_network_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Hooks registration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_option' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			// Off until configured: a banner shown on activation would collect
			// consent against placeholder wording and unlinked policy pages.
			'enabled'                    => false,
			'layout'                     => 'box',         // box | cloud | bar.
			'position'                   => 'bottom left', // <vertical> <horizontal>.
			'lock_overlay'               => false,         // Dark backdrop that blocks the page until a choice is made.
			'dark_mode'                  => false,
			'primary_button_bg'          => '',            // Hex; blank keeps the CookieConsent default.
			'primary_button_hover_bg'    => '',            // Hex; blank keeps the CookieConsent default.
			'expires_days'               => 182,
			'revision'                   => 0,
			'network_policy_pages'       => true,          // Multisite: all sites share the main site's policy pages.
			'network_lock_site_settings' => false,         // Multisite: subsites cannot change their own appearance.
			// '' = auto-detect by slug, numeric = page ID, 'custom' = use the URL below.
			'policy_page'                => '',
			'privacy_page'               => '',
			'terms_page'                 => '',
			'policy_url'                 => '',
			'privacy_url'                => '',
			'terms_url'                  => '',
			// Which of the three the banner links to; the rest are still reachable
			// wherever the site links them itself.
			'banner_links'               => array( 'cookie', 'privacy', 'terms' ),
			'banner_links_new_tab'       => true,
			// Blank = use the wording in default_text().
			'banner_title'               => '',
			'banner_description'         => '',
			'ack_button_label'           => '',
			// Optional closing section in the preferences popup.
			'prefs_extra'                => false,
			'prefs_extra_title'          => '',
			'prefs_extra_text'           => '',
			'disclose_cloudflare'        => false,
			'disclose_recaptcha'         => false,
			'disclose_stripe'            => false,
			'disclose_turnstile'         => false,
			// On by default: WooCommerce ships Order Attribution enabled, and the
			// per-site feature switch cannot be read for a network-wide table.
			'disclose_order_attribution' => true,
			// Detection covers the current site only.
			'auto_detect_plugins'        => true,
			'disclose_plugins'           => array(),       // Integration keys, used when auto detect is off.
			'disclose_login'             => false,
			'disclose_admin'             => false,         // Adds the wp-admin screen preference cookies.
			'block_analytics'            => false,         // Hold analytics scripts until consent.
			'block_embeds'               => false,
			'embed_providers'            => array(),       // Provider keys blocked; empty blocks nothing.
			'embed_content_filters'      => '',            // Extra filter hooks scanned for blockable content, one per line.
			'embed_notice'               => '',
			'float_button'               => true,
			'float_button_position'      => 'bottom left', // <vertical> <horizontal>.
			'float_button_label'         => '',
			'float_button_bg'            => '',            // Hex; blank uses the CSS default.
			'float_button_color'         => '',            // Hex; blank uses the CSS default.
			'float_button_z_index'       => 1000,          // Stacking order; matches Banner::DEFAULT_BUTTON_LAYER.
		);
	}

	/**
	 * Wording used for the text settings a site has not written itself.
	 *
	 * Separate from defaults() because these are translated: defaults() runs
	 * during plugin load, and a translation may not be loaded before init.
	 *
	 * @return string[]
	 */
	private function default_text() {
		return array(
			'banner_title'       => __( 'We use cookies', 'just-cookies' ),
			// One sentence that holds whatever is switched on, rather than
			// wording assembled from the settings. A site wanting to describe
			// its own setup replaces the whole thing under Banner.
			'banner_description' => __( 'This site uses cookies to work properly. Anything optional is only used with your consent.', 'just-cookies' ),
			'ack_button_label'   => __( 'Got it', 'just-cookies' ),
			'embed_notice'       => __( 'This content is hosted by {provider}. Loading it may set cookies. Accept to view.', 'just-cookies' ),
			// With nothing to set, the popup is a disclosure rather than a
			// preference screen, and its title says so too.
			'float_button_label' => $this->has_optional_categories()
				? __( 'Cookie settings', 'just-cookies' )
				: __( 'Cookie details', 'just-cookies' ),
		);
	}

	/**
	 * Whether the visitor has anything to decide.
	 *
	 * Drives the difference between a real accept/reject banner and a plain
	 * acknowledgement, and the wording that goes with each. Analytics counts
	 * even with no embeds: gating it creates a category to accept or refuse.
	 *
	 * @return bool
	 */
	public function has_optional_categories() {
		return (bool) $this->enabled_providers() || (bool) $this->get( 'block_analytics' );
	}

	/**
	 * Fills blank text values with the wording they fall back to, so the
	 * settings screen can show it as a placeholder. Applied to whichever level
	 * a screen inherits from — plugin defaults, or the network's settings.
	 *
	 * @param array $settings Resolved settings.
	 * @return array
	 */
	public function with_default_text( array $settings ) {
		foreach ( $this->default_text() as $key => $string ) {
			if ( ! isset( $settings[ $key ] ) || '' === $settings[ $key ] ) {
				$settings[ $key ] = $string;
			}
		}

		return $settings;
	}

	/**
	 * A user-facing text setting, falling back to the translated wording.
	 *
	 * Anything a site has written is returned verbatim, in whatever language it
	 * was written in.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public function text( $key ) {
		$value = (string) $this->get( $key );

		if ( '' !== $value ) {
			return $value;
		}

		$strings = $this->default_text();

		return isset( $strings[ $key ] ) ? $strings[ $key ] : '';
	}

	/**
	 * REST/option schema properties.
	 *
	 * @return array
	 */
	private function schema_properties() {
		return array(
			'enabled'                    => array( 'type' => 'boolean' ),
			'layout'                     => array( 'type' => 'string' ),
			'position'                   => array( 'type' => 'string' ),
			'lock_overlay'               => array( 'type' => 'boolean' ),
			'dark_mode'                  => array( 'type' => 'boolean' ),
			'primary_button_bg'          => array( 'type' => 'string' ),
			'primary_button_hover_bg'    => array( 'type' => 'string' ),
			'expires_days'               => array( 'type' => 'integer' ),
			'revision'                   => array( 'type' => 'integer' ),
			'network_policy_pages'       => array( 'type' => 'boolean' ),
			'network_lock_site_settings' => array( 'type' => 'boolean' ),
			'policy_page'                => array( 'type' => 'string' ),
			'privacy_page'               => array( 'type' => 'string' ),
			'terms_page'                 => array( 'type' => 'string' ),
			'policy_url'                 => array( 'type' => 'string' ),
			'privacy_url'                => array( 'type' => 'string' ),
			'terms_url'                  => array( 'type' => 'string' ),
			'banner_links'               => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'banner_links_new_tab'       => array( 'type' => 'boolean' ),
			'banner_title'               => array( 'type' => 'string' ),
			'banner_description'         => array( 'type' => 'string' ),
			'ack_button_label'           => array( 'type' => 'string' ),
			'prefs_extra'                => array( 'type' => 'boolean' ),
			'prefs_extra_title'          => array( 'type' => 'string' ),
			'prefs_extra_text'           => array( 'type' => 'string' ),
			'disclose_cloudflare'        => array( 'type' => 'boolean' ),
			'disclose_recaptcha'         => array( 'type' => 'boolean' ),
			'disclose_stripe'            => array( 'type' => 'boolean' ),
			'disclose_turnstile'         => array( 'type' => 'boolean' ),
			'disclose_order_attribution' => array( 'type' => 'boolean' ),
			'auto_detect_plugins'        => array( 'type' => 'boolean' ),
			'disclose_plugins'           => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'disclose_login'             => array( 'type' => 'boolean' ),
			'disclose_admin'             => array( 'type' => 'boolean' ),
			'block_analytics'            => array( 'type' => 'boolean' ),
			'block_embeds'               => array( 'type' => 'boolean' ),
			'embed_providers'            => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'embed_content_filters'      => array( 'type' => 'string' ),
			'embed_notice'               => array( 'type' => 'string' ),
			'float_button'               => array( 'type' => 'boolean' ),
			'float_button_position'      => array( 'type' => 'string' ),
			'float_button_label'         => array( 'type' => 'string' ),
			'float_button_bg'            => array( 'type' => 'string' ),
			'float_button_color'         => array( 'type' => 'string' ),
			'float_button_z_index'       => array( 'type' => 'integer' ),
		);
	}

	/**
	 * Keys a subsite may set for itself when network-activated.
	 *
	 * Limited to presentation and site-local behavior: nothing here affects
	 * consent state, so it can safely differ between sites sharing a cookie.
	 *
	 * @return string[]
	 */
	public function per_site_keys() {
		if ( $this->site_settings_locked() ) {
			return array();
		}

		$keys = array(
			'layout',
			'position',
			'dark_mode',
			'lock_overlay',
			'primary_button_bg',
			'primary_button_hover_bg',
			'float_button',
			'float_button_position',
			'float_button_label',
			'float_button_bg',
			'float_button_color',
			'float_button_z_index',
			'prefs_extra',
			'prefs_extra_title',
			'prefs_extra_text',
		);

		// When the network doesn't own the policy pages, each site links its own.
		if ( ! $this->network_owns_policy_pages() ) {
			$keys = array_merge( $keys, array( 'policy_url', 'privacy_url', 'terms_url' ) );
		}

		/**
		 * Filters the settings a subsite can override.
		 *
		 * @param string[] $keys Setting keys.
		 */
		return apply_filters( 'just_cookies_per_site_keys', $keys );
	}

	/**
	 * Whether subsites are barred from changing anything for themselves.
	 *
	 * Reads the network option directly: per_site_keys() feeds all(), so this
	 * must not resolve settings through it.
	 *
	 * @return bool
	 */
	public function site_settings_locked() {
		if ( ! Plugin::is_network_active() ) {
			return false;
		}

		$stored = $this->network_option_raw();
		return ! empty( $stored['network_lock_site_settings'] );
	}

	/**
	 * Whether policy pages live on the main site and are shared network-wide.
	 *
	 * Reads the network option directly: per_site_keys() feeds all(), so this
	 * must not resolve settings through it.
	 *
	 * @return bool
	 */
	public function network_owns_policy_pages() {
		if ( ! Plugin::is_network_active() ) {
			return false;
		}

		$stored = $this->network_option_raw();
		return array_key_exists( 'network_policy_pages', $stored )
			? (bool) $stored['network_policy_pages']
			: true;
	}

	/**
	 * Registers the per-site option with the settings REST endpoint.
	 *
	 * When network-activated the schema is reduced to the per-site keys, so a
	 * subsite can only change its own presentation.
	 */
	public function register_option() {
		$properties = $this->schema_properties();

		if ( Plugin::is_network_active() ) {
			$properties = array_intersect_key( $properties, array_flip( $this->per_site_keys() ) );
		}

		register_setting(
			'options',
			self::OPTION,
			array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_site_option' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'properties'           => $properties,
						'additionalProperties' => false,
					),
				),
			)
		);
	}

	/**
	 * Sanitizes the per-site option, dropping keys a subsite may not set.
	 *
	 * @param mixed $input Raw value.
	 * @return array
	 */
	public function sanitize_site_option( $input ) {
		$out = $this->sanitize( $input );

		if ( ! Plugin::is_network_active() ) {
			return $out;
		}

		return array_intersect_key( $out, array_flip( $this->per_site_keys() ) );
	}

	/**
	 * Constrains a value to a fixed set of choices, or blank to inherit.
	 *
	 * @param mixed    $value   Submitted value.
	 * @param string[] $allowed Accepted values.
	 * @return string
	 */
	private function one_of( $value, $allowed ) {
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Sanitizes an incoming settings object.
	 *
	 * A key is stored because it was submitted, never because its value differed
	 * from something. Keys left out are left alone, so saving one setting cannot
	 * pin the rest, and a later change to a default still reaches everyone who
	 * never chose for themselves. Blank is submitted to mean "inherit" and
	 * stores nothing.
	 *
	 * @param mixed $input Raw value.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();

		foreach ( array_keys( $this->defaults() ) as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = $input[ $key ];

			switch ( $key ) {
				case 'enabled':
				case 'network_lock_site_settings':
				case 'banner_links_new_tab':
				case 'prefs_extra':
				case 'network_policy_pages':
				case 'lock_overlay':
				case 'dark_mode':
				case 'disclose_cloudflare':
				case 'disclose_recaptcha':
				case 'disclose_stripe':
				case 'disclose_turnstile':
				case 'disclose_order_attribution':
				case 'auto_detect_plugins':
				case 'disclose_login':
				case 'disclose_admin':
				case 'block_analytics':
				case 'block_embeds':
				case 'float_button':
					$out[ $key ] = (bool) $value;
					break;

				case 'float_button_bg':
				case 'float_button_color':
				case 'primary_button_bg':
				case 'primary_button_hover_bg':
					$value = (string) $value;
					// Trim an alpha pair (#rrggbbaa) the color picker may emit.
					if ( preg_match( '/^#([0-9a-fA-F]{6})[0-9a-fA-F]{2}$/', $value, $hex ) ) {
						$value = '#' . $hex[1];
					}
					$out[ $key ] = (string) sanitize_hex_color( $value );
					break;

				case 'expires_days':
					$out[ $key ] = max( 1, absint( $value ) );
					break;

				case 'float_button_z_index':
					// Capped at the CSS maximum; anything higher is invalid and
					// the browser would drop the declaration. Clamped rather
					// than absint()ed so a negative does not flip to positive.
					$out[ $key ] = min( 2147483647, max( 0, (int) $value ) );
					break;

				case 'revision':
					$out[ $key ] = absint( $value );
					break;

				case 'policy_url':
				case 'privacy_url':
				case 'terms_url':
					$out[ $key ] = esc_url_raw( (string) $value );
					break;

				case 'policy_page':
				case 'privacy_page':
				case 'terms_page':
					$value = (string) $value;
					if ( 'custom' === $value ) {
						$out[ $key ] = 'custom';
					} elseif ( is_numeric( $value ) && (int) $value > 0 ) {
						$out[ $key ] = (string) absint( $value );
					} else {
						$out[ $key ] = '';
					}
					break;

				// Blank is kept as-is so the choice falls through to the level
				// above; anything unrecognized becomes blank for the same reason.
				case 'layout':
					$out[ $key ] = $this->one_of( $value, array( 'box', 'cloud', 'bar' ) );
					break;

				case 'position':
					$out[ $key ] = $this->one_of(
						$value,
						array(
							'bottom left',
							'bottom center',
							'bottom right',
							'top left',
							'top center',
							'top right',
							'middle center',
						)
					);
					break;

				case 'float_button_position':
					$out[ $key ] = $this->one_of( $value, array( 'bottom left', 'bottom right' ) );
					break;

				case 'prefs_extra_title':
					$out[ $key ] = sanitize_text_field( (string) $value );
					break;

				case 'prefs_extra_text':
				case 'banner_description':
				case 'embed_notice':
					$out[ $key ] = wp_kses_post( (string) $value );
					break;

				case 'disclose_plugins':
					$keys        = array_keys( Plugin::instance()->integrations->all() );
					$out[ $key ] = array_values(
						array_intersect( array_map( 'sanitize_key', (array) $value ), $keys )
					);
					break;

				case 'banner_links':
					$out[ $key ] = array_values(
						array_intersect(
							array_map( 'sanitize_key', (array) $value ),
							array( 'cookie', 'privacy', 'terms' )
						)
					);
					break;

				case 'embed_providers':
					$out[ $key ] = array_values(
						array_intersect(
							array_map( 'sanitize_key', (array) $value ),
							$this->embed_provider_catalog()
						)
					);
					break;

				case 'embed_content_filters':
					// One hook name per line; strip anything not valid in one.
					$lines = preg_split( '/[\r\n,]+/', (string) $value );
					$lines = array_filter( array_map( fn( $l ) => preg_replace( '/[^a-zA-Z0-9_\-\/\.]/', '', trim( $l ) ), $lines ) );
					$out[ $key ] = implode( "\n", $lines );
					break;

				default:
					$out[ $key ] = sanitize_text_field( (string) $value );
					break;
			}

			// Blank means inherit, so nothing is stored for it.
			if ( '' === $out[ $key ] ) {
				unset( $out[ $key ] );
			}
		}

		$this->cache = null;
		return $out;
	}

	/**
	 * Raw stored network option. Keys left blank are absent.
	 *
	 * @return array
	 */
	public function network_option_raw() {
		$stored = is_multisite() ? get_site_option( self::NETWORK_OPTION, array() ) : array();
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Network-wide defaults (multisite), merged over plugin defaults.
	 *
	 * @return array
	 */
	public function network_settings() {
		$stored = is_multisite() ? get_site_option( self::NETWORK_OPTION, array() ) : array();
		$stored = is_array( $stored ) ? $stored : array();
		return wp_parse_args( $stored, $this->defaults() );
	}

	/**
	 * Returns the effective settings.
	 *
	 * Network-activated installs read the network option; otherwise the site
	 * option. Missing values fall back to the plugin defaults.
	 *
	 * @return array
	 */
	public function all() {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		if ( Plugin::is_network_active() ) {
			// Network config, with this site's presentation settings layered on.
			$site = get_option( self::OPTION, array() );
			$site = is_array( $site ) ? $site : array();
			$site = array_intersect_key( $site, array_flip( $this->per_site_keys() ) );

			$this->cache = array_merge( $this->network_settings(), $site );
			return $this->cache;
		}

		$stored      = get_option( self::OPTION, array() );
		$stored      = is_array( $stored ) ? $stored : array();
		$this->cache = wp_parse_args( $stored, $this->defaults() );
		return $this->cache;
	}

	/**
	 * Returns a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Effective Cookie Policy URL, falling back to a default page.
	 *
	 * @return string
	 */
	public function policy_url() {
		return $this->resolve_policy_link( 'policy_page', 'policy_url', $this->policy_slugs( 'cookie' ) );
	}

	/**
	 * Effective Privacy Policy URL.
	 *
	 * Falls back to the page configured in Settings → Privacy before guessing.
	 *
	 * @return string
	 */
	public function privacy_url() {
		$url = $this->resolve_policy_link( 'privacy_page', 'privacy_url', '' );
		if ( $url ) {
			return $url;
		}

		$core = get_privacy_policy_url();
		return $core ? $core : $this->default_url( $this->policy_slugs( 'privacy' ) );
	}

	/**
	 * Effective Terms of Service URL, falling back to a default page.
	 *
	 * @return string
	 */
	public function terms_url() {
		return $this->resolve_policy_link( 'terms_page', 'terms_url', $this->policy_slugs( 'terms' ) );
	}

	/**
	 * Resolves a policy link from its page/url setting pair.
	 *
	 * The page setting is '' (auto-detect by slug), a page ID, or 'custom'
	 * (use the stored URL). Never returns a link to a missing/unpublished page.
	 *
	 * @param string $page_key Page setting key.
	 * @param string $url_key  URL setting key.
	 * @param string|string[] $slug Auto-detect slug(s), or '' to skip detection.
	 * @return string
	 */
	private function resolve_policy_link( $page_key, $url_key, $slug ) {
		$page = (string) $this->get( $page_key );

		if ( 'custom' === $page ) {
			return (string) $this->get( $url_key );
		}

		if ( is_numeric( $page ) ) {
			return $this->page_permalink( (int) $page );
		}

		return $slug ? $this->default_url( $slug ) : '';
	}

	/**
	 * Permalink for a published page, on the site that owns policy pages.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	private function page_permalink( $page_id ) {
		$site_id  = $this->network_owns_policy_pages() ? get_main_site_id() : get_current_blog_id();
		$switched = false;

		if ( get_current_blog_id() !== $site_id ) {
			switch_to_blog( $site_id );
			$switched = true;
		}

		$post = get_post( $page_id );
		$url  = ( $post && 'publish' === $post->post_status ) ? get_permalink( $post ) : '';

		if ( $switched ) {
			restore_current_blog();
		}

		return $url;
	}

	/**
	 * Name of the cookie the visitor's consent is stored in.
	 *
	 * Deliberately not per-site: sites sharing a domain, as a subdirectory
	 * network does, are meant to share one consent record rather than prompt
	 * again on each. Filter it only before a site goes live — changing it later
	 * orphans every stored consent, and everyone is prompted again.
	 *
	 * @return string
	 */
	public function consent_cookie_name() {
		/**
		 * Filters the consent cookie name.
		 *
		 * @param string $name Cookie name.
		 */
		$name = (string) apply_filters( 'just_cookies_cookie_name', 'just_cookies_consent' );

		// Cookie names cannot contain separators or whitespace.
		$name = preg_replace( '/[^A-Za-z0-9_\-]/', '', $name );

		return '' !== $name ? $name : 'just_cookies_consent';
	}

	/**
	 * Slugs checked when auto-detecting a policy page, in order.
	 *
	 * The first is the explicit name a generated page is given; the rest are
	 * common alternatives so a site that already has one is still detected.
	 *
	 * @param string $type cookie, privacy or terms.
	 * @return string[]
	 */
	public function policy_slugs( $type ) {
		$slugs = array(
			'cookie'  => array( 'cookie-policy', 'cookies', 'cookie-notice' ),
			'privacy' => array( 'privacy-policy', 'privacy' ),
			'terms'   => array( 'terms-of-service', 'terms', 'terms-and-conditions', 'terms-of-use' ),
		);

		/**
		 * Filters the slugs checked when auto-detecting a policy page.
		 *
		 * @param string[] $slugs Slugs, most explicit first.
		 * @param string   $type  cookie, privacy or terms.
		 */
		return (array) apply_filters(
			'just_cookies_policy_slugs',
			isset( $slugs[ $type ] ) ? $slugs[ $type ] : array(),
			$type
		);
	}

	/**
	 * Resolves a default page URL, but only if the page actually exists and is
	 * published — a policy notice must never link to a 404. Returns an empty
	 * string otherwise, and callers omit the link.
	 *
	 * Looks on the main site when policy pages are shared network-wide.
	 *
	 * @param string|string[] $slug Page slug, or slugs tried in order.
	 * @return string
	 */
	private function default_url( $slug ) {
		$site_id  = $this->network_owns_policy_pages() ? get_main_site_id() : get_current_blog_id();
		$switched = false;

		if ( get_current_blog_id() !== $site_id ) {
			switch_to_blog( $site_id );
			$switched = true;
		}

		$url = '';
		foreach ( (array) $slug as $candidate ) {
			$page = get_page_by_path( $candidate );
			if ( $page && 'publish' === $page->post_status ) {
				$url = get_permalink( $page );
				break;
			}
		}

		if ( $switched ) {
			restore_current_blog();
		}

		/**
		 * Filters a default policy/terms URL.
		 *
		 * @param string $url  Default URL.
		 * @param string $slug Page slug.
		 */
		return apply_filters( 'just_cookies_default_url', $url, $slug );
	}

	/**
	 * Integration keys whose cookies should be disclosed: detected when auto is
	 * on, otherwise whatever was chosen explicitly.
	 *
	 * @return string[]
	 */
	public function disclosed_integrations() {
		$integrations = Plugin::instance()->integrations;

		if ( ! $this->get( 'auto_detect_plugins' ) ) {
			return array_values(
				array_intersect(
					(array) $this->get( 'disclose_plugins' ),
					array_keys( $integrations->all() )
				)
			);
		}

		$keys = array();
		foreach ( array_keys( $integrations->all() ) as $key ) {
			if ( $integrations->is_active( $key ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Embed providers the plugin knows how to block, whether or not the site
	 * has turned them on.
	 *
	 * @return string[]
	 */
	public function embed_provider_catalog() {
		/**
		 * Filters the embed providers offered on the settings screen.
		 *
		 * @param string[] $providers Provider keys.
		 */
		$providers = apply_filters(
			'just_cookies_embed_providers',
			array(
				'youtube',
				'vimeo',
				'soundcloud',
				'googlemaps',
				'googlecalendar',
				'googledocs',
				'spotify',
				'dailymotion',
				'twitch',
				'wistia',
				'mixcloud',
				'bandcamp',
				'calendly',
				'typeform',
				'loom',
			)
		);

		return array_values( array_filter( array_map( 'strval', (array) $providers ) ) );
	}

	/**
	 * Embed providers this site blocks: the ones selected in the settings,
	 * limited to those in the catalog.
	 *
	 * @return string[]
	 */
	public function enabled_providers() {
		if ( ! $this->get( 'block_embeds' ) ) {
			return array();
		}

		$chosen = array_map( 'strval', (array) $this->get( 'embed_providers' ) );

		return array_values( array_intersect( $this->embed_provider_catalog(), $chosen ) );
	}
}
