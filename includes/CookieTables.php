<?php
/**
 * Builds cookie disclosure tables from settings and site context.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the per-category cookie rows used in the preferences modal and the
 * [just_cookies_table] shortcode.
 */
class CookieTables {

	/**
	 * Settings instance.
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
	}

	/**
	 * Column labels for cookie tables.
	 *
	 * @return array
	 */
	public function headers() {
		return array(
			'name' => __( 'Name', 'just-cookies' ),
			'src'  => __( 'Source', 'just-cookies' ),
			'desc' => __( 'Purpose', 'just-cookies' ),
			'exp'  => __( 'Expiry', 'just-cookies' ),
		);
	}

	/**
	 * Rows for the "necessary" category.
	 *
	 * @return array[]
	 */
	public function necessary_rows() {
		$rows = array();

		$rows[] = array(
			'name' => $this->settings->consent_cookie_name(),
			'src'  => $this->site_host(),
			'desc' => __( 'Stores your cookie consent preferences.', 'just-cookies' ),
			'exp'  => $this->human_expiry( (int) $this->settings->get( 'expires_days' ) ),
		);

		if ( $this->settings->get( 'disclose_cloudflare' ) ) {
			$rows[] = array(
				'name' => '__cf_bm',
				'src'  => 'Cloudflare',
				'desc' => __( 'Bot management; distinguishes humans from automated traffic.', 'just-cookies' ),
				'exp'  => __( '30 minutes', 'just-cookies' ),
			);
			$rows[] = array(
				'name' => 'cf_clearance',
				'src'  => 'Cloudflare',
				'desc' => __( 'Stores the result of a security/challenge check to allow access.', 'just-cookies' ),
				'exp'  => __( 'Up to 1 year', 'just-cookies' ),
			);
		}

		// Turnstile sets a cookie only when Pre-Clearance is enabled.
		if ( $this->settings->get( 'disclose_turnstile' ) ) {
			$rows[] = array(
				'name' => 'cf_clearance',
				'src'  => __( 'Cloudflare Turnstile', 'just-cookies' ),
				'desc' => __( 'Records that you passed the anti-bot check so you are not challenged again.', 'just-cookies' ),
				'exp'  => __( 'Up to 1 year', 'just-cookies' ),
			);
		}

		if ( $this->settings->get( 'disclose_login' ) ) {
			$rows[] = array(
				'name' => 'wordpress_[hash]',
				'src'  => $this->site_host(),
				'desc' => __( 'Authentication token set when you sign in.', 'just-cookies' ),
				'exp'  => __( 'Session / 14 days', 'just-cookies' ),
			);
			$rows[] = array(
				'name' => 'wordpress_sec_[hash]',
				'src'  => $this->site_host(),
				'desc' => __( 'Secure (HTTPS) authentication token set when you sign in.', 'just-cookies' ),
				'exp'  => __( 'Session / 14 days', 'just-cookies' ),
			);
			$rows[] = array(
				'name' => 'wordpress_logged_in_[hash]',
				'src'  => $this->site_host(),
				'desc' => __( 'Keeps you signed in and identifies you across the site after login.', 'just-cookies' ),
				'exp'  => __( 'Session / 14 days', 'just-cookies' ),
			);
			$rows[] = array(
				'name' => 'wordpress_test_cookie',
				'src'  => $this->site_host(),
				'desc' => __( 'Checks that the browser accepts cookies when signing in.', 'just-cookies' ),
				'exp'  => __( 'Session', 'just-cookies' ),
			);

			// Only set once someone actually loads a wp-admin screen.
			if ( $this->settings->get( 'disclose_admin' ) ) {
				$rows[] = array(
					'name' => 'wp-settings-[id]',
					'src'  => $this->site_host(),
					'desc' => __( 'Remembers your admin screen preferences.', 'just-cookies' ),
					'exp'  => __( '1 year', 'just-cookies' ),
				);
				$rows[] = array(
					'name' => 'wp-settings-time-[id]',
					'src'  => $this->site_host(),
					'desc' => __( 'Stores when your admin screen preferences were last set.', 'just-cookies' ),
					'exp'  => __( '1 year', 'just-cookies' ),
				);
			}
		}

		if ( $this->settings->get( 'disclose_recaptcha' ) ) {
			$rows[] = array(
				'name' => '_GRECAPTCHA',
				'src'  => __( 'Google reCAPTCHA', 'just-cookies' ),
				'desc' => __( 'Set when a protected form loads; tells humans from bots to prevent spam and abuse.', 'just-cookies' ),
				'exp'  => __( '6 months', 'just-cookies' ),
			);
		}

		if ( $this->settings->get( 'disclose_stripe' ) ) {
			$rows[] = array(
				'name' => '__stripe_mid',
				'src'  => 'Stripe',
				'desc' => __( 'Fraud prevention during payment; identifies the browser across sessions.', 'just-cookies' ),
				'exp'  => __( '1 year', 'just-cookies' ),
			);
			$rows[] = array(
				'name' => '__stripe_sid',
				'src'  => 'Stripe',
				'desc' => __( 'Fraud prevention during payment; identifies the current checkout session.', 'just-cookies' ),
				'exp'  => __( '30 minutes', 'just-cookies' ),
			);
		}

		// Cookies from supported plugins, per the integration registry.
		$rows = array_merge(
			$rows,
			Plugin::instance()->integrations->rows( $this->settings->disclosed_integrations() )
		);

		// Sources overlap, so each cookie name is listed once.
		$seen = array();
		foreach ( $rows as $index => $row ) {
			if ( isset( $seen[ $row['name'] ] ) ) {
				unset( $rows[ $index ] );
				continue;
			}
			$seen[ $row['name'] ] = true;
		}
		$rows = array_values( $rows );

		/**
		 * Filters the necessary cookie rows.
		 *
		 * @param array[] $rows Cookie rows.
		 */
		return apply_filters( 'just_cookies_necessary_rows', $rows );
	}

