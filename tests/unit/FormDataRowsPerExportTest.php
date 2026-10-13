<?php

namespace DeliciousBrains\WPMDB\Tests\Unit;

use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Tests\Support\MigrationFormFactory;
use PHPUnit\Framework\TestCase;

class FormDataRowsPerExportTest extends TestCase {

	public function tearDown(): void {
		remove_all_filters( 'wpmdb_rows_per_segment' );
		delete_site_option( 'wpmdb_migration_options' );
		parent::tearDown();
	}

	public function test_form_data_compat_maps_rows_per_export() {
		$form = $this->make_form_data();
		$data = MigrationFormFactory::form_data(
			array(
				'rows_per_export' => 2500,
			)
		);

		$compat = $form->form_data_compat( $data );

		$this->assertSame( 2500, $compat['rows_per_export'] );
	}

	public function test_parse_keeps_rows_per_export() {
		$form    = $this->make_form_data();
		$payload = MigrationFormFactory::form_data(
			array(
				'rows_per_export' => 500,
			)
		);

		$saved = $form->parse_and_save_migration_form_data( wp_json_encode( $payload ) );

		$this->assertSame( 500, $saved['rows_per_export'] );
	}

	public function test_resolve_rows_per_segment_for_savefile_defaults_to_one_thousand() {
		$form = $this->make_form_data();

		$this->assertSame( 1000, $form->resolve_rows_per_segment_for_intent( 'savefile' ) );
		$this->assertSame( 100, $form->resolve_rows_per_segment_for_intent( 'find_replace' ) );
	}

	public function test_apply_rows_per_segment_for_intent_updates_table() {
		$form  = $this->make_form_data();
		$table = $this->createMock( Table::class );
		$form->setFormData(
			array(
				'rows_per_export' => 750,
			)
		);

		$form->apply_rows_per_segment_for_intent( $table, 'savefile' );

		$this->assertSame( 750, $table->rows_per_segment );
		$this->assertSame( 750, (int) apply_filters( 'wpmdb_rows_per_segment', 100 ) );
	}

	public function test_sanitize_rows_per_export_clamps_values() {
		$form = $this->make_form_data();

		$this->assertSame( 1, $form->sanitize_rows_per_export( 0 ) );
		$this->assertSame( 10000, $form->sanitize_rows_per_export( 50000 ) );
	}

	/**
	 * @return FormData
	 */
	private function make_form_data() {
		return new FormData(
			$this->createMock( Util::class ),
			$this->createMock( MigrationStateManager::class )
		);
	}
}
