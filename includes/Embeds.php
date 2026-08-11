<?php
/**
 * Intercepts third-party iframe embeds and holds them until consent.
 *
 * The real src is moved to data-just-cookies-src server-side so the browser never
 * contacts the provider before the visitor accepts the "embeds" category.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites matching iframes in filtered content.
 */
class Embeds {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Host fragments mapped to a provider key.
	 *
	 * @var array
	 */
	private $providers;

	/**
	 * Provider keys the site blocks.
	 *
	 * @var string[]
	 */
	private $enabled;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;

		if ( Plugin::is_admin_request() || ! $settings->get( 'enabled' ) || ! $settings->get( 'block_embeds' ) ) {
			return;
		}

		$this->enabled = $settings->enabled_providers();

		if ( ! $this->enabled ) {
			return;
		}

		// Matched as substrings of a lowercased src, first hit wins, so the more
		// specific host goes above the bare domain.
		$this->providers = array(
			'youtube-nocookie.com'  => 'youtube',
			'youtube.com'           => 'youtube',
			'youtu.be'              => 'youtube',
			'player.vimeo.com'      => 'vimeo',
			'vimeo.com'             => 'vimeo',
			'w.soundcloud.com'      => 'soundcloud',
			'soundcloud.com'        => 'soundcloud',
			'google.com/maps'       => 'googlemaps',
			'maps.google.'          => 'googlemaps',
			'calendar.google.com'   => 'googlecalendar',
			'docs.google.com'       => 'googledocs',
			'open.spotify.com'      => 'spotify',
			'embed.spotify.com'     => 'spotify',
			'dailymotion.com/embed' => 'dailymotion',
			'dai.ly'                => 'dailymotion',
			'player.twitch.tv'      => 'twitch',
			'clips.twitch.tv'       => 'twitch',
			'fast.wistia.net'       => 'wistia',
			'wistia.net'            => 'wistia',
			'wistia.com'            => 'wistia',
			'mixcloud.com'          => 'mixcloud',
			'bandcamp.com'          => 'bandcamp',
			'calendly.com'          => 'calendly',
			'typeform.com'          => 'typeform',
			'loom.com/embed'        => 'loom',
		);