	/**
	 * Rows for the "analytics" category.
	 *
	 * @return array[]
	 */
	public function analytics_rows() {
		$rows = array(
			array(
				'name' => '_ga, _ga_[id]',
				'src'  => __( 'Google Analytics', 'just-cookies' ),
				'desc' => __( 'Distinguishes visitors and keeps session state so usage can be measured.', 'just-cookies' ),
				'exp'  => __( '2 years', 'just-cookies' ),
			),
		);

		/**
		 * Filters the analytics cookie rows.
		 *
		 * @param array[] $rows Cookie rows.
		 */
		return apply_filters( 'just_cookies_analytics_rows', $rows );
	}

	/**
	 * Display names for the embed providers, keyed by provider.
	 *
	 * Shared by the preferences modal's per-provider toggles and the blocked
	 * embed placeholder, so both name a service the same way.
	 *
	 * @return string[]
	 */
	public function provider_labels() {
		/**
		 * Filters the embed provider display names.
		 *
		 * @param string[] $labels Labels keyed by provider.
		 */
		return apply_filters(
			'just_cookies_provider_labels',
			array(
				'youtube'        => 'YouTube',
				'vimeo'          => 'Vimeo',
				'soundcloud'     => 'SoundCloud',
				'googlemaps'     => __( 'Google Maps', 'just-cookies' ),
				'googlecalendar' => __( 'Google Calendar', 'just-cookies' ),
				'googledocs'     => __( 'Google Docs, Sheets and Forms', 'just-cookies' ),
				'spotify'        => 'Spotify',
				'dailymotion'    => 'Dailymotion',
				'twitch'         => 'Twitch',
				'wistia'         => 'Wistia',
				'mixcloud'       => 'Mixcloud',
				'bandcamp'       => 'Bandcamp',
				'calendly'       => 'Calendly',
				'typeform'       => 'Typeform',
				'loom'           => 'Loom',
			)
		);
	}

