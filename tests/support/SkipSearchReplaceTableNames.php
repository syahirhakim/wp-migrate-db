<?php

namespace DeliciousBrains\WPMDB\Tests\Support;

/**
 * Table-name normalization used by skip-search-replace tests.
 */
class SkipSearchReplaceTableNames {

	/**
	 * Strip a temporary migration prefix from a table name.
	 *
	 * @param string $table
	 * @param string $temp_prefix
	 * @return string
	 */
	public static function normalize_table_name( $table, $temp_prefix ) {
		$table       = (string) $table;
		$temp_prefix = (string) $temp_prefix;

		if ( $temp_prefix !== '' && strpos( $table, $temp_prefix ) === 0 ) {
			return substr( $table, strlen( $temp_prefix ) );
		}

		return $table;
	}
}
