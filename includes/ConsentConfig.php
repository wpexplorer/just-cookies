<?php
/**
 * Builds the CookieConsent v3 runtime configuration.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Assembles the configuration object passed to CookieConsent.run().
 */
class ConsentConfig {

	const EMBED_CATEGORY = 'embeds';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Cookie tables.
	 *
	 * @var CookieTables
	 */
	private $tables;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Settings.
	 * @param CookieTables $tables   Cookie tables.
	 */
	public function __construct( Settings $settings, CookieTables $tables ) {
		$this->settings = $settings;
		$this->tables   = $tables;
	}

	/**
	 * Builds the data object exposed to the frontend script.
	 *
	 * @return array
	 */
	public function build() {
		$s = $this->settings;

		$config = array(
			'revision' => (int) $s->get( 'revision' ),
			'disablePageInteraction' => (bool) $s->get( 'lock_overlay' ),
			'cookie'   => array(
				'name'             => $s->consent_cookie_name(),
				'expiresAfterDays' => (int) $s->get( 'expires_days' ),
			),
			'guiOptions' => array(
				'consentModal'     => array(
					'layout'             => $s->get( 'layout' ),
					'position'           => $s->get( 'position' ),
					'equalWeightButtons' => true,
					'flipButtons'        => false,
				),
				'preferencesModal' => array(
					'layout'             => 'box',
					'equalWeightButtons' => true,
					'flipButtons'        => false,
				),
			),
			'categories' => array(
				'necessary' => array(
					'enabled'  => true,
					'readOnly' => true,
				),
			),
			'language'   => array(
				'default'      => 'en',
				'translations' => array(
					'en' => $this->translation(),
				),
			),
		);

		if ( $s->get( 'block_analytics' ) ) {
			$config['categories'][ Analytics::CATEGORY ] = array(
				'enabled'   => false,
				'autoClear' => array(
					'cookies' => $this->analytics_cookies_to_clear(),
					// The tracker stays loaded after consent is withdrawn and
					// would write its cookies straight back, so the page is
					// reloaded to unload it.
					'reloadPage' => true,
				),
			);
		}

		// The category is present on every page, not only pages with embeds.
		$providers = $s->enabled_providers();
		if ( $providers ) {
			// One service per provider, so accepting a video does not also
			// accept maps. Keys match data-just-cookies-provider in the markup.
			$labels   = $this->tables->provider_labels();
			$services = array();
			foreach ( $providers as $provider ) {
				$services[ $provider ] = array(
					'label' => isset( $labels[ $provider ] ) ? $labels[ $provider ] : $provider,
				);
			}

			$config['categories'][ self::EMBED_CATEGORY ] = array(
				'enabled'  => false,
				'services' => $services,
			);
		}

		/**
		 * Filters the full CookieConsent config array.
		 *
		 * @param array $config Config.
		 */
		$config = apply_filters( 'just_cookies_consent_config', $config );

		// Hosts for the enabled providers only, so a click is never held for a
		// service the site does not block.
		$link_hosts = array_filter(
			Embeds::link_hosts(),
			static fn( $provider ) => in_array( $provider, $providers, true )
		);

		$labels = $this->tables->provider_labels();

		return array(
			'config'            => $config,
			'darkMode'          => (bool) $s->get( 'dark_mode' ),
			'embedCategory'     => self::EMBED_CATEGORY,
			'linkHosts'         => (object) $link_hosts,
			'lightboxSelectors' => $link_hosts ? Embeds::lightbox_selectors() : array(),
			// Same wording the inline placeholder uses, so a held lightbox link
			// explains itself the same way.
			'embedNotice'       => wp_kses_post( $s->text( 'embed_notice' ) ),
			'providerLabels'    => (object) array_intersect_key( $labels, array_flip( $providers ) ),
			'i18n'              => array(
				'load'   => __( 'Load content', 'just-cookies' ),
				'prefs'  => __( 'Cookie settings', 'just-cookies' ),
				'cancel' => __( 'Cancel', 'just-cookies' ),
			),
		);
	}

