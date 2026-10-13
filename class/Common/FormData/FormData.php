<?php

namespace DeliciousBrains\WPMDB\Common\FormData;

use DeliciousBrains\WPMDB\Common\MigrationPersistence\Persistence;
use DeliciousBrains\WPMDB\Common\MigrationState\MigrationStateManager;
use DeliciousBrains\WPMDB\Common\Util\Util;
use DeliciousBrains\WPMDB\Common\Sql\Table;
use DeliciousBrains\WPMDB\WPMDBDI;

class FormData
{

    /**
     * @var Util
     */
    private $util;
    /**
     * @var
     */
    public $form_data;
    /**
     * @var array
     */
    public $accepted_fields;
    /**
     * @var MigrationStateManager
     */
    public $migration_state_manager;

    public $compat_fields = [
        'action',
        'connection_info',
        'select_tables',
        'table_migrate_option',
        'create_backup',
        'backup_option',
        'select_backup',
        'select_post_types',
        'replace_guids',
        'compatibility_older_mysql',
        'exclude_transients',
        'exclude_spam',
        'keep_active_plugins',
        'gzip_file',
        'select_tables_skip_search_replace',
        'rows_per_export',
    ];

    public function __construct(
        Util $util,
        MigrationStateManager $migration_state_manager
    ) {
        $this->util                    = $util;
        $this->migration_state_manager = $migration_state_manager;

        $this->accepted_fields = array(
            'action',
            'save_computer',
            'gzip_file',
            'connection_info',
            'replace_old',
            'replace_new',
            'table_migrate_option',
            'select_tables',
            'replace_guids',
            'exclude_spam',
            'save_migration_profile',
            'save_migration_profile_option',
            'create_new_profile',
            'create_backup',
            'remove_backup',
            'keep_active_plugins',
            'select_post_types',
            'backup_option',
            'select_backup',
            'exclude_transients',
            'exclude_post_types',
            'exclude_post_revisions',
            'compatibility_older_mysql',
            'export_dest',
            'import_find_replace',
            'current_migration',
            'search_replace',
            'regex',
            'case_sensitive',
            'select_tables_skip_search_replace',
            'rows_per_export',
        );

        add_filter('wpmdb_skip_search_replace_tables', array($this, 'merge_skip_search_replace_tables_from_form'), 10, 1);
    }

    public function get_accepted_fields()
    {
        return $this->accepted_fields;
    }

    public function set_accepted_fields()
    {
    }

    public function form_data_compat($data)
    {
        $current_migration = $data['current_migration'];
        $advanced_options  = $current_migration['advanced_options_selected'];

        $return = [
            'action'                    => $current_migration['intent'],
            'select_tables'             => isset($current_migration['tables_selected']) ? $current_migration['tables_selected'] : [],
            'table_migrate_option'      => isset($current_migration['tables_option']) ? $current_migration['tables_option'] : '',
            'create_backup'             => isset($current_migration['backup_option']) && $current_migration['backup_option'] !== 'none' ? 1 : 0,
            'backup_option'             => isset($current_migration['backup_option']) ? $current_migration['backup_option'] : '',
            'select_backup'             => isset($current_migration['backup_tables_selected']) ? $current_migration['backup_tables_selected'] : [],
            'select_post_types'         => isset($current_migration['post_types_selected']) ? $current_migration['post_types_selected'] : [],
            'exclude_post_revisions'    => in_array('exclude_post_revisions', $advanced_options) ? '1' : '0',
            'replace_guids'             => in_array('replace_guids', $advanced_options) ? '1' : '0',
            'compatibility_older_mysql' => in_array('compatibility_older_mysql', $advanced_options) ? '1' : '0',
            'exclude_transients'        => in_array('exclude_transients', $advanced_options) ? '1' : '0',
            'exclude_spam'              => in_array('exclude_spam', $advanced_options) ? '1' : '0',
            'keep_active_plugins'       => in_array('keep_active_plugins', $advanced_options) ? '1' : '0',
            'gzip_file'                 => in_array('gzip_file', $advanced_options) ? '1' : '0',
            'exclude_post_types'       => '0',
            'select_tables_skip_search_replace' => isset($current_migration['search_replace_skip_option']) && $current_migration['search_replace_skip_option'] === 'manual_select' && isset($current_migration['search_replace_skip_tables_selected']) && is_array($current_migration['search_replace_skip_tables_selected']) ? $current_migration['search_replace_skip_tables_selected'] : [],
        ];

        if (isset($current_migration['rows_per_export']) && $current_migration['rows_per_export'] !== '') {
            $return['rows_per_export'] = $this->sanitize_rows_per_export($current_migration['rows_per_export']);
        } elseif (isset($data['rows_per_export']) && $data['rows_per_export'] !== '') {
            $return['rows_per_export'] = $this->sanitize_rows_per_export($data['rows_per_export']);
        }

        if (in_array($current_migration['intent'], array('push', 'pull'))) {
            $return['connection_info'] = isset($data['connection_info'], $data['connection_info']['connection_state']) ? $data['connection_info']['connection_state']['value'] : '';
        }

        if ($return['table_migrate_option'] === 'selected') {
            $return['table_migrate_option'] = 'migrate_select';
        }

        if ($current_migration['post_types_option'] !== 'all') {
            $return['exclude_post_types'] = 1;
        }

        if ($return['exclude_post_revisions'] === '1' && $current_migration['post_types_option'] === 'all' ) {
            $table                        = WPMDBDI::getInstance()->get(Table::class);
            $return['select_post_types']  = array_diff($table->get_post_types(), ['revision']);
            $return['exclude_post_types'] = 1;
        }

        //make sure revisions are included when user has selected post types to migrate but did not exclude revisions
        if ($return['exclude_post_revisions'] === '0' && $return['exclude_post_types'] === 1 && ! in_array('revision',
                $return['select_post_types'], true)) {
            $return['select_post_types'][] = 'revision';
        }
        return $return;
    }

