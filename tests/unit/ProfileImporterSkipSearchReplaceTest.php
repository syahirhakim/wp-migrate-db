<?php

namespace DeliciousBrains\WPMDB\Tests\Unit;

use DeliciousBrains\WPMDB\Common\Error\ErrorLog;
use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\Http\Helper;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Http\WPMDBRestAPIServer;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Plugin\Assets;
use DeliciousBrains\WPMDB\Common\Profile\ProfileImporter;
use DeliciousBrains\WPMDB\Common\Profile\ProfileManager;
use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Settings\Settings;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Tests\Support\MigrationFormFactory;
use PHPUnit\Framework\TestCase;

class ProfileImporterSkipSearchReplaceTest extends TestCase {

	public function test_compose_maps_legacy_skip_list_to_manual_select() {
		$profile = $this->legacy_profile( array( 'vbst_options', 'vbst_posts' ) );

		$details = $this->compose( $profile );

		$this->assertSame( 'manual_select', $details['search_replace_skip_option'] );
		$this->assertSame(
			array( 'vbst_options', 'vbst_posts' ),
			$details['search_replace_skip_tables_selected']
		);
	}

	public function test_compose_maps_empty_skip_list_to_none() {
		$profile = $this->legacy_profile( array() );

		$details = $this->compose( $profile );

		$this->assertSame( 'none', $details['search_replace_skip_option'] );
		$this->assertSame( array(), $details['search_replace_skip_tables_selected'] );
	}

	public function test_default_profile_includes_empty_skip_list() {
		$settings = $this->createMock( Settings::class );
		$settings->method( 'get_settings' )->willReturn( array( 'profiles' => array() ) );

		$manager = new ProfileManager(
			$this->createMock( Http::class ),
			$this->createMock( Helper::class ),
			$this->createMock( Properties::class ),
			$settings,
			$this->createMock( MigrationStateManager::class ),
			$this->createMock( Util::class ),
			$this->createMock( ErrorLog::class ),
			$this->createMock( Table::class ),
			$this->createMock( FormData::class ),
			$this->createMock( Assets::class ),
			$this->createMock( WPMDBRestAPIServer::class ),
			$this->createMock( ProfileImporter::class )
		);

		$profile = $manager->get_profile( 0 );

		$this->assertSame( array(), $profile['select_tables_skip_search_replace'] );
		$this->assertSame( 1000, $profile['rows_per_export'] );
	}

	/**
	 * @param array $skip_tables
	 * @return object
	 */
	private function legacy_profile( $skip_tables ) {
		return MigrationFormFactory::legacy_profile(
			array(
				'name'                              => 'fixture-profile',
				'create_backup'                     => '0',
				'backup_option'                     => 'none',
				'table_migrate_option'              => 'migrate_only_with_prefix',
				'exclude_post_types'                => '0',
				'select_tables_skip_search_replace' => $skip_tables,
			)
		);
	}

	/**
	 * @param object $profile_obj
	 * @return array
	 */
	private function compose( $profile_obj ) {
		$importer = new ProfileImporter(
			$this->createMock( Util::class ),
			$this->createMock( Table::class )
		);
		$method = new \ReflectionMethod( ProfileImporter::class, 'composeCurrentMigrationDetails' );
		$method->setAccessible( true );

		return $method->invoke(
			$importer,
			array( 'databaseEnabled' => true ),
			'savefile',
			$profile_obj,
			array(),
			array()
		);
	}
}
