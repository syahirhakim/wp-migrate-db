<?php

namespace DeliciousBrains\WPMDB\Tests\Unit;

use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Tests\Support\MigrationFormFactory;
use DeliciousBrains\WPMDB\Tests\Support\SkipSearchReplaceTableNames;
use PHPUnit\Framework\TestCase;

class TestHarnessTest extends TestCase {

	public function test_wordpress_and_plugin_autoload_are_available() {
		global $wpdb;

		$this->assertTrue( defined( 'ABSPATH' ) );
		$this->assertTrue( defined( 'WPMDB_PATH' ) );
		$this->assertFileExists( WPMDB_PATH . 'vendor/autoload.php' );
		$this->assertFileExists( WPMDB_PATH . 'class/autoload.php' );
		$this->assertInstanceOf( \wpdb::class, $wpdb );
		$this->assertNotSame( '', $wpdb->prefix );

		$plugin_vendor = realpath( WPMDB_PATH . 'vendor' );
		$tests_vendor  = realpath( dirname( __DIR__ ) . '/vendor' );

		$this->assertNotFalse( $plugin_vendor );
		$this->assertNotFalse( $tests_vendor );
		$this->assertNotSame( $plugin_vendor, $tests_vendor );
		$this->assertTrue( class_exists( FormData::class ) );
	}

	public function test_phpunit_is_loaded_from_tests_vendor() {
		$reflection = new \ReflectionClass( TestCase::class );

		$this->assertStringContainsString(
			'tests' . DIRECTORY_SEPARATOR . 'vendor',
			$reflection->getFileName()
		);
	}

	public function test_support_helpers_normalize_and_build_form_fragments() {
		$this->assertSame(
			'vbst_options',
			SkipSearchReplaceTableNames::normalize_table_name( 'wp_mig_vbst_options', 'wp_mig_' )
		);
		$this->assertSame(
			'vbst_options',
			SkipSearchReplaceTableNames::normalize_table_name( 'vbst_options', 'wp_mig_' )
		);

		$form = MigrationFormFactory::form_data(
			array(
				'search_replace_skip_option'          => 'manual_select',
				'search_replace_skip_tables_selected' => array( 'vbst_options' ),
			)
		);

		$this->assertSame( 'manual_select', $form['current_migration']['search_replace_skip_option'] );
		$this->assertSame( array( 'vbst_options' ), $form['current_migration']['search_replace_skip_tables_selected'] );

		$profile = MigrationFormFactory::legacy_profile(
			array(
				'select_tables_skip_search_replace' => array( 'vbst_options' ),
			)
		);

		$this->assertSame( array( 'vbst_options' ), $profile->select_tables_skip_search_replace );
	}
}