    /**
     * Sets up the form data for the migration.
     */
    public function setup_form_data()
    {
        $this->util->set_time_limit();
        $state_data = $this->migration_state_manager->set_post_data();

        if (empty($this->form_data)) {
            // ***+=== @TODO - revisit usage of parse_migration_form_data
            $this->parse_and_save_migration_form_data($state_data['form_data']);
        }
    }

    /**
     * Returns validated and sanitized form data.
     *
     * @param array|string $data
     *
     * @return array|string
     */

    // @TODO - refactor usage
    public function parse_and_save_migration_form_data($data)
    {
        $form_data = json_decode($data, true);

        $this->accepted_fields = apply_filters('wpmdb_accepted_profile_fields', $this->accepted_fields);

        $form_data             = array_intersect_key($form_data, array_flip($this->accepted_fields));

        $compat_form_data = $this->form_data_compat($form_data);

        if (!empty($compat_form_data) && is_array($compat_form_data)) {
            $form_data = array_merge($form_data, $compat_form_data);
        }

        $existing_form_data = Persistence::getMigrationOptions();

        if (!empty($existing_form_data)) {
            $form_data = array_merge($existing_form_data, $form_data);
        }

        // @TODO maybe sanitize JSON?
        Persistence::saveMigrationOptions($form_data);

        $this->form_data = $form_data;

        return $form_data;
    }

    /**
     * @return mixed
     */
    public function getFormData()
    {
        if (!empty($this->form_data)) {
            return $this->form_data;
        }

        $saved_form_data = Persistence::getMigrationOptions();

        if (empty($saved_form_data)) {
            return null;
        }

        return $saved_form_data;
    }

    public function getAdvancedOptions()
    {
        $form_data = $this->getFormData();

        if (isset($form_data['current_migration']['advanced_options_selected'])) {
            return $form_data['current_migration']['advanced_options_selected'];
        }

        return false;
    }

    public function getAdvancedOption($key)
    {
        $form_data = $this->getFormData();

        if (!isset($form_data['current_migration']['advanced_options_selected'])) {
            return false;
        }

        $advanced_options = $form_data['current_migration']['advanced_options_selected'];

        if (isset($advanced_options[$key])) {
            return $advanced_options[$key];
        }

        return false;
    }

    public function getCurrentMigrationData()
    {
        $form_data = $this->getFormData();

        if (isset($form_data['current_migration'])) {
            return $form_data['current_migration'];
        }

        return false;
    }

    public function getMigrationStages()
    {
        $current_migration = $this->getCurrentMigrationData();

        if (!$current_migration) {
            return;
        }

        if (isset($current_migration['stages'])) {
            return $current_migration['stages'];
        }

        return false;
    }


    /**
     * @param mixed $form_data
     */
    public function setFormData($form_data)
    {
        $this->form_data = $form_data;
    }

    /**
     * Merge the saved per-table skip list with the default skipped tables.
     *
     * @param array $tables
     * @return array
     */
    public function merge_skip_search_replace_tables_from_form($tables)
    {
        if (!is_array($tables)) {
            $tables = [];
        }

        $selected  = [];
        $form_data = $this->getFormData();

        if (is_array($form_data) && isset($form_data['select_tables_skip_search_replace']) && is_array($form_data['select_tables_skip_search_replace'])) {
            $selected = $form_data['select_tables_skip_search_replace'];
        }

        return array_values(array_unique(array_merge($tables, $selected)));
    }

    /**
     * @param mixed $value
     * @return int
     */
    public function sanitize_rows_per_export($value)
    {
        $rows = (int) $value;

        if ($rows < 1) {
            return 1;
        }

        if ($rows > 10000) {
            return 10000;
        }

        return $rows;
    }

    /**
     * @return int|null
     */
    public function get_rows_per_export_from_form()
    {
        $form_data = $this->getFormData();

        if (!is_array($form_data)) {
            return null;
        }

        if (isset($form_data['rows_per_export']) && $form_data['rows_per_export'] !== '') {
            return $this->sanitize_rows_per_export($form_data['rows_per_export']);
        }

        if (isset($form_data['current_migration']['rows_per_export']) && $form_data['current_migration']['rows_per_export'] !== '') {
            return $this->sanitize_rows_per_export($form_data['current_migration']['rows_per_export']);
        }

        return null;
    }

    /**
     * @param string $intent
     * @return int
     */
    public function resolve_rows_per_segment_for_intent($intent)
    {
        $export_intents = array('savefile', 'backup_local');

        if (in_array($intent, $export_intents, true)) {
            $from_form = $this->get_rows_per_export_from_form();

            if (null !== $from_form) {
                return $from_form;
            }

            return 1000;
        }

        return 100;
    }

    /**
     * @param Table $table
     * @param string $intent
     */
    public function apply_rows_per_segment_for_intent(Table $table, $intent)
    {
        $rows                    = $this->resolve_rows_per_segment_for_intent($intent);
        $table->rows_per_segment = $rows;

        add_filter(
            'wpmdb_rows_per_segment',
            static function () use ($rows) {
                return $rows;
            },
            100
        );
    }
}
