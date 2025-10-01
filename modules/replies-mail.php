<?php
/**
 * Replies-mail module
 * ─ Mailt alle auteurs + gekoppelde users in de volledige keten
 *   wanneer er op een reactie (CPT gem-reacties) wordt gereageerd.
 *
 * Versie  : 1.2.0  (anchor-link fix)
 * Auteur  : GEM-Mailer
 */

if ( ! function_exists( 'gem_mailer_get_option_int' ) ) {
        require_once __DIR__ . '/../includes/options.php';
}

if ( ! function_exists( 'gem_send_reply_mail' ) ) :

	/* ------------------------------------------------ helpers ---------- */

	/**
	 * Haalt alle bovenliggende reacties + het bovenliggende onderwerp op
	 * -> array terug in volgorde [reply, parent reply, … , topic]
	 */
	function gem_chain_ids( int $start_id, int $rel_rr, int $rel_rt ): array {
		global $wpdb;

		$ids    = [ $start_id ];
		$tableR = $wpdb->prefix . 'jet_rel_' . $rel_rr;
		$tableT = $wpdb->prefix . 'jet_rel_' . $rel_rt;

		/* reactie → reactie omhoog */
		while ( true ) {
			$parent = $wpdb->get_var( $wpdb->prepare(
				"SELECT parent_object_id FROM {$tableR}
				 WHERE child_object_id=%d LIMIT 1",
				end( $ids )
			) );
			if ( ! $parent ) { break; }
			$ids[] = (int) $parent;
		}

		/* bovenste reactie → onderwerp (topic) */
		$topic = $wpdb->get_var( $wpdb->prepare(
			"SELECT parent_object_id FROM {$tableT}
			 WHERE child_object_id=%d LIMIT 1",
			end( $ids )
		) );
		if ( $topic ) { $ids[] = (int) $topic; }

		return $ids;
	}

	/**
	 * Haalt uit relation 14 (post ↔ users) alle gekoppelde user-IDs op
	 */
	function gem_users_from_rel14( int $post_id, int $rel_ru ): array {
		global $wpdb;
		if ( ! $rel_ru ) { return []; }

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT child_object_id FROM {$wpdb->prefix}jet_rel_{$rel_ru}
			 WHERE parent_object_id=%d",
			$post_id
		) ) );
	}

	/**
	 * Bouwt en verstuurt de e-mails
	 */
	function gem_send_reply_bulk( array $uids, int $topic_id, int $reply_id, string $tpl ) {
		$reply_author  = get_the_author_meta( 'display_name', get_post_field( 'post_author', $reply_id ) );
		$reply_excerpt = wp_trim_words(
			wp_strip_all_tags( get_post_field( 'post_content', $reply_id ) ),
			20,
			'…'
		);
		$reply_slug    = get_post_field( 'post_name', $reply_id );
		$reply_url     = get_permalink( $topic_id ) . '#' . $reply_slug;   // <<< anchor-link!

		foreach ( $uids as $uid ) {
			$user = get_userdata( $uid );
			if ( ! $user || ! is_email( $user->user_email ) ) { continue; }

			$message = str_replace(
				[
					'{{recipient_name}}',
					'{{post_title}}',
					'{{post_permalink}}',
					'{{site_name}}',
					'{{site_url}}',
					'{{reply_author}}',
					'{{reply_excerpt}}',
					'{{reply_permalink}}',
				],
				[
					$user->display_name,
					get_the_title( $topic_id ),
					get_permalink( $topic_id ),
					get_bloginfo( 'name' ),
					home_url(),
					$reply_author,
					$reply_excerpt,
					$reply_url,
				],
				$tpl
			);

			wp_mail(
				$user->user_email,
				sprintf( 'Nieuwe reactie in “%s”', get_the_title( $topic_id ) ),
				$message,
				[ 'Content-Type: text/html; charset=UTF-8' ]
			);
		}
	}

	/**
	 * Core-routine – roept bulk-mailer aan + throttle & logging
	 */
	function gem_try_reply_mail( int $reply_id ) {

		/* throttle (60 s) ---------------------------------------------- */
		$last = (int) get_post_meta( $reply_id, '_gem_reply_mail_sent', true );
		if ( $last && ( time() - $last ) < 60 ) {
			error_log( sprintf(
				'GEM-MAIL replies: throttle – reply %d already mailed %d s ago.',
				$reply_id,
				time() - $last
			) );
			return;
		}

		/* opties -------------------------------------------------------- */
                $rel_rt  = gem_mailer_get_option_int( GEM_MAILER_OPT_REL_TOPIC_REACTIE );
                $rel_rr  = gem_mailer_get_option_int( GEM_MAILER_OPT_REL_REPLY_REACTIE );
                $rel_ru  = gem_mailer_get_option_int( GEM_MAILER_OPT_REL_REACTIE_USER );
                $template = gem_mailer_get_option( GEM_MAILER_OPT_TEMPLATE_REPLY, '' )
                        ?: '<p>Er is een nieuwe reactie in “{{post_title}}”.</p>';

		if ( ! $rel_rr || ! $rel_rt ) {
			error_log( sprintf(
				'GEM-MAIL replies: rel_rr (%d) or rel_rt (%d) missing.',
				$rel_rr,
				$rel_rt
			) );
			return;
		}

		/* keten & ontvangers ------------------------------------------- */
		$chain = gem_chain_ids( $reply_id, $rel_rr, $rel_rt );
		$topic = end( $chain );                   // laatste ID = onderwerp
		$uids  = [];

		foreach ( $chain as $pid ) {
			$uids[] = (int) get_post_field( 'post_author', $pid );
			$uids   = array_merge( $uids, gem_users_from_rel14( $pid, $rel_ru ) );
		}

		$uids = array_diff( array_unique( $uids ), [ (int) get_post_field( 'post_author', $reply_id ) ] );

		if ( ! $uids ) {
			error_log( sprintf(
				'GEM-MAIL replies: no recipients found for reply %d.',
				$reply_id
			) );
			return;
		}

		/* versturen ----------------------------------------------------- */
		gem_send_reply_bulk( $uids, $topic, $reply_id, $template );
		update_post_meta( $reply_id, '_gem_reply_mail_sent', time() );
	}

endif;


/* --------- save_post + cron-retry ------------------------------------ */
add_action( 'save_post_gem-reacties', function ( $pid, $post ) {
	if ( wp_is_post_autosave( $pid ) || wp_is_post_revision( $pid ) ) { return; }
	if ( 'publish' !== $post->post_status ) { return; }

	gem_try_reply_mail( $pid );

	/* één retry 10 s later */
	if ( ! wp_next_scheduled( 'gem_reply_retry', [ $pid ] ) ) {
		wp_schedule_single_event( time() + 10, 'gem_reply_retry', [ $pid ] );
	}
}, 20, 2 );

add_action( 'gem_reply_retry', 'gem_try_reply_mail' );


/* --------- relation/after-add-child ---------------------------------- */
add_action( 'jet-engine/relation/after-add-child', function ( $rel, $parent_id, $child_id ) {
	if ( intval( $rel->id ) === gem_mailer_get_option_int( GEM_MAILER_OPT_REL_REPLY_REACTIE ) ) {
		gem_try_reply_mail( $child_id );     // child = nieuwe reply
	}
}, 10, 3 );
