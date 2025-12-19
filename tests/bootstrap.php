<?php

/**
 * Tests: References
 *
 * - [PHPUnit](https://github.com/sebastianbergmann/phpunit)
 * - [PHPUnit Polyfills](https://github.com/Yoast/PHPUnit-Polyfills)
 * - [WP_UnitTestCase](https://github.com/WordPress/wordpress-develop/blob/trunk/tests/phpunit/includes/abstract-testcase.php)
 * - [Assertions](https://docs.phpunit.de/en/10.2/assertions.html)
 */

if ( ! $_WORDPRESS_DEVELOP_DIR = getenv( 'WORDPRESS_DEVELOP_DIR' ) ) {
  $_WORDPRESS_DEVELOP_DIR = __DIR__ . '/../wordpress-develop';
}

/**
 * Directory of PHPUnit test files
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#using-included-wordpress-phpunit-test-files
 */
if ( ! $_WORDPRESS_TESTS_DIR = getenv( 'WP_TESTS_DIR' ) ) {
  $_WORDPRESS_TESTS_DIR = $_WORDPRESS_DEVELOP_DIR . '/tests/phpunit';
}

/**
 * Optional: Load the database-module library for DatabaseModuleStorage tests.
 * Set DATABASE_MODULE_DIR environment variable to override the default path.
 */
if ( ! $_DATABASE_MODULE_DIR = getenv( 'DATABASE_MODULE_DIR' ) ) {
  $_DATABASE_MODULE_DIR = __DIR__ . '/../../database-module';
}

/**
 * Optional: Load the Tangible Fields library for TangibleFieldsRenderer tests.
 * Set TANGIBLE_FIELDS_DIR environment variable to override the default path.
 */
if ( ! $_TANGIBLE_FIELDS_DIR = getenv( 'TANGIBLE_FIELDS_DIR' ) ) {
  $_TANGIBLE_FIELDS_DIR = __DIR__ . '/../../fields';
}

// Load WP test functions first (provides tests_add_filter)
require_once $_WORDPRESS_TESTS_DIR . '/includes/functions.php';

// Load database-module if available
if ( file_exists( $_DATABASE_MODULE_DIR . '/index.php' ) ) {
  tests_add_filter( 'muplugins_loaded', function() use ( $_DATABASE_MODULE_DIR ) {
    require_once $_DATABASE_MODULE_DIR . '/index.php';
  });
}

// Load tangible-fields if available (requires tangible-framework)
if ( file_exists( $_TANGIBLE_FIELDS_DIR . '/index.php' ) ) {
  tests_add_filter( 'muplugins_loaded', function() use ( $_TANGIBLE_FIELDS_DIR ) {
    // Load tangible-framework first (required by tangible-fields)
    $framework_path = __DIR__ . '/../vendor/tangible/framework/index.php';
    if ( file_exists( $framework_path ) ) {
      require_once $framework_path;
    }
    require_once $_TANGIBLE_FIELDS_DIR . '/index.php';
  });
}

// Now load the rest of WordPress
require $_WORDPRESS_TESTS_DIR . '/includes/bootstrap.php';
