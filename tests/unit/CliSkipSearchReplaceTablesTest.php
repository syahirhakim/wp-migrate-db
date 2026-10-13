<?php

namespace DeliciousBrains\WPMDB\Tests\Unit {

use DeliciousBrains\WPMDB\Common\Cli\Cli;
use DeliciousBrains\WPMDB\Common\Cli\CliManager;
use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Migration\FinalizeMigration;
use DeliciousBrains\WPMDB\Common\Migration\InitiateMigration;
use DeliciousBrains\WPMDB\Common\Migration\MigrationManager;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Tests\Support\MigrationFormFactory;
use PHPUnit\Framework\TestCase;

class CliSkipSearchReplaceTablesTest extends TestCase {

	public function test_skip_search_replace_tables_arg_sets_profile_list() {
		$cli     = $this->make_cli();
		$profile = $cli->get_profile_data_from_args(
			array(),
			array(
				'action'                       => 'savefile',
				'skip-search-replace-tables'   => 'foo,bar',
			)
		);

		$this->assertIsArray( $profile );
		$this->assertSame( array( 'foo', 'bar' ), $profile['select_tables_skip_search_replace'] );
		$this->assertSame( 'manual_select', $profile['current_migration']['search_replace_skip_option'] );
		$this->assertSame( array( 'foo', 'bar' ), $profile['current_migration']['search_replace_skip_tables_selected'] );
	}

	public function tearDown(): void {
		remove_all_filters( 'wpmdb_rows_per_segment' );
		parent::tearDown();
	}

	public function test_missing_skip_arg_leaves_an_empty_list() {
		$cli     = $this->make_cli();
		$profile = $cli->get_profile_data_from_args(
			array(),
			array(
				'action' => 'savefile',
			)
		);

		$this->assertIsArray( $profile );
		$this->assertSame( array(), $profile['select_tables_skip_search_replace'] );
		$this->assertSame( 'none', $profile['current_migration']['search_replace_skip_option'] );
	}

	public function test_rows_per_export_arg_sets_profile_value() {
		$cli     = $this->make_cli();
		$profile = $cli->get_profile_data_from_args(
			array(),
			array(
				'action'           => 'savefile',
				'rows-per-export'  => '2500',
			)
		);

		$this->assertIsArray( $profile );
		$this->assertSame( 2500, $profile['rows_per_export'] );
		$this->assertSame( 2500, $profile['current_migration']['rows_per_export'] );
	}

	public function test_savefile_uses_one_thousand_rows_per_segment() {
		$table  = $this->createMock( Table::class );
		$table->method( 'get_post_types' )->willReturn( array() );
		$table->method( 'get_tables' )->willReturn( array( 'vbst_posts' ) );
		$table->rows_per_segment = 100;

		$cli    = $this->make_cli_with_table( $table );
		$result = $cli->cli_migration( $this->profile_stopped_on_missing_table( 'savefile' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 1000, $table->rows_per_segment );
		$this->assertSame( 1000, (int) apply_filters( 'wpmdb_rows_per_segment', 100 ) );
	}

	public function test_savefile_uses_custom_rows_per_segment_from_profile() {
		$table  = $this->createMock( Table::class );
		$table->method( 'get_post_types' )->willReturn( array() );
		$table->method( 'get_tables' )->willReturn( array( 'vbst_posts' ) );
		$table->rows_per_segment = 100;

		$cli    = $this->make_cli_with_table( $table );
		$profile = $this->profile_stopped_on_missing_table( 'savefile' );
		$profile['rows_per_export']                      = 2500;
		$profile['current_migration']['rows_per_export'] = 2500;
		$result = $cli->cli_migration( $profile );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 2500, $table->rows_per_segment );
	}

	public function test_find_replace_keeps_the_default_row_segment() {
		$table  = $this->createMock( Table::class );
		$table->method( 'get_post_types' )->willReturn( array() );
		$table->method( 'get_tables' )->willReturn( array( 'vbst_posts' ) );
		$table->rows_per_segment = 100;

		$cli    = $this->make_cli_with_table( $table );
		$result = $cli->cli_migration( $this->profile_stopped_on_missing_table( 'find_replace' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 100, $table->rows_per_segment );
		$this->assertSame( 100, (int) apply_filters( 'wpmdb_rows_per_segment', 100 ) );
	}

	/**
	 * @param string $action
	 * @return array
	 */
	private function profile_stopped_on_missing_table( $action ) {
		return array_merge(
			MigrationFormFactory::form_data(
				array(
					'intent'          => $action,
					'stages'          => array( 'tables' ),
					'tables_option'   => 'selected',
					'tables_selected' => array( 'not_a_table' ),
				)
			),
			array(
				'action'        => $action,
				'select_tables' => array( 'not_a_table' ),
			)
		);
	}

	/**
	 * @param Table $table
	 * @return Cli
	 */
	private function make_cli_with_table( $table ) {
		$util = $this->createMock( Util::class );
		$util->method( 'site_details' )->willReturn( array() );

		$real_form_data = new FormData(
			$this->createMock( Util::class ),
			$this->createMock( MigrationStateManager::class )
		);
		$form_data      = $this->createMock( FormData::class );
		$form_data->method( 'parse_and_save_migration_form_data' )->willReturnCallback(
			function ( $json ) use ( $real_form_data ) {
				return $real_form_data->parse_and_save_migration_form_data( $json );
			}
		);
		$form_data->method( 'apply_rows_per_segment_for_intent' )->willReturnCallback(
			function ( $table, $intent ) use ( $real_form_data ) {
				$real_form_data->apply_rows_per_segment_for_intent( $table, $intent );
			}
		);

		return new Cli(
			$form_data,
			$util,
			$this->createMock( CliManager::class ),
			$table,
			$this->createMock( ErrorLog::class ),
			$this->createMock( InitiateMigration::class ),
			$this->createMock( FinalizeMigration::class ),
			$this->createMock( Helper::class ),
			$this->createMock( MigrationManager::class ),
			$this->createMock( MigrationStateManager::class )
		);
	}

	/**
	 * @return Cli
	 */
	private function make_cli() {
		$table = $this->createMock( Table::class );
		$table->method( 'get_post_types' )->willReturn( array() );

		return new Cli(
			$this->createMock( FormData::class ),
			$this->createMock( Util::class ),
			$this->createMock( CliManager::class ),
			$table,
			$this->createMock( ErrorLog::class ),
			$this->createMock( InitiateMigration::class ),
			$this->createMock( FinalizeMigration::class ),
			$this->createMock( Helper::class ),
			$this->createMock( MigrationManager::class ),
			$this->createMock( MigrationStateManager::class )
		);
	}
}
}

namespace {
	if ( ! function_exists( 'wpmdb_cli' ) ) {
		function wpmdb_cli() {
			return new class() {
				public function cli_error( $message ) {
					return new \WP_Error( 'wpmdb_cli_error', $message );
				}
			};
		}
	}
}