	/**
	 * Cookies removed when analytics consent is withdrawn.
	 *
	 * First-party only: these are written on this site's own domain by the
	 * trackers the plugin gates. Cookies belonging to a third-party domain, as
	 * embed providers set, cannot be removed from here at all.
	 *
	 * A 'regex' entry is compiled to a RegExp on the client; 'name' matches
	 * exactly.
	 *
	 * @return array[]
	 */
	private function analytics_cookies_to_clear() {
		/**
		 * Filters the first-party cookies cleared when analytics is refused.
		 *
		 * @param array[] $cookies Entries of array( 'name' => … ) or array( 'regex' => … ).
		 */
		return (array) apply_filters(
			'just_cookies_analytics_clear_cookies',
			array(
				// Google Analytics / gtag.
				array( 'regex' => '^_ga' ),
				array( 'name' => '_gid' ),
				array( 'regex' => '^_gat' ),
				array( 'regex' => '^_gac_' ),
				// Meta Pixel.
				array( 'name' => '_fbp' ),
				array( 'name' => '_fbc' ),
				// Hotjar.
				array( 'regex' => '^_hj' ),
				// Microsoft Clarity.
				array( 'name' => '_clck' ),
				array( 'name' => '_clsk' ),
				// Bing UET.
				array( 'name' => '_uetsid' ),
				array( 'name' => '_uetvid' ),
				// Matomo / Piwik.
				array( 'regex' => '^_pk_' ),
				// TikTok.
				array( 'name' => '_ttp' ),
				// Mixpanel.
				array( 'regex' => '^mp_' ),
			)
		);
	}

	/**
	 * Builds the English translation block, including cookie tables.
	 *
	 * @return array
	 */
	private function translation() {
		$targets = array(
			'cookie'  => array( $this->settings->policy_url(), __( 'Cookie Policy', 'just-cookies' ) ),
			'privacy' => array( $this->settings->privacy_url(), __( 'Privacy Policy', 'just-cookies' ) ),
			'terms'   => array( $this->settings->terms_url(), __( 'Terms of Service', 'just-cookies' ) ),
		);

		// A site already linking these in its footer can leave them out here.
		$wanted  = (array) $this->settings->get( 'banner_links' );
		$new_tab = (bool) $this->settings->get( 'banner_links_new_tab' );

		// The overlay stops the page scrolling while the banner is up, so a
		// link out of it leads somewhere unreadable. They move to the
		// preferences popup, which is reached after the banner is answered and
		// carries no lock of its own.
		$links_in_prefs = (bool) $this->settings->get( 'lock_overlay' );

		$links = array();
		foreach ( $targets as $key => $target ) {
			list( $url, $label ) = $target;
			if ( ! $url || ! in_array( $key, $wanted, true ) ) {
				continue;
			}
			$links[] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( $url ),
				$new_tab ? ' target="_blank" rel="noopener"' : '',
				esc_html( $label )
			);
		}

		$footer = $links_in_prefs ? '' : implode( ' ', $links );

		$has_embeds = (bool) $this->settings->enabled_providers();

		$has_optional = $this->settings->has_optional_categories();

		// The embeds note is appended only while blocking is on.
		$description = wp_kses_post( $this->settings->text( 'banner_description' ) );
		$note        = wp_kses_post( $this->settings->text( 'banner_embeds_note' ) );
		if ( $has_embeds && $note ) {
			$description = trim( $description . ' ' . $note );
		}

		$banner_title = wp_kses_post( $this->settings->text( 'banner_title' ) );
		$ack_label    = wp_kses_post( $this->settings->text( 'ack_button_label' ) );

		// Covers analytics as well as embeds, so gating analytics alone does not
		// leave the popup describing a site with nothing optional on it.
		$intro = $has_optional
			? __( 'Necessary cookies keep the site working and are always on. Anything optional is only used after you accept it.', 'just-cookies' )
			: __( 'Necessary cookies keep the site working and are always on.', 'just-cookies' );

		$titles = $this->tables->category_titles();

