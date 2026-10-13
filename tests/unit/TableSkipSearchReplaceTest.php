<?php

namespace DeliciousBrains\WPMDB\Tests\Unit;

use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\FullSite\FullSiteExport;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Http\RemotePost;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Multisite\Multisite;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Replace;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Sql\TableHelper;
use DeliciousBrains\WPMDB\Common\Util\Util;
use PHPUnit\Framework\TestCase;

class TableSkipSearchReplaceTest extends TestCase {

	const OLD_URL = 'https://old.example';
	const NEW_URL = 'https://new.example';

	public function tearDown(): void {
		remove_all_filters( 'wpmdb_skip_search_replace_tables' );
		delete_site_option( 'wpmdb_migration_options' );
		parent::tearDown();
	}

	public function test_export_keeps_serialized_value_on_skipped_table() {
		$table   = $this->make_table( array( 'vbst_options' ) );
		$replace = $this->make_replacer();
		$row     = (object) array(
			'option_value' => serialize( array( 'siteurl' => self::OLD_URL ) ),
		);

		$table->process_row( 'vbst_options', $replace, $row, $this->structure_info(), null, $this->export_state() );

		$this->assertStringContainsString( self::OLD_URL, $table->query_buffer );
		$this->assertStringNotContainsString( self::NEW_URL, $table->query_buffer );
	}

	public function test_export_keeps_serialized_value_when_table_has_temp_prefix() {
		$table   = $this->make_table( array( 'vbst_options' ) );
		$replace = $this->make_replacer();
		$row     = (object) array(
			'option_value' => serialize( array( 'siteurl' => self::OLD_URL ) ),
		);
		$state   = $this->export_state();
		$state['temp_prefix'] = 'wp_mig_';

		$table->process_row( 'wp_mig_vbst_options', $replace, $row, $this->structure_info(), null, $state );

		$this->assertStringContainsString( self::OLD_URL, $table->query_buffer );
		$this->assertStringNotContainsString( self::NEW_URL, $table->query_buffer );
	}

	public function test_export_still_replaces_serialized_value_on_other_tables() {
		$table   = $this->make_table( array( 'vbst_options' ) );
		$replace = $this->make_replacer();
		$row     = (object) array(
			'option_value' => serialize( array( 'siteurl' => self::OLD_URL ) ),
		);

		$table->process_row( 'vbst_posts', $replace, $row, $this->structure_info(), null, $this->export_state() );

		$this->assertStringContainsString( self::NEW_URL, $table->query_buffer );
		$this->assertStringNotContainsString( self::OLD_URL, $table->query_buffer );
	}

	public function test_find_replace_stage_skips_table_without_scanning() {
		$probe = $this->make_probe( array( 'vbst_options' ) );

		$result = $probe->process_table( 'vbst_options', null, $this->find_replace_state() );

		$this->assertFalse( $probe->reached_processing );
		$this->assertEquals( -1, $result['current_row'] );
	}

	public function test_find_replace_stage_still_processes_other_tables() {
		$probe = $this->make_probe( array( 'vbst_options' ) );

		$probe->process_table( 'vbst_posts', null, $this->find_replace_state() );

		$this->assertTrue( $probe->reached_processing );
	}

