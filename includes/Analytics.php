<?php
/**
 * Holds analytics scripts until the visitor accepts the analytics category.
 *
 * Detection is self-contained: script tags are matched by source URL or inline
 * body, the same approach consent managers use, so no cooperation is needed
 * from the theme or from whatever added the tracker.
 *
 * Gating is client-side — the tags are marked type="text/plain" with a
 * data-category and CookieConsent activates them on consent. A server-side
 * check would be baked into page caches and is not usable here.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Marks analytics script tags as consent-gated.
 */
class Analytics {

	const CATEGORY = 'analytics';

	/**
	 * Nesting level of the buffer this class opened, 0 when not buffering.
	 *
	 * @var int
	 */
	private $buffer_level = 0;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		if ( Plugin::is_admin_request() || ! $settings->get( 'enabled' ) || ! $settings->get( 'block_analytics' ) ) {
			return;
		}

		// Properly enqueued trackers, wherever they print: no buffering needed.
		add_filter( 'script_loader_tag', array( $this, 'filter_enqueued_tag' ), 20, 3 );

		/**
		 * Hooks whose output is buffered to catch directly echoed trackers.
		 *
		 * Both ends of the page: head is where tracking snippets are meant to
		 * go, footer is where Tag Manager and script-injector plugins often
		 * put them. Enqueued scripts are handled separately.
		 *
		 * @param string[] $hooks Action names.
		 */
		$hooks = (array) apply_filters( 'just_cookies_analytics_buffer_hooks', array( 'wp_head', 'wp_footer' ) );

		foreach ( $hooks as $hook ) {
			add_action( $hook, array( $this, 'start_buffer' ), -PHP_INT_MAX );
			add_action( $hook, array( $this, 'end_buffer' ), PHP_INT_MAX );
		}
	}

	/**
	 * Source fragments identifying an analytics script.
	 *
	 * @return string[]
	 */
	private function src_patterns() {
		/**
		 * Filters the script sources treated as analytics.
		 *
		 * @param string[] $patterns Case-insensitive substrings.
		 */
		return (array) apply_filters(
			'just_cookies_analytics_script_patterns',
			array(
				// Google (gtag, Tag Manager, Universal Analytics).
				'googletagmanager.com',
				'google-analytics.com',
				'gtag/js',
				// Other mainstream trackers.
				'connect.facebook.net',
				'static.hotjar.com',
				'clarity.ms',
				'bat.bing.com',
				'snap.licdn.com',
				'analytics.tiktok.com',
				'cdn.segment.com',
				'cdn.mxpnl.com',
				'plausible.io',
				'matomo.js',
				'piwik.js',
				'stats.wp.com',
			)
		);
	}

	/**
	 * Body fragments identifying an inline analytics script.
	 *
	 * @return string[]
	 */
	private function inline_patterns() {
		/**
		 * Filters the inline script bodies treated as analytics.
		 *
		 * @param string[] $patterns Case-insensitive substrings.
		 */
		return (array) apply_filters(
			'just_cookies_analytics_inline_patterns',
			array(
				// Google. gtm.start matches the Tag Manager snippet.
				'gtag(',
				'gtm.start',
				'dataLayer.push',
				'GoogleAnalyticsObject',
				// Other mainstream trackers.
				'fbq(',
				'_paq.push',
				'hj(',
				'clarity(',
				'ttq.load',
				'lintrk(',
				'uetq',
				'mixpanel.init',
			)
		);
	}

	/**
	 * Whether a string contains any of the given fragments.
	 *
	 * @param string   $haystack Subject.
	 * @param string[] $needles  Fragments.
	 * @return bool
	 */
	private function contains_any( $haystack, $needles ) {
		$haystack = strtolower( $haystack );
		foreach ( $needles as $needle ) {
			if ( '' !== $needle && str_contains( $haystack, strtolower( $needle ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a tag is already gated by this plugin or another consent manager.
	 *
	 * @param string $tag Opening script tag.
	 * @return bool
	 */
	private function already_gated( $tag ) {
		return (bool) preg_match( '#type\s*=\s*(["\'])text/plain\1#i', $tag )
			|| false !== stripos( $tag, 'data-category' );
	}

	/**
	 * Adds the gating attributes to an opening script tag.
	 *
	 * @param string $tag Opening script tag.
	 * @return string
	 */
	private function gate_tag( $tag ) {
		// Replace an existing type, otherwise inject both attributes.
		if ( preg_match( '#\stype\s*=\s*(["\']).*?\1#i', $tag ) ) {
			$tag = preg_replace( '#\stype\s*=\s*(["\']).*?\1#i', ' type="text/plain"', $tag, 1 );
			return preg_replace( '#<script\b#i', '<script data-category="' . self::CATEGORY . '"', $tag, 1 );
		}

		return preg_replace(
			'#<script\b#i',
			'<script type="text/plain" data-category="' . self::CATEGORY . '"',
			$tag,
			1
		);
	}

	/**
	 * Gates enqueued analytics scripts.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source.
	 * @return string
	 */
	public function filter_enqueued_tag( $tag, $handle, $src ) {
		if ( Plugin::is_editing_context() || $this->already_gated( $tag ) ) {
			return $tag;
		}

		if ( ! $src || ! $this->contains_any( $src, $this->src_patterns() ) ) {
			return $tag;
		}

		return $this->gate_tag( $tag );
	}

	/**
	 * Starts buffering.
	 */
	public function start_buffer() {
		if ( Plugin::is_editing_context() ) {
			return;
		}

		if ( ob_start() ) {
			$this->buffer_level = ob_get_level();
		}
	}

	/**
	 * Rewrites analytics scripts in the buffer and flushes it.
	 */
	public function end_buffer() {
		if ( ! $this->buffer_level ) {
			return;
		}

		$level              = $this->buffer_level;
		$this->buffer_level = 0;

		// Something else closed this buffer already.
		if ( ob_get_level() < $level ) {
			return;
		}

		// Flush buffers another hook callback left open into this one, so their
		// output is kept and in order.
		while ( ob_get_level() > $level ) {
			ob_end_flush();
		}

		$html = ob_get_clean();

		if ( false === $html ) {
			return;
		}

		if ( is_string( $html ) && false !== stripos( $html, '<script' ) ) {
			$html = preg_replace_callback(
				'#<script\b[^>]*>(.*?)</script>#is',
				array( $this, 'replace_script' ),
				$html
			);
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Gates a single matched script if it looks like analytics.
	 *
	 * @param array $m Regex match: 0 = full tag, 1 = inline body.
	 * @return string
	 */
	private function replace_script( $m ) {
		$full = $m[0];
		$body = isset( $m[1] ) ? $m[1] : '';

		$open_end = strpos( $full, '>' );
		if ( false === $open_end ) {
			return $full;
		}

		$open = substr( $full, 0, $open_end + 1 );

		if ( $this->already_gated( $open ) ) {
			return $full;
		}

		$is_analytics = false;

		if ( preg_match( '#\ssrc\s*=\s*(["\'])(.*?)\1#i', $open, $src_match ) ) {
			$is_analytics = $this->contains_any( $src_match[2], $this->src_patterns() );
		} elseif ( '' !== trim( $body ) ) {
			$is_analytics = $this->contains_any( $body, $this->inline_patterns() );
		}

		if ( ! $is_analytics ) {
			return $full;
		}

		return $this->gate_tag( $open ) . substr( $full, $open_end + 1 );
	}
}
