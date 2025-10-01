<?php
/**
 * Plugin Name: GEM Mailer
 * Description: Mailer-engine voor JetEngine forums; verstuurt notificaties bij nieuwe onderwerpen, reacties en replies.
 * Version: 2.0.0
 * Author: QuickDesign
 * Text Domain: gem-mailer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GEM_MAILER_DIR', plugin_dir_path( __FILE__ ) );

gem_mailer_bootstrap();

/**
 * Bootstrap the plugin components.
 */
function gem_mailer_bootstrap(): void {
    require_once GEM_MAILER_DIR . 'includes/constants.php';
    require_once GEM_MAILER_DIR . 'includes/helpers.php';
    require_once GEM_MAILER_DIR . 'includes/admin/settings-page.php';
    require_once GEM_MAILER_DIR . 'includes/mailers/new-topic.php';
    require_once GEM_MAILER_DIR . 'includes/mailers/topic-reaction.php';
}
