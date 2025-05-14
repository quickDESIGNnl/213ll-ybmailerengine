<?php
/**
 * Replies-mail module
 * ─ Mailt alle auteurs + gekoppelde users in de volledige keten
 *   wanneer er op een reactie (CPT gem-reacties) wordt gereageerd.
 *
 * Opties
 *   gem_mailer_settings_gem_onderwerp_reactie_relation   = ID relation Onderwerp ↔ Reacties  (bv. 9)
 *   gem_reactie-reactie_relation                         = ID relation Reactie ↔ Reactie    (bv. 15)
 *   gem_mailer_settings_gem_reactie_relation             = ID relation Post   ↔ Users       (bv. 14)
 *   gem_mailer_settings_reacties-reacties_email          = HTML-template
 *
 * Tags in template
 *   {{recipient_name}}   – naam ontvanger
 *   {{post_title}}       – titel onderwerp (top-parent)
 *   {{post_permalink}}   – link naar onderwerp
 *   {{site_name}}        – sitenaam
 *   {{site_url}}         – hoofddomein
 *   {{reply_author}}     – naam schrijver nieuwe reactie
 *   {{reply_excerpt}}    – eerste 20 woorden van reactie
 *   {{reply_permalink}}  – directe link naar nieuwe reactie
 *
 * Anti-dubbel  : _gem_reply_mail_sent   (1 mail per reactie per 60 s)
 * Triggers     : save_post  (gem-reacties) + relation/after-add-child
 * Retry        : één cron-poging 10 s later
 * Versie       : 1.1
 */

if ( ! function_exists( 'gem_send_reply_mail' ) ) :

	/* ------------------------------------------------ helpers ---------- */

	function gem_chain_ids( int $start_id, int $rel_rr, int $rel_rt ): array {
		global $wpdb;
		$ids    = [ $start_id ];
		$tableR = $wpdb->prefix . 'jet_rel_' . $rel_rr;
		$tableT = $wpdb->prefix . 'jet_rel_' . $rel_rt;

		while ( true ) {                                  // reactie ↗ reactie ↗ …
			$p = $wpdb->get_var( $wpdb->prepare(
				"SELECT parent_object_id FROM {$tableR} WHERE child_object_id=%d LIMIT 1",
				end( $ids )
			) );
			if ( ! $p ) { break; }
			$ids[] = (int) $p;
		}
		/* onderwerp (top parent) */
		$topic = $wpdb->get_var( $wpdb->prepare(
			"SELECT parent_object_id FROM {$tableT} WHERE child_object_id=%d LIMIT 1",
			end( $ids )
		) );
		if ( $topic ) { $ids[] = (int) $topic; }
		return $ids;
	}

	function gem_users_from_rel14( int $post_id, int $rel_ru ): array {
		global $wpdb;
		if ( ! $rel_ru ) { return []; }
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT child_object_id FROM {$wpdb->prefix}jet_rel_{$rel_ru} WHERE parent_object_id=%d",
			$post_id
		) ) );
	}

	function gem_send_reply_bulk( array $uids, int $topic_id, int $reply_id, string $tpl ) {
		foreach ( $uids as $uid ) {
			$user = get_userdata( $uid );
			if ( ! $user || ! is_email( $user->user_email ) ) { continue; }

			$msg = str_replace(
				[ '{{recipient_name}}', '{{post_title}}', '{{post_permalink}}',
				  '{{site_name}}', '{{site_url}}',
				  '{{reply_author}}', '{{reply_excerpt}}', '{{reply_permalink}}' ],
				[
					$user->display_name,
					get_the_title( $topic_id ),
					get_permalink( $topic_id ),
					get_bloginfo( 'name' ),
					home_url(),
					get_the_author_meta( 'display_name', get_post_field( 'post_author', $reply_id ) ),
					wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $reply_id ) ), 20, '…' ),
					get_permalink( $reply_id ),
				],
				$tpl
			);

			wp_mail(
				$user->user_email,
				sprintf( 'Nieuwe reactie in “%s”', get_the_title( $topic_id ) ),
				$msg,
				[ 'Content-Type: text/html; charset=UTF-8' ]
			);
		}
	}

	function gem_try_reply_mail( int $reply_id ) {

		/* Dubbel-filter */
		$last = (int) get_post_meta( $reply_id, '_gem_reply_mail_sent', true );
		if ( $last && ( time() - $last ) < 60 ) { return; }

		$rel_rt = (int) get_option( 'gem_mailer_settings_gem_onderwerp_reactie_relation', 0 );
		$rel_rr = (int) get_option( 'gem_reactie-reactie_relation', 0 );
		$rel_ru = (int) get_option( 'gem_mailer_settings_gem_reactie_relation', 0 );
		$template = get_option( 'gem_mailer_settings_reacties-reacties_email', '' )
			?: '<p>Er is een nieuwe reactie in “{{post_title}}”.</p>';

		if ( ! $rel_rr || ! $rel_rt ) { return; }

		$chain = gem_chain_ids( $reply_id, $rel_rr, $rel_rt );
		$topic = end( $chain );
		$uids  = [];

		foreach ( $chain as $pid ) {
			$uids[] = (int) get_post_field( 'post_author', $pid );
			$uids   = array_merge( $uids, gem_users_from_rel14( $pid, $rel_ru ) );
		}

		/* geen mail aan inzender zelf */
		$uids = array_diff( array_unique( $uids ), [ (int) get_post_field( 'post_author', $reply_id ) ] );
		if ( ! $uids ) { return; }

		gem_send_reply_bulk( $uids, $topic, $reply_id, $template );
		update_post_meta( $reply_id, '_gem_reply_mail_sent', time() );
	}

endif;


/* --------- save_post + cron-retry ---------- */
add_action( 'save_post_gem-reacties', function ( $pid, $post ) {
	if ( wp_is_post_autosave( $pid ) || wp_is_post_revision( $pid ) ) { return; }
	if ( 'publish' !== $post->post_status ) { return; }

	gem_try_reply_mail( $pid );
	if ( ! wp_next_scheduled( 'gem_reply_retry', [ $pid ] ) ) {
		wp_schedule_single_event( time() + 10, 'gem_reply_retry', [ $pid ] );
	}
}, 20, 2 );

add_action( 'gem_reply_retry', 'gem_try_reply_mail' );


/* --------- relation/after-add-child ---------- */
add_action( 'jet-engine/relation/after-add-child', function ( $rel, $parent_id, $child_id ) {
	if ( intval( $rel->id ) === (int) get_option( 'gem_reactie-reactie_relation', 0 ) ) {
		gem_try_reply_mail( $child_id );                 // child = nieuwe reply
	}
}, 10, 3 );