		$sections = array(
			array(
				'title'       => __( 'Your Privacy', 'just-cookies' ),
				'description' => $intro,
			),
			array(
				'title'         => $titles['necessary'],
				'description'   => __( 'Required for the site to function. These cannot be switched off.', 'just-cookies' ),
				'linkedCategory' => 'necessary',
				'cookieTable'   => array(
					'headers' => $this->tables->headers(),
					'body'    => $this->tables->necessary_rows(),
				),
			),
		);

		if ( $this->settings->get( 'block_analytics' ) ) {
			$sections[] = array(
				'title'          => $titles[ Analytics::CATEGORY ],
				'description'    => __( 'Help us understand how visitors use the site. These are only set if you accept them.', 'just-cookies' ),
				'linkedCategory' => Analytics::CATEGORY,
				'cookieTable'    => array(
					'headers' => $this->tables->headers(),
					'body'    => $this->tables->analytics_rows(),
				),
			);
		}

		if ( $has_embeds ) {
			$sections[] = array(
				'title'         => $titles[ self::EMBED_CATEGORY ],
				'description'   => __( 'Videos, audio players and maps embedded from third-party services. These are blocked until you accept them.', 'just-cookies' ),
				'linkedCategory' => self::EMBED_CATEGORY,
				'cookieTable'   => array(
					'headers' => $this->tables->headers(),
					'body'    => $this->tables->embed_rows(),
				),
			);
		}

		// Where the policy links live when the overlay keeps them out of the
		// banner. Nothing is lost — they are one click further on.
		if ( $links_in_prefs && $links ) {
			$sections[] = array(
				'title'       => __( 'Our policies', 'just-cookies' ),
				'description' => implode( ' ', $links ),
			);
		}

		// Closing section with no category of its own — a contact address, a
		// link to a fuller policy. Per site, so each can name its own contact.
		$extra_text = $this->settings->get( 'prefs_extra' )
			? wp_kses_post( (string) $this->settings->get( 'prefs_extra_text' ) )
			: '';

		if ( $extra_text ) {
			$sections[] = array(
				'title'       => sanitize_text_field( (string) $this->settings->get( 'prefs_extra_title' ) ),
				'description' => $extra_text,
			);
		}

		if ( $has_optional ) {
			// Optional categories exist: full opt-in choice.
			$consent_modal = array(
				'title'              => $banner_title,
				'description'        => $description,
				'acceptAllBtn'       => __( 'Accept all', 'just-cookies' ),
				'acceptNecessaryBtn' => __( 'Reject all', 'just-cookies' ),
				'showPreferencesBtn' => __( 'Manage preferences', 'just-cookies' ),
				'footer'             => $footer,
			);
		} else {
			// Necessary cookies only: acknowledgement plus a disclosure link.
			$consent_modal = array(
				'title'              => $banner_title,
				'description'        => $description,
				'acceptNecessaryBtn' => $ack_label,
				'showPreferencesBtn' => __( 'Cookie details', 'just-cookies' ),
				'footer'             => $footer,
			);
		}

		$preferences_modal = array(
			// Nothing to set means nothing to prefer, and the banner button that
			// opens this already says "Cookie details".
			'title'          => $has_optional
				? __( 'Cookie preferences', 'just-cookies' )
				: __( 'Cookie details', 'just-cookies' ),
			'closeIconLabel' => __( 'Close', 'just-cookies' ),
			'sections'       => $sections,
		);

		if ( $has_optional ) {
			$preferences_modal['acceptAllBtn']       = __( 'Accept all', 'just-cookies' );
			$preferences_modal['acceptNecessaryBtn'] = __( 'Reject all', 'just-cookies' );
			$preferences_modal['savePreferencesBtn'] = __( 'Save preferences', 'just-cookies' );
		} else {
			// Nothing optional to toggle — just a dismiss.
			$preferences_modal['acceptNecessaryBtn'] = $ack_label;
		}

		return array(
			'consentModal'     => $consent_modal,
			'preferencesModal' => $preferences_modal,
		);
	}
}
