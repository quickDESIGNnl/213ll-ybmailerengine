<?php
/**
 * JetFormBuilder Call-Hooks
 *
 * 1) gem_jfb_notify_parent_author  – nieuwe reactie op een onderwerp
 * 2) gem_jfb_notify_reply_chain    – reactie op een reactie
 *
 * Beide hooks worden in JetFormBuilder ingesteld via
 * Post Submit Actions → “Call Hook”.
 */

if ( ! function_exists( 'gem_mailer_get_option_int' ) ) {
        require_once __DIR__ . '/../includes/options.php';
}

/* ============================================================
 * 1 ▸ Reactie op een onderwerp
 * ========================================================== */
if ( ! function_exists( 'gem_jfb_notify_parent_author' ) ) :

	function gem_jfb_notify_parent_author( $handler, $record ) {

		$reactie_id = $handler->get_inserted_post_id();
		if ( ! $reactie_id ) {
			error_log( 'GEM-MAIL JFB: parent → geen post_id.' );
			return;
		}

                $rel_id = gem_mailer_get_option_int( GEM_MAILER_OPT_REL_TOPIC_REACTIE );
		if ( ! $rel_id ) { return; }

		global $wpdb;
		$parent = $wpdb->get_var( $wpdb->prepare(
			"SELECT parent_object_id FROM {$wpdb->prefix}jet_rel_{$rel_id}
			 WHERE child_object_id=%d LIMIT 1",
			$reactie_id
		) );
		if ( ! $parent ) { return; }

		/* gebruik bestaande helper uit reactions-module */
		if ( function_exists( 'gem_try_mail_once' ) ) {
			gem_try_mail_once( $reactie_id );   // verstuurt & zet _gem_mail_sent
		}
	}

	add_action(
		'jet-form-builder/call-hook/gem_jfb_notify_parent_author',
		'gem_jfb_notify_parent_author',
		10,
		2
	);

endif;


/* ============================================================
 * 2 ▸ Reactie op een reactie
 * ========================================================== */
if ( ! function_exists( 'gem_jfb_notify_reply_chain' ) ) :

	function gem_jfb_notify_reply_chain( $handler, $record ) {

		$reply_id = $handler->get_inserted_post_id();
		if ( ! $reply_id ) {
			error_log( 'GEM-MAIL JFB: reply → geen post_id.' );
			return;
		}

		/* Laat replies-module het werk doen */
		if ( function_exists( 'gem_try_reply_mail' ) ) {
			gem_try_reply_mail( $reply_id );    // verstuurt & zet _gem_reply_mail_sent
		}
	}

	add_action(
		'jet-form-builder/call-hook/gem_jfb_notify_reply_chain',
		'gem_jfb_notify_reply_chain',
		10,
		2
	);

endif;
