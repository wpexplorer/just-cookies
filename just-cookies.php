<?php
/**
 * Plugin Name:       Just Cookies – Cookie Consent & Embed Blocking
 * Plugin URI:        https://wordpress.org/plugins/just-cookies/
 * Description:       Cookie consent and third-party embed blocking — self-hosted, no third-party service. Names specific cookies instead of generic boilerplate and holds third-party content until visitors consent.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            WPExplorer
 * Author URI:        https://www.wpexplorer.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       just-cookies
 *
 * @package JustCookies
 */

namespace JustCookies;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';

define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );
define( __NAMESPACE__ . '\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PLUGIN_DIR . 'autoload.php';

Plugin::instance();