		foreach ( $this->content_filters() as $hook ) {
			add_filter( $hook, array( $this, 'block_iframes_in_content' ), 20 );
		}
	}

	/**
	 * Host fragments mapped to a provider key, for links rather than iframes.
	 *
	 * A lightbox is handed the page URL — youtube.com/watch, vimeo.com/123 —
	 * and builds the iframe itself in the browser, so these match at domain
	 * level where the iframe list above matches specific embed paths. Keep the
	 * two in step: same provider keys, both need the entry when one is added.
	 *
	 * @return array
	 */
	public static function link_hosts() {
		return array(
			'youtube-nocookie.com' => 'youtube',
			'youtube.com'          => 'youtube',
			'youtu.be'             => 'youtube',
			'vimeo.com'            => 'vimeo',
			'soundcloud.com'       => 'soundcloud',
			'google.com/maps'      => 'googlemaps',
			'maps.google.'         => 'googlemaps',
			'calendar.google.com'  => 'googlecalendar',
			'docs.google.com'      => 'googledocs',
			'spotify.com'          => 'spotify',
			'dailymotion.com'      => 'dailymotion',
			'dai.ly'               => 'dailymotion',
			'twitch.tv'            => 'twitch',
			'wistia.net'           => 'wistia',
			'wistia.com'           => 'wistia',
			'mixcloud.com'         => 'mixcloud',
			'bandcamp.com'         => 'bandcamp',
			'calendly.com'         => 'calendly',
			'typeform.com'         => 'typeform',
			'loom.com'             => 'loom',
		);
	}

	/**
	 * CSS selectors marking a link a lightbox will open in place.
	 *
	 * Only links matching one of these are intercepted, so an ordinary link to
	 * a YouTube channel still navigates normally. Covers the common libraries;
	 * a theme using its own marker adds it through the filter.
	 *
	 * @return string[]
	 */
	public static function lightbox_selectors() {
		/**
		 * Filters the selectors treated as lightbox links.
		 *
		 * @param string[] $selectors CSS selectors.
		 */
		$selectors = apply_filters(
			'just_cookies_lightbox_selectors',
			array(
				'[data-fancybox]',
				'.fancybox',
				'.wpex-lightbox',
				'[data-lity]',
				'.glightbox',
				'.magnific-popup',
				'.mfp-iframe',
				'.lightbox',
				'[data-elementor-open-lightbox]',
			)
		);

		return array_values( array_filter( array_map( 'strval', (array) $selectors ) ) );
	}

	/**
	 * CSS selectors for wrappers holding a play overlay over a parked iframe.
	 *
	 * The iframe keeps its URL in data-src and has no src to take away, so the
	 * click on the overlay is the only place to hold it. Clicks are only
	 * inspected inside these wrappers; a theme with its own markup adds it
	 * through the filter.
	 *
	 * @return string[]
	 */
	public static function video_wrappers() {
		/**
		 * Filters the selectors treated as lazy video wrappers.
		 *
		 * @param string[] $selectors CSS selectors.
		 */
		$selectors = apply_filters(
			'just_cookies_video_wrappers',
			array(
				'.vcex-video',
			)
		);

		return array_values( array_filter( array_map( 'strval', (array) $selectors ) ) );
	}

	/**
	 * Filter hooks whose output is scanned for blockable content.
	 *
	 * Extendable for themes or plugins that render embeds through their own
	 * filters, via the setting or the just_cookies_embed_content_filters filter.
	 *
	 * @return string[]
	 */
	private function content_filters() {
		// widget_text_content is where the text widget resolves oEmbeds;
		// widget_text runs earlier and only sees the raw URL. embed_oembed_html
		// is filtered after the oEmbed cache is written, so the stored markup
		// stays clean. oembed_result runs before the write and is left alone.
		$hooks = array(
			'the_content',
			'widget_text',
			'widget_text_content',
			'widget_block_content',
			'embed_oembed_html',
		);

		$extra = preg_split( '/[\r\n,]+/', (string) $this->settings->get( 'embed_content_filters' ) );
		foreach ( (array) $extra as $hook ) {
			$hook = trim( $hook );
			if ( '' !== $hook ) {
				$hooks[] = $hook;
			}
		}

		/**
		 * Filters the hooks scanned for blockable content.
		 *
		 * @param string[] $hooks Filter hook names.
		 */
		$hooks = apply_filters( 'just_cookies_embed_content_filters', $hooks );

		return array_values( array_unique( array_filter( array_map( 'strval', (array) $hooks ) ) ) );
	}

	/**
	 * Rewrites matching iframes in a content string.
	 *
	 * @param string $content Content HTML.
	 * @return string
	 */
	public function block_iframes_in_content( $content ) {
		if ( ! is_string( $content ) || false === stripos( $content, '<iframe' ) ) {
			return $content;
		}

		if ( Plugin::is_editing_context() ) {
			return $content;
		}

		// Matches the self-closing form first so it can't span two elements.
		return preg_replace_callback(
			'#<iframe\b[^>]*?/>|<iframe\b[^>]*>.*?</iframe>#is',
			array( $this, 'replace_iframe' ),
			$content
		);
	}

	/**
	 * Replaces a single matched iframe if it belongs to an enabled provider.
	 *
	 * @param array $m Regex match.
	 * @return string
	 */
	private function replace_iframe( $m ) {
		$iframe = $m[0];

		if ( ! preg_match( '#\ssrc\s*=\s*(["\'])(.*?)\1#i', $iframe, $src_match ) ) {
			return $iframe;
		}

		$src      = html_entity_decode( $src_match[2], ENT_QUOTES );
		$provider = $this->match_provider( $src );

		if ( ! $provider || ! in_array( $provider, $this->enabled, true ) ) {
			return $iframe;
		}

		$src = $this->harden_src( $provider, $src );

		// Strip the live src and stash the real one for the client to restore.
		$blocked = preg_replace( '#\ssrc\s*=\s*(["\']).*?\1#i', '', $iframe, 1 );
		$blocked = preg_replace(
			'#<iframe\b#i',
			'<iframe data-just-cookies-src="' . esc_attr( $src ) . '"',
			$blocked,
			1
		);

		// An iframe has no self-closing form in HTML: the parser would treat the
		// placeholder markup as iframe content, so the tag is closed explicitly.
		if ( false === stripos( $blocked, '</iframe>' ) ) {
			$blocked = preg_replace( '#\s*/?>$#', '></iframe>', $blocked, 1 );
		}

		return $this->wrap_in_placeholder( $provider, $blocked );
	}

	/**
	 * Returns the provider key for a src, or empty string.
	 *
	 * @param string $src Iframe src.
	 * @return string
	 */
	private function match_provider( $src ) {
		$src = strtolower( $src );
		foreach ( $this->providers as $needle => $provider ) {
			if ( str_contains( $src, $needle ) ) {
				return $provider;
			}
		}
		return '';
	}

	/**
	 * Applies privacy hardening to a provider URL.
	 *
	 * @param string $provider Provider key.
	 * @param string $src      Original src.
	 * @return string
	 */
	private function harden_src( $provider, $src ) {
		if ( 'youtube' === $provider ) {
			$src = preg_replace( '#https?://(www\.)?youtube\.com#i', 'https://www.youtube-nocookie.com', $src );
		}

		if ( 'vimeo' === $provider && ! str_contains( $src, 'dnt=' ) ) {
			$src .= ( str_contains( $src, '?' ) ? '&' : '?' ) . 'dnt=1';
		}

		return $src;
	}

	/**
	 * Inline style giving the placeholder the embed's own proportions.
	 *
	 * Taken from the iframe's width and height attributes so the placeholder
	 * occupies the space the embed will take, and accepting it does not shift
	 * the page. Returns an empty string when the attributes are missing or not
	 * plain numbers, leaving the stylesheet's min-height to size it.
	 *
	 * @param string $iframe Iframe HTML.
	 * @return string
	 */
	private function ratio_style( $iframe ) {
		if ( ! preg_match( '#\swidth\s*=\s*(["\'])(\d+)\1#i', $iframe, $w )
			|| ! preg_match( '#\sheight\s*=\s*(["\'])(\d+)\1#i', $iframe, $h ) ) {
			return '';
		}

		$width  = (int) $w[2];
		$height = (int) $h[2];

		if ( $width < 1 || $height < 1 ) {
			return '';
		}

		return sprintf( 'aspect-ratio:%d/%d;min-height:0;', $width, $height );
	}

	/**
	 * Wraps a blocked iframe in the placeholder markup.
	 *
	 * @param string $provider Provider key.
	 * @param string $iframe   Blocked iframe HTML.
	 * @return string
	 */
	private function wrap_in_placeholder( $provider, $iframe ) {
		$labels = Plugin::instance()->cookie_tables->provider_labels();
		$label  = isset( $labels[ $provider ] ) ? $labels[ $provider ] : ucfirst( $provider );

		$notice = str_replace( '{provider}', $label, $this->settings->text( 'embed_notice' ) );

		$ratio = $this->ratio_style( $iframe );

		$html  = '<div class="just-cookies-embed" data-just-cookies-category="embeds" data-just-cookies-provider="' . esc_attr( $provider ) . '"';
		$html .= $ratio ? ' style="' . esc_attr( $ratio ) . '"' : '';
		$html .= '>';
		$html .= $iframe;
		$html .= '<div class="just-cookies-embed__overlay" role="group" aria-label="' . esc_attr( $label ) . '">';
		$html .= '<p class="just-cookies-embed__text">' . wp_kses_post( $notice ) . '</p>';
		$html .= '<div class="just-cookies-embed__actions">';
		$html .= '<button type="button" class="just-cookies-embed__load">' . esc_html__( 'Load content', 'just-cookies' ) . '</button>';
		$html .= '<button type="button" class="just-cookies-embed__prefs">' . esc_html__( 'Cookie settings', 'just-cookies' ) . '</button>';
		$html .= '</div></div></div>';

		return $html;
	}
}
