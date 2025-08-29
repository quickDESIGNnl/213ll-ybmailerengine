<?php
/**
 * Module: New-Topic Mailer  –  v 1.0.3 (2025-05-14)
 * ---------------------------------------------------------------------
 * Stuurt meldingen uit wanneer er in een Thema een nieuw Onderwerp
 * wordt aangemaakt – via JetForm (front-end) én via wp-admin (back-end).
 * Het bijbehorende hoofdonderwerp wordt bepaald via de eerste
 * taxonomy-term die aan het onderwerp is gekoppeld.
 *
 * Opties
 *   gem_mailer_settings_gem_thema_onderwerp_relation     int
 *   gem_mailer_settings_gem_thema_user_relation          int | int[]
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

if ( ! function_exists( 'gem_log' ) ) {
        function gem_log( string $msg ): void {
                error_log( 'GEM-MAIL new-topic: ' . $msg );
        }
}

if ( ! function_exists( 'gem_try_new_topic_mail' ) ) :   /* guard */

/* ───────────── helpers ───────────── */

function gem_get_option_int( string $key ): int {
	$raw = get_option( $key, 0 );
	if ( is_array( $raw ) ) { $raw = reset( $raw ); }
	return (int) $raw;
}

function gem_topic_to_themas( int $topic_id, int $rel_to ): array {
        global $wpdb;
        if ( ! $rel_to ) { return []; }

        $parent = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT parent_object_id FROM {$wpdb->prefix}jet_rel_{$rel_to} WHERE child_object_id = %d",
                $topic_id
        ) );
        if ( ! $parent ) { return []; }

        $taxes = get_post_taxonomies( $parent );
        if ( ! $taxes ) { return []; }

        $terms = wp_get_object_terms( $parent, $taxes, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $terms ) || ! $terms ) { return []; }

        return array_map( 'intval', $terms );
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

/**
 * Send a templated email to all users connected to a Thema.
 *
 * @param array $uids     User IDs.
 * @param int   $thema_id The theme term ID.
 * @param int   $topic_id The topic post ID.
 * @param string $tpl     HTML mail template.
 */
function gem_send_new_topic_bulk( array $uids, int $thema_id, int $topic_id, string $tpl ): void {
        $thema_name      = get_term( $thema_id )->name;
        $topic_title     = get_the_title( $topic_id );
        $topic_permalink = get_permalink( $topic_id );
        $topic_excerpt   = wp_trim_words(
                wp_strip_all_tags( get_post_field( 'post_content', $topic_id ) ),
                20,
                '…'
        );
        $topic_author    = get_the_author_meta( 'display_name', get_post_field( 'post_author', $topic_id ) );

        foreach ( $uids as $uid ) {
                $user = get_userdata( $uid );
                if ( ! $user || ! is_email( $user->user_email ) ) { continue; }

                $message = str_replace(
                        [
                                '{{recipient_name}}',
                                '{{thema_title}}',
                                '{{topic_title}}',
                                '{{topic_permalink}}',
                                '{{topic_excerpt}}',
                                '{{topic_author}}',
                                '{{site_name}}',
                                '{{site_url}}',
                        ],
                        [
                                $user->display_name,
                                $thema_name,
                                $topic_title,
                                $topic_permalink,
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

        gem_log( "gem_try_new_topic_mail({$topic_id})" );

        if ( ! $topic_id ) {
                gem_log( 'aborted – topic_id is 0.' );
                return;
        }

	/* ─ throttle ─ */
	$last = (int) get_post_meta( $topic_id, '_gem_new_topic_mail_sent', true );
        if ( $last && ( time() - $last ) < 60 ) {
                gem_log( sprintf(
                        'throttle – topic %d already mailed %d s ago.',
                        $topic_id, time() - $last
                ) );
                return;
        }

        /* ─ opties ─ */
        $rel_to   = gem_get_option_int( 'gem_mailer_settings_gem_thema_onderwerp_relation' );
        $rel_tu   = gem_get_option_int( 'gem_mailer_settings_gem_thema_user_relation' );
       $template = get_option( 'gem_mailer_settings_gem_nieuwe_onderwerp_in_thema_email', '' )
                ?: '<p>Nieuw onderwerp: {{topic_title}}</p>';

        if ( ! $rel_to ) {
                gem_log( 'missing rel_to option.' );
                return;
        }

        if ( ! $rel_tu ) {
                gem_log( 'missing rel_tu option.' );
                return;
        }

        /* ─ thema & ontvangers ─ */
        $thema_ids = gem_topic_to_themas( $topic_id, $rel_to );
        if ( ! $thema_ids ) {
                gem_log( "no parent Thema found for topic {$topic_id} via rel_to {$rel_to}." );
                return;
        }

       gem_log( "Themas " . implode( ',', $thema_ids ) . " via rel_to {$rel_to}." );

       $sent = [];
       foreach ( $thema_ids as $thema_id ) {
               $uids = gem_users_from_thema( $thema_id, $rel_tu );
               $uids = array_diff(
                       array_unique( $uids ),
                       $sent,
                       array_filter([
                               (int) get_post_field( 'post_author', $topic_id ),
                               (int) get_term_meta( $thema_id, 'author', true ),
                       ])
               );

               if ( ! $uids ) { continue; }

               gem_send_new_topic_bulk( $uids, $thema_id, $topic_id, $template );
               $sent = array_merge( $sent, $uids );
       }

        if ( ! $sent ) {
                gem_log( "no recipients for topic {$topic_id}." );
                return;
        }

        update_post_meta( $topic_id, '_gem_new_topic_mail_sent', time() );

        gem_log( "mail sent for topic {$topic_id} to " . implode( ',', $sent ) );
}

endif;  /* end guard */


/* ───────────── LISTENERS ───────────── */

/* 1️⃣  FRONT-END – JetForm “Call a Hook” */
add_action( 'gem_new_topic_mail', function ( ...$args ) {

        gem_log( 'gem_new_topic_mail hook fired. Args: ' . print_r( $args, true ) );

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
                gem_log( 'abort – no topic_id extracted.' );
                return;
        }

	gem_try_new_topic_mail( $topic_id );

	if ( ! wp_next_scheduled( 'gem_new_topic_retry', [ $topic_id ] ) ) {
		wp_schedule_single_event( time() + 10, 'gem_new_topic_retry', [ $topic_id ] );
	}
}, 10, 20 );   // accepteert tot 20 args


/* 2️⃣  BACK-END – publish/update (front-end nu óók toegestaan) */
add_action( 'save_post_forums', function ( $pid, $post ) {

        gem_log( "save_post_forums for post {$pid}" );

	if ( wp_is_post_autosave( $pid ) || wp_is_post_revision( $pid ) ) { return; }
	if ( 'publish' !== $post->post_status ) { return; }

	gem_try_new_topic_mail( $pid );

	if ( ! wp_next_scheduled( 'gem_new_topic_retry', [ $pid ] ) ) {
		wp_schedule_single_event( time() + 10, 'gem_new_topic_retry', [ $pid ] );
	}
}, 20, 2 );


/* 3️⃣  Retry-event */
add_action( 'gem_new_topic_retry', 'gem_try_new_topic_mail' );
