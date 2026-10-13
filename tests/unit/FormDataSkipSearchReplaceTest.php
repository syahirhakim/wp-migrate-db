<?php

namespace DeliciousBrains\WPMDB\Tests\Unit;

use DeliciousBrains\WPMDB\Common\FormData\FormData;
use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Tests\Support\MigrationFormFactory;
use PHPUnit\Framework\TestCase;

class FormDataSkipSearchReplaceTest extends TestCase {

	public function tearDown(): void {
		remove_all_filters( 'wpmdb_skip_search_replace_tables' );
		delete_site_option( 'wpmdb_migration_options' );
		parent::tearDown();
	}

	public function test_form_data_compat_maps_manual_select_tables() {
		$form = $this->make_form_data();
		$data = MigrationFormFactory::form_data(
			array(
				'search_replace_skip_option'          => 'manual_select',
				'search_replace_skip_tables_selected' => array( 'vbst_options', 'vbst_posts' ),
			)
		);

		$compat = $form->form_data_compat( $data );

		$this->assertSame(
			array( 'vbst_options', 'vbst_posts' ),
			$compat['select_tables_skip_search_replace']
		);
	}

	public function test_form_data_compat_clears_skip_list_when_option_is_none() {
		$form = $this->make_form_data();
		$data = MigrationFormFactory::form_data(
			array(
				'search_replace_skip_option'          => 'none',
				'search_replace_skip_tables_selected' => array( 'vbst_options' ),
			)
		);

		$compat = $form->form_data_compat( $data );

		$this->assertSame( array(), $compat['select_tables_skip_search_replace'] );
	}

	public function test_parse_strips_unknown_keys_and_keeps_skip_list() {
		$form = $this->make_form_data();
		$payload = MigrationFormFactory::form_data(
			array(
				'search_replace_skip_option'          => 'manual_select',
				'search_replace_skip_tables_selected' => array( 'vbst_options' ),
			),
			array(
				'not_a_field' => 'drop-me',
			)
		);

		$this->assertContains( 'select_tables_skip_search_replace', $form->get_accepted_fields() );
		$this->assertContains( 'select_tables_skip_search_replace', $form->compat_fields );

		$saved = $form->parse_and_save_migration_form_data( wp_json_encode( $payload ) );

		$this->assertArrayNotHasKey( 'not_a_field', $saved );
		$this->assertSame( array( 'vbst_options' ), $saved['select_tables_skip_search_replace'] );
		$this->assertSame( $saved, Persistence::getMigrationOptions() );
	}

	public function test_merge_skip_search_replace_tables_from_form_dedupes_with_default() {
		$form = $this->make_form_data();
		$form->setFormData(
			array(
				'select_tables_skip_search_replace' => array( 'vbst_options', 'eum_logs', 'vbst_options' ),
			)
		);

		$merged = $form->merge_skip_search_replace_tables_from_form( array( 'eum_logs' ) );

		$this->assertEquals( array( 'eum_logs', 'vbst_options' ), array_values( $merged ) );
		$this->assertNotFalse(
			has_filter( 'wpmdb_skip_search_replace_tables', array( $form, 'merge_skip_search_replace_tables_from_form' ) )
		);
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
