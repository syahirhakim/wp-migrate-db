<?php

namespace DeliciousBrains\WPMDB\Tests\Support;

/**
 * Build current_migration fragments and legacy profile objects.
 */
class MigrationFormFactory {

	/**
	 * @param array $overrides
	 * @return array
	 */
	public static function current_migration( $overrides = array() ) {
		$migration = array(
			'intent'                              => 'savefile',
			'tables_option'                       => 'all',
			'tables_selected'                     => array(),
			'backup_option'                       => 'none',
			'backup_tables_selected'              => array(),
			'post_types_option'                   => 'all',
			'post_types_selected'                 => array(),
			'advanced_options_selected'           => array(),
			'search_replace_skip_option'          => 'none',
			'search_replace_skip_tables_selected' => array(),
			'rows_per_export'                     => 1000,
		);

		return array_merge( $migration, $overrides );
	}

	/**
	 * @param array $migration_overrides
	 * @param array $extra
	 * @return array
	 */
	public static function form_data( $migration_overrides = array(), $extra = array() ) {
		$form = array(
			'current_migration' => self::current_migration( $migration_overrides ),
		);

		return array_merge( $form, $extra );
	}

	/**
	 * @param array $overrides
	 * @return object
	 */
	public static function legacy_profile( $overrides = array() ) {
		$profile = array(
			'action'                            => 'savefile',
			'select_tables'                     => array(),
			'select_tables_skip_search_replace' => array(),
			'rows_per_export'                   => 1000,
		);

		return (object) array_merge( $profile, $overrides );
	}
}
