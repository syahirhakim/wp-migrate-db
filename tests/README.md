# WP Migrate DB tests

PHPUnit lives in this directory. The plugin’s shipped `vendor/` tree is loaded by the bootstrap and is never installed or updated from here.

## Vendor isolation

- Run Composer only inside `tests/`.
- `composer install` writes to `tests/vendor/` (`config.vendor-dir` is `vendor` relative to this folder).
- Do not add a `composer.json` at the plugin root. `composer install` there would replace `vendor/autoload.php`, which the plugin loads at runtime.
- Bootstrap order: plugin `vendor/autoload.php`, then `class/autoload.php`, then `tests/vendor` for PHPUnit and wp-phpunit.

## Commands

From this directory:

```
composer install
composer test
```

From the plugin root, `run-tests.ps1` runs `composer test` in `tests/`.

## WordPress test database

`phpunit.xml.dist` points `WP_PHPUNIT__TESTS_CONFIG` at `vbest-tuition/tests/wp-tests-config.php`. The bootstrap rewrites that to an absolute path before WordPress boots.

That config uses database `vbest_test` and table prefix `wptests_`. The site itself uses prefix `vbst_`. Skip-search-replace tests pass table names such as `vbst_options` as migration input; they do not assume the PHPUnit database prefix is `vbst_`.
