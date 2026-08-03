<?php
/**
 * PSR-4 autoloader for the plugin namespace.
 *
 * Maps JustCookies\Foo\Bar to includes/Foo/Bar.php.
 *
 * @package JustCookies
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( $class ) {
		$prefix = 'JustCookies\\';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$path = __DIR__ . '/includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
