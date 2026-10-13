<?php

namespace DeliciousBrains\WPMDB\Tests\Unit {

use DeliciousBrains\WPMDB\Common\Cli\Cli;
use DeliciousBrains\WPMDB\Common\Cli\CliManager;
use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Migration\FinalizeMigration;
use DeliciousBrains\WPMDB\Common\Migration\InitiateMigration;
use DeliciousBrains\WPMDB\Common\Migration\MigrationManager;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Util\Util;
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
