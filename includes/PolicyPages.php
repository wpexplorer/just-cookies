<?php
/**
 * Policy page detection and creation.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Reports which policy pages are missing and creates the ones it safely can.
 *
 * The cookie policy is generated because its content is factual and derived
 * from the plugin's own settings. Terms are only stubbed as a draft — the
 * wording is a legal decision, not something to auto-publish.
 */
class PolicyPages {

	// Slug given to a generated page. Detection also accepts the common
	// alternatives listed in Settings::policy_slugs().
	const COOKIE_SLUG = 'cookie-policy';
	const TERMS_SLUG  = 'terms-of-service';

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

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the status/create routes.
	 */
	public function register_routes() {
		register_rest_route(
			'just-cookies/v1',
			'/policy-pages',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_pages' ),
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
		return $this->settings->network_owns_policy_pages()
			? current_user_can( 'manage_network_options' )
			: current_user_can( 'manage_options' );
	}

	/**
	 * Site that owns the policy pages: the main site when shared network-wide,
	 * otherwise the current site.
	 *
	 * @return int
	 */
	private function target_site_id() {
		return $this->settings->network_owns_policy_pages()
			? get_main_site_id()
			: get_current_blog_id();
	}

	/**
	 * Finds a published-or-draft page by explicit URL, then by slug.
	 *
	 * @param string          $url   Configured URL, if any.
	 * @param string|string[] $slug  Fallback slug, or slugs tried in order.
	 * @return \WP_Post|null
	 */
	private function locate_page( $url, $slug ) {
		if ( $url ) {
			$id = url_to_postid( $url );
			if ( $id ) {
				return get_post( $id );
			}
		}

		foreach ( (array) $slug as $candidate ) {
			$page = get_page_by_path( $candidate );
			if ( $page ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * Builds the status payload.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status() {
		$switched = false;
		$site_id  = $this->target_site_id();

		if ( get_current_blog_id() !== $site_id ) {
			switch_to_blog( $site_id );
			$switched = true;
		}

		// Resolved the same way the banner resolves its links, so the two can
		// never disagree about whether a page exists.
		$cookie = $this->locate_page( $this->settings->policy_url(), $this->settings->policy_slugs( 'cookie' ) );
		$terms  = $this->locate_page( $this->settings->terms_url(), $this->settings->policy_slugs( 'terms' ) );

		// Settings → Privacy first: it is authoritative and, unlike the banner
		// link, finds a page that is still a draft.
		$privacy_id   = (int) get_option( 'wp_privacy_policy_page' );
		$privacy_post = $privacy_id ? get_post( $privacy_id ) : null;

		if ( ! $privacy_post ) {
			$privacy_post = $this->locate_page( $this->settings->privacy_url(), $this->settings->policy_slugs( 'privacy' ) );
		}

		$data = array(
			'cookie'  => $this->describe_page( $cookie ),
			'terms'   => $this->describe_page( $terms ),
			'privacy' => $this->describe_page( $privacy_post ),
			'privacyAdminUrl' => admin_url( 'options-privacy.php' ),
		);

		if ( $switched ) {
			restore_current_blog();
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Describes a page for the admin UI.
	 *
	 * @param \WP_Post|null $post Page.
	 * @return array
	 */
	private function describe_page( $post ) {
		if ( ! $post ) {
			return array( 'exists' => false );
		}

		return array(
			'exists'  => true,
			'status'  => $post->post_status,
			'title'   => $post->post_title,
			'url'     => get_permalink( $post ),
			'editUrl' => get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	/**
	 * Creates whichever pages are missing.
	 *
	 * @return \WP_REST_Response
	 */
	public function create_pages() {
		$switched = false;
		$site_id  = $this->target_site_id();

		if ( get_current_blog_id() !== $site_id ) {
			switch_to_blog( $site_id );
			$switched = true;
		}

		$created = array();

		// Both are drafts. These are legal pages on someone else's site, and
		// publishing one the owner has not read would put wording in front of
		// visitors that nobody approved — the same reason core leaves its own
		// generated privacy policy unpublished. The banner links to a page only
		// once it is published, so nothing is advertised before it is ready.
		if ( ! $this->locate_page( $this->settings->policy_url(), $this->settings->policy_slugs( 'cookie' ) ) ) {
			$id = wp_insert_post(
				array(
					'post_title'   => __( 'Cookie Policy', 'just-cookies' ),
					'post_name'    => self::COOKIE_SLUG,
					'post_content' => $this->cookie_policy_content(),
					'post_status'  => 'draft',
					'post_type'    => 'page',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$created[] = 'cookie';
			}
		}

		if ( ! $this->locate_page( $this->settings->terms_url(), $this->settings->policy_slugs( 'terms' ) ) ) {
			$id = wp_insert_post(
				array(
					'post_title'   => __( 'Terms of Service', 'just-cookies' ),
					'post_name'    => self::TERMS_SLUG,
					'post_content' => $this->terms_stub_content(),
					'post_status'  => 'draft',
					'post_type'    => 'page',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$created[] = 'terms';
			}
		}

		if ( $switched ) {
			restore_current_blog();
		}

		$response          = $this->get_status();
		$data              = $response->get_data();
		$data['created']   = $created;

		return rest_ensure_response( $data );
	}

	/**
	 * Cookie policy body. Factual content driven by the plugin's own tables.
	 *
	 * @return string
	 */
	private function cookie_policy_content() {
		$intro = __( 'This page explains the cookies this website uses and why. You can change your choices at any time using the cookie settings link on the site.', 'just-cookies' );

		return $intro . "\n\n[just_cookies_table]";
	}

	/**
	 * Terms draft placeholder.
	 *
	 * @return string
	 */
	private function terms_stub_content() {
		return __( 'Draft placeholder — replace this with your own terms before publishing.', 'just-cookies' );
	}
}
