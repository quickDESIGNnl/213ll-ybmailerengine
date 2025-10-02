<?php
/**
 * Plugin Name: GEM Mailer
 * Description: Mailer-engine voor JetEngine forums; verstuurt notificaties bij nieuwe onderwerpen, reacties en replies.
 * Version: 3.0.0
 * Author: QuickDesign
 * Text Domain: gem-mailer
 * Domain Path: /languages
 */

define( 'GEM_MAILER_PATH', plugin_dir_path( __FILE__ ) );
define( 'GEM_MAILER_URL', plugin_dir_url( __FILE__ ) );

gem_mailer_bootstrap();

/**
 * Register the plugin autoloader and boot the plugin container.
 */
function gem_mailer_bootstrap(): void {
    if ( ! defined( 'ABSPATH' ) ) {
        return;
    }

    spl_autoload_register( static function ( string $class ): void {
        if ( 0 !== strpos( $class, 'GemMailer\\' ) ) {
            return;
        }

        $relative = substr( $class, strlen( 'GemMailer\\' ) );
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
        $file     = GEM_MAILER_PATH . 'includes/' . $relative . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    } );

    GemMailer\Plugin::instance()->boot();
}
