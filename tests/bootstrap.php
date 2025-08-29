<?php
/**
 * PHPUnit bootstrap file for GEM Mailer.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = sys_get_temp_dir() . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find {$_tests_dir}/includes/functions.php\n";
    exit( 1 );
}

require $_tests_dir . '/includes/functions.php';

if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
    require dirname( __DIR__ ) . '/vendor/autoload.php';
}

function _manually_load_plugin() {
    require dirname( __DIR__ ) . '/modules/new-topic-mail.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
