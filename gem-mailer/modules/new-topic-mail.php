<?php
/**
 * Module: New-Topic Mailer  –  v 1.0.3 (2025-05-14)
 * ---------------------------------------------------------------------
 * Stuurt meldingen uit wanneer er in een Thema een nieuw Onderwerp
 * wordt aangemaakt – via JetForm (front-end) én via wp-admin (back-end).
 *
 * Opties
 *   gem_mailer_settings_gem_thema_onderwerp_relation      int | int[]
 *   gem_mailer_settings_gem_thema_user_relation           int | int[]
 *   gem_mailer_settings_gem_nieuwe_onderwerp_in_thema_email   string
 *
 * Placeholders
 *   {{recipient_name}}, {{thema_title}}, {{topic_title}},
 *   {{topic_permalink}}, {{topic_excerpt}}, {{topic_author}},
 *   {{site_name}}, {{site_url}}
 *
 * Meta-key : _gem_new_topic_mail_sent   – throttle 60 s
 * Cron     : gem_new_topic_retry        – één her-poging 10 s later
 */

if ( ! function_exists( 'gem_try_new_topic_mail' ) ) :   /* guard */

/* ───────────── helpers ───────────── */

function gem_get_option_int( string $key ): int {
	$raw = get_option( $key, 0 );
	if ( is_array( $raw ) ) { $raw = reset( $raw ); }
	return (int) $raw;
}

function gem_topic_to_thema( int $topic_id, int $rel_to ): ?int {
	global $wpdb;
	if ( ! $rel_to ) { return null; }
	$table = $wpdb->prefix . "jet_rel_{$rel_to}";
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT parent_object_id FROM {$table}
		 WHERE child_object_id = %d LIMIT 1",
		$topic_id
	) );
}

function gem_users_from_thema( int $thema_id, int $rel_tu ): array {
	global $wpdb;
	if ( ! $rel_tu ) { return []; }
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT child_object_id FROM {$wpdb->prefix}jet_rel_{$rel_tu}
		 WHERE parent_object_id = %d",
		$thema_id
	) ) );
}

function gem_send_new_topic_bulk( array $uids, int $thema_id, int $topic_id, string $tpl ): void {

	$thema      = get_term( $thema_id );
	$thema_name = $thema ? $thema->name : '';

	$topic_author  = get_the_author_meta( 'display_name', get_post_field( 'post_author', $topic_id ) );
	$topic_excerpt = wp_trim_words(
		wp_strip_all_tags( get_post_field( 'post_content', $topic_id ) ),
		30, '…'
	);

	foreach ( $uids as $uid ) {
		$user = get_userdata( $uid );
		if ( ! $user || ! is_email( $user->user_email ) ) { continue; }

		$message = str_replace(
			[
				'{{recipient_name}}', '{{thema_title}}', '{{topic_title}}',
				'{{topic_permalink}}', '{{topic_excerpt}}', '{{topic_author}}',
				'{{site_name}}', '{{site_url}}',
			],
			[
				$user->display_name,
				$thema_name,
				get_the_title( $topic_id ),
				get_permalink( $topic_id ),
				$topic_excerpt,
				$topic_author,
				get_bloginfo( 'name' ),
				home_url(),
			],
			$tpl
		);

                wp_mail(
                        $user->user_email,
                        sprintf( 'Nieuw onderwerp binnen hoofdonderwerp “%s”', $thema_name ),
                        $message,
                        [ 'Content-Type: text/html; charset=UTF-8' ]
                );
        }
}