	/**
	 * @param array $skip_tables
	 * @return Table
	 */
	private function make_table( $skip_tables ) {
		$form_data = new FormData(
			$this->createMock( Util::class ),
			$this->createMock( MigrationStateManager::class )
		);
		$form_data->setFormData(
			array(
				'select_tables_skip_search_replace' => $skip_tables,
				'replace_guids'                      => '0',
			)
		);

		$util = $this->createMock( Util::class );
		$util->method( 'get_bottleneck' )->willReturn( 1000000 );

		$properties              = $this->createMock( Properties::class );
		$properties->temp_prefix = '_mig_';

		$table = new Table(
			$this->createMock( Filesystem::class ),
			$util,
			$this->createMock( ErrorLog::class ),
			$this->createMock( MigrationStateManager::class ),
			$form_data,
			new TableHelper(
				$form_data,
				$this->createMock( MigrationStateManager::class ),
				$this->createMock( Http::class )
			),
			$this->createMock( Multisite::class ),
			$this->createMock( Http::class ),
			$this->createMock( Helper::class ),
			$this->createMock( RemotePost::class ),
			$properties,
			$this->make_replacer(),
			$this->createMock( FullSiteExport::class )
		);
		$table->row_tracker = 0;
		$query_size         = new \ReflectionProperty( Table::class, 'query_size' );
		$query_size->setAccessible( true );
		$query_size->setValue( $table, 0 );

		return $table;
	}

	/**
	 * @param array $skip_tables
	 * @return TableSkipSearchReplaceProbe
	 */
	private function make_probe( $skip_tables ) {
		$form_data = new FormData(
			$this->createMock( Util::class ),
			$this->createMock( MigrationStateManager::class )
		);
		$form_data->setFormData(
			array(
				'select_tables_skip_search_replace' => $skip_tables,
			)
		);

		$http = $this->createMock( Http::class );
		$http->method( 'end_ajax' )->willReturnArgument( 0 );

		$properties              = $this->createMock( Properties::class );
		$properties->temp_prefix = '_mig_';

		return new TableSkipSearchReplaceProbe(
			$this->createMock( Filesystem::class ),
			$this->createMock( Util::class ),
			$this->createMock( ErrorLog::class ),
			$this->createMock( MigrationStateManager::class ),
			$form_data,
			$this->createMock( TableHelper::class ),
			$this->createMock( Multisite::class ),
			$http,
			$this->createMock( Helper::class ),
			$this->createMock( RemotePost::class ),
			$properties,
			$this->createMock( Replace::class ),
			$this->createMock( FullSiteExport::class )
		);
	}

	/**
	 * @return Replace
	 */
	private function make_replacer() {
		$test = new ReplaceSkipSearchReplaceTest();
		$method = new \ReflectionMethod( ReplaceSkipSearchReplaceTest::class, 'make_replace' );
		$method->setAccessible( true );

		return $method->invoke( $test, 'vbst_posts', 'wp_mig_' );
	}

	/**
	 * @return array
	 */
	private function structure_info() {
		return array(
			'defs'      => array(),
			'ints'      => array(),
			'bins'      => array(),
			'bits'      => array(),
			'field_set' => array(),
			'points'    => array(),
		);
	}

	/**
	 * @return array
	 */
	private function export_state() {
		return array(
			'stage'                => 'tables',
			'intent'               => 'savefile',
			'source_prefix'        => 'vbst_',
			'destination_prefix'   => 'vbst_',
			'temp_prefix'          => '_mig_',
		);
	}

	/**
	 * @return array
	 */
	private function find_replace_state() {
		return array(
			'stage'              => 'find_replace',
			'intent'             => 'find_replace',
			'source_prefix'      => 'vbst_',
			'destination_prefix' => 'vbst_',
			'temp_prefix'        => '_mig_',
			'current_row'        => '0',
			'site_details'       => array(),
		);
	}
}

class TableSkipSearchReplaceProbe extends Table {

	public $reached_processing = false;

	public function get_structure_info( $table, $table_structure = array(), $state_data = array() ) {
		return array(
			'defs'      => array(),
			'ints'      => array(),
			'bins'      => array(),
			'bits'      => array(),
			'field_set' => array(),
			'points'    => array(),
		);
	}

	public function pre_process_data( $table, $target_table_name, $temp_table_name, $fp, $state_data ) {
		$this->reached_processing = true;
	}

	public function build_select_query( $table, $row_start, $structure_info, $state_data ) {
		$this->reached_processing = true;

		return new \WP_Error( 'scanned', 'scanned' );
	}
}
