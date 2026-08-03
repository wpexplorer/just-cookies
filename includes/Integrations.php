<?php
/**
 * Registry of plugins whose cookies can be disclosed.
 *
 * Each entry declares how to detect the plugin, the plugin file used to test
 * whether it is installed at all, and the cookie rows to disclose. Cookie names
 * here were read from each plugin's own source or documentation when the entry
 * was added, and are a snapshot: a plugin can change what it sets at any time,
 * so entries need re-checking against the current version.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Supported plugin integrations.
 */
class Integrations {

	/**
	 * Cached installed plugin files.
	 *
	 * @var array|null
	 */
	private $installed = null;

	/**
	 * Cached integration registry.
	 *
	 * @var array|null
	 */
	private $items = null;

	/**
	 * All supported integrations, keyed by slug.
	 *
	 * @return array
	 */
	public function all() {
		if ( null !== $this->items ) {
			return $this->items;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = $host ? $host : __( 'This site', 'just-cookies' );

		$woocommerce_rows = array(
			array(
				'name' => 'woocommerce_cart_hash',
				'src'  => 'WooCommerce',
				'desc' => __( 'Tracks changes to the cart contents.', 'just-cookies' ),
				'exp'  => __( 'Session', 'just-cookies' ),
			),
			array(
				'name' => 'woocommerce_items_in_cart',
				'src'  => 'WooCommerce',
				'desc' => __( 'Tracks the number of items in the cart.', 'just-cookies' ),
				'exp'  => __( 'Session', 'just-cookies' ),
			),
			array(
				'name' => 'wp_woocommerce_session_[hash]',
				'src'  => 'WooCommerce',
				'desc' => __( 'Holds a unique identifier for the cart/session data on the server.', 'just-cookies' ),
				'exp'  => __( '2 days', 'just-cookies' ),
			),
		);

		if ( $this->woocommerce_tracks_attribution() ) {
			$woocommerce_rows[] = array(
				'name' => 'sbjs_current, sbjs_current_add, sbjs_first, sbjs_first_add, sbjs_migrations, sbjs_session, sbjs_udata',
				'src'  => 'WooCommerce',
				'desc' => __( 'Records the referrer, campaign and visit that led to an order, so the sale can be credited to its source (Order Attribution).', 'just-cookies' ),
				'exp'  => __( 'Session', 'just-cookies' ),
			);
		}

		$items = array(
			'woocommerce'       => array(
				'label'  => 'WooCommerce',
				'file'   => 'woocommerce/woocommerce.php',
				'active' => static function () {
					return class_exists( 'WooCommerce' );
				},
				'rows'   => $woocommerce_rows,
			),
			'edd'               => array(
				'label'  => 'Easy Digital Downloads',
				'file'   => 'easy-digital-downloads/easy-digital-downloads.php',
				'active' => static function () {
					return class_exists( 'Easy_Digital_Downloads' );
				},
				'rows'   => array(
					array(
						'name' => 'edd_items_in_cart',
						'src'  => 'Easy Digital Downloads',
						'desc' => __( 'Tracks the contents of the cart.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
					array(
						'name' => 'edd_cart_token',
						'src'  => 'Easy Digital Downloads',
						'desc' => __( 'Verifies the cart belongs to you.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
					array(
						'name' => 'edd_session_[hash]',
						'src'  => 'Easy Digital Downloads',
						'desc' => __( 'Holds a unique identifier for your checkout session.', 'just-cookies' ),
						'exp'  => __( '1 day', 'just-cookies' ),
					),
				),
			),
			'simple_membership' => array(
				'label'  => 'Simple Membership',
				'file'   => 'simple-membership/simple-wp-membership.php',
				'active' => static function () {
					return class_exists( 'SimpleWpMembership' );
				},
				'rows'   => array(
					array(
						'name' => 'swpm_session, wp_swpm_in_use',
						'src'  => $host,
						'desc' => __( 'Keeps you signed in to your membership account.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
				),
			),
			'polylang'          => array(
				'label'  => 'Polylang',
				'file'   => 'polylang/polylang.php',
				'active' => static function () {
					return defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' );
				},
				'rows'   => array(
					array(
						'name' => 'pll_language',
						'src'  => $host,
						'desc' => __( 'Remembers the language you chose.', 'just-cookies' ),
						'exp'  => __( '1 year', 'just-cookies' ),
					),
				),
			),
			'wpml'              => array(
				'label'  => 'WPML',
				'file'   => 'sitepress-multilingual-cms/sitepress.php',
				'active' => static function () {
					return defined( 'ICL_SITEPRESS_VERSION' );
				},
				'rows'   => array(
					array(
						'name' => 'wp-wpml_current_language',
						'src'  => $host,
						'desc' => __( 'Remembers the language you chose.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
				),
			),
			'jetpack'           => array(
				'label'  => 'Jetpack',
				'file'   => 'jetpack/jetpack.php',
				'active' => static function () {
					return class_exists( 'Jetpack' );
				},
				'rows'   => array(
					array(
						'name' => 'jetpack_comments_subscribe_[hash], jetpack_blog_subscribe_[hash]',
						'src'  => 'Jetpack',
						'desc' => __( 'Remembers whether you subscribed to comments or new posts.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
					array(
						'name' => 'jpp_math_pass',
						'src'  => 'Jetpack',
						'desc' => __( 'Records that you passed a spam-protection check.', 'just-cookies' ),
						'exp'  => __( '1 day', 'just-cookies' ),
					),
				),
			),
			'affiliatewp'       => array(
				'label'  => 'AffiliateWP',
				'file'   => 'affiliate-wp/affiliate-wp.php',
				'active' => static function () {
					return class_exists( 'Affiliate_WP' );
				},
				'rows'   => array(
					array(
						'name' => 'affwp_ref, affwp_ref_visit_id, affwp_campaign',
						'src'  => $host,
						'desc' => __( 'Records which affiliate referred you so a commission can be credited.', 'just-cookies' ),
						'exp'  => __( 'Set by the site (commonly 30 days)', 'just-cookies' ),
					),
				),
			),
			'buddypress'        => array(
				'label'  => 'BuddyPress',
				'file'   => 'buddypress/bp-loader.php',
				'active' => static function () {
					return function_exists( 'buddypress' );
				},
				'rows'   => array(
					array(
						'name' => 'bp-message, bp-message-type',
						'src'  => $host,
						'desc' => __( 'Carries a status message across a page reload.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
					array(
						'name' => 'bp_messages_content, bp_completed_create_steps',
						'src'  => $host,
						'desc' => __( 'Preserves a message draft or group-creation progress.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
				),
			),
			'lifterlms'         => array(
				'label'  => 'LifterLMS',
				'file'   => 'lifterlms/lifterlms.php',
				'active' => static function () {
					return class_exists( 'LifterLMS' );
				},
				'rows'   => array(
					array(
						'name' => 'llms-tracking',
						'src'  => $host,
						'desc' => __( 'Records course activity for your learner progress.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
				),
			),
			'learndash'         => array(
				'label'  => 'LearnDash',
				'file'   => 'sfwd-lms/sfwd_lms.php',
				'active' => static function () {
					return class_exists( 'SFWD_LMS' );
				},
				'rows'   => array(
					array(
						'name' => 'learndash_timer_cookie_[id]',
						'src'  => $host,
						'desc' => __( 'Tracks time spent on a timed lesson.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
					array(
						'name' => 'wpProQuiz_lock, wpProQuiz_result',
						'src'  => $host,
						'desc' => __( 'Stores your quiz attempt and prevents retaking a locked quiz.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
				),
			),
			'events_manager'    => array(
				'label'  => 'Events Manager',
				'file'   => 'events-manager/events-manager.php',
				'active' => static function () {
					return defined( 'EM_VERSION' );
				},
				'rows'   => array(
					array(
						'name' => 'em_search_events, em_notices',
						'src'  => $host,
						'desc' => __( 'Remembers your event search and carries booking notices.', 'just-cookies' ),
						'exp'  => __( 'Session', 'just-cookies' ),
					),
				),
			),
			'post_views'        => array(
				'label'  => 'Post Views Counter',
				'file'   => 'post-views-counter/post-views-counter.php',
				'active' => static function () {
					return class_exists( 'Post_Views_Counter' );
				},
				'rows'   => array(
					array(
						'name' => 'pvc_visits[_id]',
						'src'  => $host,
						'desc' => __( 'Counts page views and prevents the same visit being counted twice.', 'just-cookies' ),
						'exp'  => __( 'Set by the site (commonly 24 hours)', 'just-cookies' ),
					),
				),
			),
		);

		/**
		 * Filters the supported plugin integrations.
		 *
		 * Each entry accepts: label, file, active (callable), rows.
		 *
		 * @param array $items Integrations keyed by slug.
		 */
		$this->items = apply_filters( 'just_cookies_integrations', $items );

		return $this->items;
	}

	/**
	 * Whether an integration's plugin is active on the current site.
	 *
	 * @param string $key Integration key.
	 * @return bool
	 */
	public function is_active( $key ) {
		$items = $this->all();

		if ( empty( $items[ $key ]['active'] ) || ! is_callable( $items[ $key ]['active'] ) ) {
			return false;
		}

		return (bool) call_user_func( $items[ $key ]['active'] );
	}

	/**
	 * Whether to disclose WooCommerce Order Attribution's sbjs_* cookies.
	 *
	 * Follows whichever mode the plugin list is in. Auto-detect reads
	 * WooCommerce's own feature switch, accepting the same limitation the rest
	 * of auto-detect has: it describes this site only. Choosing plugins by hand
	 * — what a network has to do, since the switch can differ per site — uses
	 * the setting beside the WooCommerce checkbox instead.
	 *
	 * @return bool
	 */
	private function woocommerce_tracks_attribution() {
		$settings = Plugin::instance()->settings;

		if ( ! $settings->get( 'auto_detect_plugins' ) ) {
			return (bool) $settings->get( 'disclose_order_attribution' );
		}

		// The two conditions WooCommerce itself checks before loading
		// Sourcebuster: the Advanced → Features switch, and the filter.
		if ( 'yes' !== get_option( 'woocommerce_feature_order_attribution_enabled', 'yes' ) ) {
			return false;
		}

		return (bool) apply_filters( 'wc_order_attribution_allow_tracking', true );
	}

	/**
	 * Integration keys whose plugin files exist on the server, whether active
	 * or not. On a network this includes plugins active only on other sites.
	 *
	 * Only call from admin screens: get_plugins() scans the plugins directory.
	 *
	 * @return string[]
	 */
	public function installed_keys() {
		if ( null === $this->installed ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$this->installed = array_keys( get_plugins() );
		}

		$keys = array();
		foreach ( $this->all() as $key => $item ) {
			if ( ! empty( $item['file'] ) && in_array( $item['file'], $this->installed, true ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Labels keyed by integration slug.
	 *
	 * @return array
	 */
	public function labels() {
		$labels = array();
		foreach ( $this->all() as $key => $item ) {
			$labels[ $key ] = isset( $item['label'] ) ? $item['label'] : $key;
		}
		return $labels;
	}

	/**
	 * Cookie rows for the given integration keys.
	 *
	 * @param string[] $keys Integration keys.
	 * @return array[]
	 */
	public function rows( $keys ) {
		$items = $this->all();
		$rows  = array();

		foreach ( (array) $keys as $key ) {
			if ( ! empty( $items[ $key ]['rows'] ) ) {
				$rows = array_merge( $rows, $items[ $key ]['rows'] );
			}
		}

		return $rows;
	}
}
