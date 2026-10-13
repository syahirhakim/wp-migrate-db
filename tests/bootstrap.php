<?php
/**
 * PHPUnit bootstrap for WP Migrate DB.
 *
 * Order: shipped plugin vendor → plugin class autoload → tests/vendor (PHPUnit / wp-phpunit).
 * Dev dependencies stay in tests/vendor and never replace the plugin vendor tree.
 */

if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
	$_SERVER['REQUEST_URI'] = '/';
}
register_shutdown_function( function () {
	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		$_SERVER['REQUEST_URI'] = '/';
	}
} );

$plugin_root = dirname( __DIR__ );

if ( ! defined( 'WPMDB_PATH' ) ) {
	define( 'WPMDB_PATH', $plugin_root . '/' );
}

$plugin_autoload = $plugin_root . '/vendor/autoload.php';
if ( ! file_exists( $plugin_autoload ) ) {
	fwrite( STDERR, "Missing plugin vendor autoload at vendor/autoload.php.\n" );
	exit( 1 );
}
require_once $plugin_autoload;
require_once $plugin_root . '/class/autoload.php';

$tests_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $tests_autoload ) ) {
	fwrite( STDERR, "Missing tests/vendor. Run `composer install` in tests/.\n" );
	exit( 1 );
}
require_once $tests_autoload;

$tests_config = dirname( $plugin_root ) . '/vbest-tuition/tests/wp-tests-config.php';
if ( ! file_exists( $tests_config ) ) {
	fwrite( STDERR, "Missing WordPress tests config: {$tests_config}\n" );
	exit( 1 );
}

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $tests_config );
$_ENV['WP_PHPUNIT__TESTS_CONFIG'] = $tests_config;
$_SERVER['WP_PHPUNIT__TESTS_CONFIG'] = $tests_config;

$tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $tests_dir ) {
	$tests_dir = __DIR__ . '/vendor/wp-phpunit/wp-phpunit';
}

$tests_dir    = rtrim( $tests_dir, '/\\' );
$wp_functions = $tests_dir . '/includes/functions.php';
$wp_bootstrap = $tests_dir . '/includes/bootstrap.php';

if ( ! file_exists( $wp_functions ) || ! file_exists( $wp_bootstrap ) ) {
	fwrite( STDERR, "Unable to locate WordPress tests library. Run `composer install` in tests/.\n" );
	exit( 1 );
}

require_once $wp_functions;
require_once $wp_bootstrap;