function gem_try_new_topic_mail( int $topic_id ): void {

	error_log( "GEM-MAIL new-topic: gem_try_new_topic_mail({$topic_id})" );

	if ( ! $topic_id ) {
		error_log( 'GEM-MAIL new-topic: aborted – topic_id is 0.' );
		return;
	}

	/* ─ throttle ─ */
	$last = (int) get_post_meta( $topic_id, '_gem_new_topic_mail_sent', true );
	if ( $last && ( time() - $last ) < 60 ) {
		error_log( sprintf(
			'GEM-MAIL new-topic: throttle – topic %d already mailed %d s ago.',
			$topic_id, time() - $last
		) );
		return;
	}

	/* ─ opties ─ */
	$rel_to   = gem_get_option_int( 'gem_mailer_settings_gem_thema_onderwerp_relation' );
	$rel_tu   = gem_get_option_int( 'gem_mailer_settings_gem_thema_user_relation' );
       $template = get_option( 'gem_mailer_settings_gem_nieuwe_onderwerp_in_thema_email', '' )
		?: '<p>Nieuw onderwerp: {{topic_title}}</p>';

	if ( ! $rel_to || ! $rel_tu ) {
		error_log( 'GEM-MAIL new-topic: missing rel_to or rel_tu option.' );
		return;
	}

	/* ─ thema & ontvangers ─ */
	$thema_id = gem_topic_to_thema( $topic_id, $rel_to );
	if ( ! $thema_id ) {
		error_log( "GEM-MAIL new-topic: no parent Thema found for topic {$topic_id}." );
		return;
	}

	$uids = gem_users_from_thema( $thema_id, $rel_tu );
	$uids[] = (int) get_post_field( 'post_author', $thema_id );   // auteur Thema
	$uids[] = (int) get_post_field( 'post_author', $topic_id );   // auteur Topic
	$uids   = array_diff( array_unique( $uids ), [ (int) get_post_field( 'post_author', $topic_id ) ] );

	if ( ! $uids ) {
		error_log( "GEM-MAIL new-topic: no recipients for topic {$topic_id}." );
		return;
	}

	/* ─ verstuur ─ */
	gem_send_new_topic_bulk( $uids, $thema_id, $topic_id, $template );
	update_post_meta( $topic_id, '_gem_new_topic_mail_sent', time() );

	error_log( "GEM-MAIL new-topic: mail sent for topic {$topic_id} to " . implode( ',', $uids ) );
}

endif;  /* end guard */


/* ───────────── LISTENERS ───────────── */

/* 1️⃣  FRONT-END – JetForm “Call a Hook” */
add_action( 'gem_new_topic_mail', function ( ...$args ) {

	error_log( 'GEM-MAIL new-topic: gem_new_topic_mail hook fired. Args: ' . print_r( $args, true ) );

	$topic_id = 0;

	foreach ( $args as $arg ) {
		if ( is_numeric( $arg ) && $arg > 0 ) { $topic_id = (int) $arg; break; }
		if ( is_array( $arg ) && isset( $arg['inserted_post_id'] ) ) {
			$topic_id = (int) $arg['inserted_post_id']; break;
		}
		if ( is_object( $arg ) && property_exists( $arg, 'inserted_post_id' ) ) {
			$topic_id = (int) $arg->inserted_post_id; break;
		}
	}

	if ( ! $topic_id ) {
		error_log( 'GEM-MAIL new-topic: abort – no topic_id extracted.' );
		return;
	}

	gem_try_new_topic_mail( $topic_id );

	if ( ! wp_next_scheduled( 'gem_new_topic_retry', [ $topic_id ] ) ) {
		wp_schedule_single_event( time() + 10, 'gem_new_topic_retry', [ $topic_id ] );
	}
}, 10, 20 );   // accepteert tot 20 args


/* 2️⃣  BACK-END – publish/update (front-end nu óók toegestaan) */
add_action( 'save_post_gem-onderwerpen', function ( $pid, $post ) {

	error_log( "GEM-MAIL new-topic: save_post_gem-onderwerpen for post {$pid}" );

	if ( wp_is_post_autosave( $pid ) || wp_is_post_revision( $pid ) ) { return; }
	if ( 'publish' !== $post->post_status ) { return; }

	gem_try_new_topic_mail( $pid );

	if ( ! wp_next_scheduled( 'gem_new_topic_retry', [ $pid ] ) ) {
		wp_schedule_single_event( time() + 10, 'gem_new_topic_retry', [ $pid ] );
	}
}, 20, 2 );


/* 3️⃣  Retry-event */
add_action( 'gem_new_topic_retry', 'gem_try_new_topic_mail' );
