<?php

namespace DeliciousBrains\WPMDB\Tests\Unit;

use DeliciousBrains\WPMDB\Common\Profile\ProfileImporter;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Tests\Support\MigrationFormFactory;
use PHPUnit\Framework\TestCase;

class ProfileImporterRowsPerExportTest extends TestCase {

	public function test_compose_maps_rows_per_export_from_legacy_profile() {
		$profile = $this->legacy_profile(
			array(
				'rows_per_export' => 2000,
			)
		);

		$details = $this->compose( $profile );

		$this->assertSame( 2000, $details['rows_per_export'] );
	}

	public function test_compose_defaults_rows_per_export_to_one_thousand() {
		$profile = $this->legacy_profile( array() );

		$details = $this->compose( $profile );

		$this->assertSame( 1000, $details['rows_per_export'] );
	}

	/**
	 * @param array $overrides
	 * @return object
	 */
	private function legacy_profile( $overrides ) {
		return MigrationFormFactory::legacy_profile(
			array_merge(
				array(
					'name'                 => 'fixture-profile',
					'create_backup'        => '0',
					'backup_option'        => 'none',
					'table_migrate_option' => 'migrate_only_with_prefix',
					'exclude_post_types'   => '0',
				),
				$overrides
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