	/**
	 * Rows for the "embeds" category, one per provider.
	 *
	 * @param string[]|null $providers Providers to include; null = all enabled.
	 * @return array[]
	 */
	public function embed_rows( $providers = null ) {
		$catalog = array(
			'youtube'    => array(
				'name' => 'YSC, VISITOR_INFO1_LIVE, PREF',
				'src'  => __( 'YouTube (Google)', 'just-cookies' ),
				'desc' => __( 'Set when a YouTube video is played; used to remember preferences and measure playback.', 'just-cookies' ),
				'exp'  => __( 'Session / up to 2 years', 'just-cookies' ),
			),
			'vimeo'      => array(
				'name' => 'vuid, player',
				'src'  => 'Vimeo',
				'desc' => __( 'Set when a Vimeo video loads; stores playback preferences and analytics.', 'just-cookies' ),
				'exp'  => __( 'Up to 2 years', 'just-cookies' ),
			),
			'soundcloud' => array(
				'name' => 'sc_anonymous_id',
				'src'  => 'SoundCloud',
				'desc' => __( 'Set when a SoundCloud player loads; identifies the player session.', 'just-cookies' ),
				'exp'  => __( '10 years', 'just-cookies' ),
			),
			'googlemaps' => array(
				'name' => 'NID, CONSENT',
				'src'  => __( 'Google Maps', 'just-cookies' ),
				'desc' => __( 'Set when a Google Map loads; used by Google for preferences and security.', 'just-cookies' ),
				'exp'  => __( 'Up to 2 years', 'just-cookies' ),
			),
			'googlecalendar' => array(
				'name' => 'NID, CONSENT',
				'src'  => __( 'Google Calendar', 'just-cookies' ),
				'desc' => __( 'Set when an embedded calendar loads; used by Google for preferences and security. Signed-in visitors also receive their Google account cookies.', 'just-cookies' ),
				'exp'  => __( 'Up to 2 years', 'just-cookies' ),
			),
			'googledocs' => array(
				'name' => 'NID, CONSENT',
				'src'  => __( 'Google Docs, Sheets and Forms', 'just-cookies' ),
				'desc' => __( 'Set when an embedded document or form loads; used by Google for preferences and security. Signed-in visitors also receive their Google account cookies.', 'just-cookies' ),
				'exp'  => __( 'Up to 2 years', 'just-cookies' ),
			),
			'spotify'    => array(
				'name' => 'sp_t, sp_landing',
				'src'  => 'Spotify',
				'desc' => __( 'Set when a Spotify player loads; identifies the player session and remembers playback preferences.', 'just-cookies' ),
				'exp'  => __( 'Up to 1 year', 'just-cookies' ),
			),
			'dailymotion' => array(
				'name' => 'dmvk, ts, v1st',
				'src'  => 'Dailymotion',
				'desc' => __( 'Set when a Dailymotion video loads; identifies the visitor and measures playback.', 'just-cookies' ),
				'exp'  => __( 'Session / up to 1 year', 'just-cookies' ),
			),
			'twitch'     => array(
				'name' => 'unique_id, twitch.lohp.countryCode',
				'src'  => 'Twitch',
				'desc' => __( 'Set when a Twitch player loads; identifies the visitor and stores regional preferences.', 'just-cookies' ),
				'exp'  => __( 'Up to 1 year', 'just-cookies' ),
			),
			'wistia'     => array(
				'name' => 'wistia, __distillery',
				'src'  => 'Wistia',
				'desc' => __( 'Set when a Wistia video loads; identifies the viewer so playback can be measured.', 'just-cookies' ),
				'exp'  => __( 'Session / up to 2 years', 'just-cookies' ),
			),
			'mixcloud'   => array(
				'name' => 'c, verified',
				'src'  => 'Mixcloud',
				'desc' => __( 'Set when a Mixcloud player loads; identifies the player session.', 'just-cookies' ),
				'exp'  => __( 'Up to 1 year', 'just-cookies' ),
			),
			'bandcamp'   => array(
				'name' => 'session, identity',
				'src'  => 'Bandcamp',
				'desc' => __( 'Set when a Bandcamp player loads; identifies the player session.', 'just-cookies' ),
				'exp'  => __( 'Session / up to 1 year', 'just-cookies' ),
			),
			'calendly'   => array(
				'name' => '__cf_bm, _calendly_session',
				'src'  => 'Calendly',
				'desc' => __( 'Set when a Calendly booking widget loads; keeps the booking session and filters automated traffic.', 'just-cookies' ),
				'exp'  => __( '30 minutes / session', 'just-cookies' ),
			),
			'typeform'   => array(
				'name' => 'tf_respondent_*',
				'src'  => 'Typeform',
				'desc' => __( 'Set when a Typeform form loads; remembers partial answers so a form can be resumed.', 'just-cookies' ),
				'exp'  => __( 'Session / up to 1 year', 'just-cookies' ),
			),
			'loom'       => array(
				'name' => 'connect.sid, loom_referral_source',
				'src'  => 'Loom',
				'desc' => __( 'Set when a Loom recording loads; identifies the viewer session.', 'just-cookies' ),
				'exp'  => __( 'Session / up to 1 year', 'just-cookies' ),
			),
		);

		if ( null === $providers ) {
			$providers = $this->settings->enabled_providers();
		}

		$rows = array();
		foreach ( (array) $providers as $provider ) {
			if ( isset( $catalog[ $provider ] ) ) {
				$rows[] = $catalog[ $provider ];
			}
		}

		/**
		 * Filters the embed cookie rows.
		 *
		 * @param array[] $rows Cookie rows.
		 */
		return apply_filters( 'just_cookies_embed_rows', $rows );
	}

	/**
	 * Formats a number of days as a human-readable expiry.
	 *
	 * @param int $days Number of days.
	 * @return string
	 */
	private function human_expiry( $days ) {
		$days = max( 1, (int) $days );

		if ( 0 === $days % 365 ) {
			$years = $days / 365;
			/* translators: %d: number of years. */
			return sprintf( _n( '%d year', '%d years', $years, 'just-cookies' ), $years );
		}

		if ( $days >= 28 ) {
			$months = (int) round( $days / 30 );
			/* translators: %d: number of months. */
			return sprintf( _n( '%d month', '%d months', $months, 'just-cookies' ), $months );
		}

		/* translators: %d: number of days. */
		return sprintf( _n( '%d day', '%d days', $days, 'just-cookies' ), $days );
	}

	/**
	 * Current site host, used as the first-party source label.
	 *
	 * @return string
	 */
	private function site_host() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host ? $host : __( 'This site', 'just-cookies' );
	}
}
