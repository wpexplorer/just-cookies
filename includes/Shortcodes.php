<?php
/**
 * Front-end shortcodes.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings-link and cookie-table shortcodes.
 */
class Shortcodes {

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

		add_shortcode( 'just_cookies_settings_link', array( $this, 'render_settings_link' ) );
		add_shortcode( 'just_cookies_table', array( $this, 'render_cookie_table' ) );
	}

	/**
	 * Renders a link/button that reopens the preferences modal.
	 *
	 * @param array       $atts    Shortcode attributes.
	 * @param string|null $content Enclosed label.
	 * @return string
	 */
	public function render_settings_link( $atts, $content = null ) {
		$atts  = shortcode_atts(
			array(
				'text'  => __( 'Cookie settings', 'just-cookies' ),
				'class' => '',
			),
			$atts,
			'just_cookies_settings_link'
		);
		$label = $content ? $content : $atts['text'];
		$class = trim( 'just-cookies-open-prefs ' . $atts['class'] );

		return sprintf(
			'<button type="button" class="%s">%s</button>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Resolves the tag each table's heading is wrapped in.
	 *
	 * The right level depends on the headings around the shortcode, which only
	 * the page knows, so it is settable per use and filterable site-wide.
	 * Limited to tags that make sense as a heading; the filter runs before that
	 * check, so a filter cannot widen it either. Escaped again where it is
	 * printed, as everything else that reaches markup is.
	 *
	 * @param string $tag Requested tag.
	 * @return string
	 */
	private function heading_tag( $tag ) {
		/**
		 * Filters the heading tag used above each cookie table.
		 *
		 * @param string $tag Tag name.
		 */
		$tag = tag_escape( (string) apply_filters( 'just_cookies_table_heading_tag', $tag ) );

		return in_array( $tag, array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ), true ) ? $tag : 'h3';
	}

	/**
	 * Renders the full cookie disclosure table for a policy page.
	 *
	 * Headings name the category each table belongs to, which is part of the
	 * disclosure rather than decoration, so they stay on by default even when
	 * only one table is rendered. A page that introduces the table in its own
	 * prose can turn them off with titles="no".
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_cookie_table( $atts ) {
		$atts = shortcode_atts(
			array(
				'titles'    => 'yes',
				'title_tag' => 'h3',
			),
			$atts,
			'just_cookies_table'
		);

		$titles = ! in_array(
			strtolower( trim( (string) $atts['titles'] ) ),
			array( 'no', 'false', '0', 'off' ),
			true
		);

		$tag     = $this->heading_tag( $atts['title_tag'] );
		$headers = $this->tables->headers();

		$groups = array(
			__( 'Strictly Necessary Cookies', 'just-cookies' ) => $this->tables->necessary_rows(),
		);

		if ( $this->settings->get( 'block_analytics' ) ) {
			$groups[ __( 'Analytics Cookies', 'just-cookies' ) ] = $this->tables->analytics_rows();
		}

		$embed_rows = $this->tables->embed_rows();
		if ( $embed_rows ) {
			$groups[ __( 'Embedded Media', 'just-cookies' ) ] = $embed_rows;
		}

		$out = '<div class="just-cookies-cookie-tables">';

		foreach ( $groups as $title => $rows ) {
			$heading = tag_escape( $tag );

			$out .= $titles
				? '<' . $heading . '>' . esc_html( $title ) . '</' . $heading . '>'
				: '';

			// Four columns do not fit a phone. The wrapper is what a stylesheet
			// scrolls; tabindex makes that scroll reachable without a mouse,
			// and the label says which table is being scrolled — the heading
			// above it may not be rendered.
			$out .= '<div class="just-cookies-cookie-table-wrap" tabindex="0" role="region" aria-label="'
				. esc_attr( $title ) . '">';
			$out .= '<table class="just-cookies-cookie-table"><thead><tr>';
			foreach ( $headers as $label ) {
				$out .= '<th>' . esc_html( $label ) . '</th>';
			}
			$out .= '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$out .= '<tr>';
				foreach ( array_keys( $headers ) as $key ) {
					$value = isset( $row[ $key ] ) ? $row[ $key ] : '';
					$out  .= '<td>' . esc_html( $value ) . '</td>';
				}
				$out .= '</tr>';
			}

			$out .= '</tbody></table></div>';
		}

		$out .= '</div>';

		return $out;
	}
}
