<?php

namespace DeliciousBrains\WPMDB\Tests\Unit;

use DeliciousBrains\WPMDB\Common\DryRun\DiffInterpreter;
use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Http\WPMDBRestAPIServer;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Replace;
use DeliciousBrains\WPMDB\Common\Replace\CaseInsensitivePair;
use DeliciousBrains\WPMDB\Common\Replace\PairFactory;
use DeliciousBrains\WPMDB\Common\Sql\TableHelper;
use DeliciousBrains\WPMDB\Common\Util\Util;
use PHPUnit\Framework\TestCase;

class ReplaceSkipSearchReplaceTest extends TestCase {

	const OLD_URL = 'https://old.example';
	const NEW_URL = 'https://new.example';

	public function tearDown(): void {
		remove_all_filters( 'wpmdb_skip_search_replace_tables' );
		parent::tearDown();
	}

	public function test_recursive_replace_leaves_full_table_name_unchanged() {
		$replace = $this->make_replace( 'vbst_options', 'wp_mig_' );
		$this->skip_tables( array( 'vbst_options' ) );

		$result = $replace->recursive_unserialize_replace( self::OLD_URL );

		$this->assertSame( self::OLD_URL, $result );
	}

	public function test_recursive_replace_leaves_temp_prefixed_table_unchanged() {
		$replace = $this->make_replace( 'wp_mig_vbst_options', 'wp_mig_' );
		$this->skip_tables( array( 'vbst_options' ) );

		$result = $replace->recursive_unserialize_replace( self::OLD_URL );

		$this->assertSame( self::OLD_URL, $result );
	}

	public function test_recursive_replace_leaves_default_suffix_table_unchanged() {
		global $wpdb;

		$replace = $this->make_replace( $wpdb->base_prefix . 'eum_logs', '_mig_' );

		$result = $replace->recursive_unserialize_replace( self::OLD_URL );

		$this->assertSame( self::OLD_URL, $result );
	}

	public function test_recursive_replace_leaves_temp_prefixed_suffix_table_unchanged() {
		global $wpdb;

		$temp_prefix = '_mig_';
		$replace     = $this->make_replace( $temp_prefix . $wpdb->base_prefix . 'eum_logs', $temp_prefix );
		$replace->set( 'intent', 'find_replace' );

		$result = $replace->recursive_unserialize_replace( self::OLD_URL );

		$this->assertSame( self::OLD_URL, $result );
	}

	public function test_recursive_replace_still_replaces_other_tables() {
		$replace = $this->make_replace( 'vbst_posts', 'wp_mig_' );
		$this->skip_tables( array( 'vbst_options' ) );

		$result = $replace->recursive_unserialize_replace( self::OLD_URL );

		$this->assertSame( self::NEW_URL, $result );
	}

	/**
	 * @param array $tables
	 */
	private function skip_tables( $tables ) {
		add_filter(
			'wpmdb_skip_search_replace_tables',
			function ( $skipped ) use ( $tables ) {
				return array_merge( $skipped, $tables );
			}
		);
	}

	/**
	 * @param string $table
	 * @param string $temp_prefix
	 * @return Replace
	 */
	private function make_replace( $table, $temp_prefix ) {
		$properties              = $this->createMock( Properties::class );
		$properties->temp_prefix = $temp_prefix;

		$replace = new Replace(
			$this->createMock( MigrationStateManager::class ),
			$this->createMock( TableHelper::class ),
			$this->createMock( ErrorLog::class ),
			$this->createMock( Util::class ),
			$this->createMock( FormData::class ),
			$properties,
			$this->createMock( PairFactory::class ),
			$this->createMock( WPMDBRestAPIServer::class ),
			$this->createMock( Helper::class ),
			$this->createMock( Http::class ),
			$this->createMock( DiffInterpreter::class )
		);

		$replace->set( 'table', $table );
		$replace->set( 'intent', 'savefile' );
		$replace->set( 'column', 'option_value' );
		$replace->set( 'search', array( self::OLD_URL ) );
		$replace->set( 'replace', array( self::NEW_URL ) );
		$replace->set( 'json_merged', true );
		$replace->set(
			'pairs',
			array(
				new CaseInsensitivePair( self::OLD_URL, self::NEW_URL ),
			)
		);

		return $replace;
	}
}
