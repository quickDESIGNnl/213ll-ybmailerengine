<?php
/**
 * Plugin Name: GEM Mailer
 * Description : Verzamel-plugin die losse mail-modules laadt (Reacties enz.).
 * Version     : 1.2
 * Author      : QuickDesign
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basis-constanten
 */
define( 'GEM_MAILER_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEM_MAILER_VER', '1.2' );

/**
 * Modules laden
 *
 * Zet elke nieuwe module in /modules en require_once hier.
 */
// Modules laden --------------------------------------------------------
require_once GEM_MAILER_DIR . 'modules/reactions-mail.php';   // Reactie-notificaties
require_once GEM_MAILER_DIR . 'modules/jfb-hook.php';         // JetForm extra hook
require_once GEM_MAILER_DIR . 'modules/replies-mail.php';     // Reactie-op-reactie
require_once GEM_MAILER_DIR . 'modules/new-topic-mail.php';   // Nieuw onderwerp in thema   ← nieuw



// ↓ toekomstige modules   
// require_once GEM_MAILER_DIR . 'modules/thema-mail.php';
// require_once GEM_MAILER_DIR . 'modules/anders-mail.php';
