<?php
/**
 * Consent banner output on the front end.
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the compiled banner bundle, inlines the runtime config and renders
 * the floating re-open button.
 */
class Banner {

	// Matches the stylesheet, so the setting only emits CSS when it differs.
	const DEFAULT_BUTTON_LAYER = 1000;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Config builder.
	 *
	 * @var ConsentConfig
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings Settings.
	 * @param ConsentConfig $config   Config builder.
	 */
	public function __construct( Settings $settings, ConsentConfig $config ) {
		$this->settings = $settings;
		$this->config   = $config;

		if ( $settings->get( 'enabled' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			if ( $settings->get( 'float_button' ) ) {
				add_action( 'wp_footer', array( $this, 'render_float_button' ) );
			}
		}
	}

	/**
	 * Outputs the fixed floating button that reopens the preferences modal.
	 * Reuses the .just-cookies-open-prefs click handler in the frontend bundle.
	 */
	public function render_float_button() {
		if ( Plugin::is_editing_context() ) {
			return;
		}

		$label    = $this->settings->text( 'float_button_label' );
		$position = $this->settings->get( 'float_button_position' );
		$classes  = array( 'just-cookies-cookie-btn', 'just-cookies-open-prefs', 'is-' . str_replace( ' ', '-', $position ) );

		printf(
			'<button type="button" class="%1$s" aria-label="%2$s">'
			. '<svg class="just-cookies-cookie-btn__icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor">'
			. '<path d="M21.95 11.03a1 1 0 0 0-.86-.98 2.5 2.5 0 0 1-2.06-2.64 1 1 0 0 0-1.2-1.04 2.5 2.5 0 0 1-2.86-2.86 1 1 0 0 0-1.04-1.2A10 10 0 1 0 22 12c0-.33-.02-.65-.05-.97ZM8 15a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm.5-6a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm5 8a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm2.5-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/>'
			. '</svg>'
			. '<span class="just-cookies-cookie-btn__label">%3$s</span></button>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $label ),
			esc_html( $label )
		);
	}

	/**
	 * Builds inline CSS overriding the primary button colors, if configured.
	 *
	 * @return string
	 */
	private function primary_button_css() {
		$bg    = sanitize_hex_color( (string) $this->settings->get( 'primary_button_bg' ) );
		$hover = sanitize_hex_color( (string) $this->settings->get( 'primary_button_hover_bg' ) );

		$vars = array();
		if ( $bg ) {
			$vars[] = '--cc-btn-primary-bg:' . $bg;
			$vars[] = '--cc-btn-primary-border-color:' . $bg;
		}
		if ( $hover ) {
			$vars[] = '--cc-btn-primary-hover-bg:' . $hover;
			$vars[] = '--cc-btn-primary-hover-border-color:' . $hover;
		}

		$css = $vars ? '#cc-main{' . implode( ';', $vars ) . '}' : '';

		return $css . $this->float_button_css();
	}

	/**
	 * Builds inline CSS overriding the floating button colors and stacking
	 * order, if configured.
	 *
	 * @return string
	 */
	private function float_button_css() {
		$rules = array();

		$bg = sanitize_hex_color( (string) $this->settings->get( 'float_button_bg' ) );
		if ( $bg ) {
			$rules[] = 'background:' . $bg;
		}

		$color = sanitize_hex_color( (string) $this->settings->get( 'float_button_color' ) );
		if ( $color ) {
			$rules[] = 'color:' . $color;
		}

		$layer = absint( $this->settings->get( 'float_button_z_index' ) );
		if ( $layer !== self::DEFAULT_BUTTON_LAYER ) {
			$rules[] = 'z-index:' . $layer;
		}

		return $rules ? '.just-cookies-cookie-btn{' . implode( ';', $rules ) . '}' : '';
	}

	/**
	 * Registers and enqueues the frontend assets.
	 */
	public function enqueue_assets() {
		if ( Plugin::is_editing_context() ) {
			return;
		}

		$asset_file = PLUGIN_DIR . 'build/frontend.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => VERSION,
			);

		wp_enqueue_script(
			'just-cookies-frontend',
			PLUGIN_URL . 'build/frontend.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( PLUGIN_DIR . 'build/frontend.css' ) ) {
			wp_enqueue_style(
				'just-cookies-frontend',
				PLUGIN_URL . 'build/frontend.css',
				array(),
				$asset['version']
			);

			$css = $this->primary_button_css();
			if ( $css ) {
				wp_add_inline_style( 'just-cookies-frontend', $css );
			}
		}

		wp_add_inline_script(
			'just-cookies-frontend',
			'window.justCookiesData = ' . wp_json_encode( $this->config->build() ) . ';',
			'before'
		);
	}
}
