<?php
/**
 * Reactie-notificatie module
 * Versie 1.6.1 – alle auteurs & gekoppelde users in de héle keten,
 *                maar NIET de auteur van de actuele reactie.
 */

if ( ! function_exists( 'gem_mailer_get_option_int' ) ) {
        require_once __DIR__ . '/../includes/options.php';
}

if ( ! function_exists( 'gem_notify_mail' ) ) :

	/* -------------------- helpers -------------------- */

	function gem_mail_bulk( array $user_ids, int $onderwerp_id, string $template ) {

		foreach ( $user_ids as $uid ) {
			$user = get_userdata( $uid );
			if ( ! $user || ! is_email( $user->user_email ) ) {
				continue;
			}
			$body = str_replace(
				[ '{{recipient_name}}', '{{post_title}}', '{{post_permalink}}',
				  '{{site_name}}', '{{site_url}}' ],
				[
					$user->display_name,
					get_the_title( $onderwerp_id ),
					get_permalink( $onderwerp_id ),
					get_bloginfo( 'name' ),
					home_url(),
				],
				$template
			);

			wp_mail(
				$user->user_email,
				sprintf( 'Nieuwe reactie op “%s”', get_the_title( $onderwerp_id ) ),
				$body,
				[ 'Content-Type: text/html; charset=UTF-8' ]
			);
		}
	}


	function gem_collect_chain_ids( int $reactie_id, int $rel_post_post ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'jet_rel_' . $rel_post_post;
		$ids   = [ $reactie_id ];

		while ( true ) {
			$parent = $wpdb->get_var( $wpdb->prepare(
				"SELECT parent_object_id FROM {$table} WHERE child_object_id=%d LIMIT 1",
				end( $ids )
			) );
			if ( ! $parent ) {
				break;
			}
			$ids[] = (int) $parent;
		}
		return $ids; /* reactie → … → onderwerp */
	}


	function gem_users_from_post_user_relation( int $post_id, int $rel_post_user ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'jet_rel_' . $rel_post_user;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT child_object_id FROM {$table} WHERE parent_object_id=%d",
			$post_id
		) ) );
	}


	function gem_try_chain_mail( int $reactie_id ) {

		/* anti-dubbel binnen 60 s */
		$last = (int) get_post_meta( $reactie_id, '_gem_mail_sent', true );
		if ( $last && ( time() - $last ) < 60 ) {
			return;
		}

                $rel_post_post = gem_mailer_get_option_int( GEM_MAILER_OPT_REL_TOPIC_REACTIE );
                $rel_post_user = gem_mailer_get_option_int( GEM_MAILER_OPT_REL_REACTIE_USER );
                $template      = gem_mailer_get_option( GEM_MAILER_OPT_TEMPLATE_REACTIE, '' )
                        ?: '<p>Nieuwe reactie op "{{post_title}}".</p>';

		if ( ! $rel_post_post ) {
			error_log( 'GEM-MAIL: rel_post_post optie ontbreekt.' );
			return;
		}

		$chain_ids   = gem_collect_chain_ids( $reactie_id, $rel_post_post );
		$onderwerp_id = end( $chain_ids );

		/* ------- ontvangers verzamelen ------- */
		$user_ids = [];

		foreach ( $chain_ids as $pid ) {
			$user_ids[] = (int) get_post_field( 'post_author', $pid );

			if ( $rel_post_user ) {
				$user_ids = array_merge(
					$user_ids,
					gem_users_from_post_user_relation( $pid, $rel_post_user )
				);
			}
		}

		/* uitsluiten: auteur van de huidige reactie */
		$current_author = (int) get_post_field( 'post_author', $reactie_id );
		$user_ids       = array_diff( $user_ids, [ $current_author ] );

		$user_ids = array_unique( array_filter( $user_ids ) );
		if ( ! $user_ids ) {
			return;
		}

		gem_mail_bulk( $user_ids, $onderwerp_id, $template );
		update_post_meta( $reactie_id, '_gem_mail_sent', time() );
	}

endif;


/* -------------------- Triggers -------------------- */

/* A. save_post + één cron-retry */
add_action( 'save_post_gem-reacties', function ( $post_ID, $post ) {

	if ( wp_is_post_autosave( $post_ID ) || wp_is_post_revision( $post_ID ) ) {
		return;
	}
	if ( 'publish' !== $post->post_status ) {
		return;
	}

	gem_try_chain_mail( $post_ID );

	if ( ! wp_next_scheduled( 'gem_mailer_chain_retry', [ $post_ID ] ) ) {
		wp_schedule_single_event( time() + 10, 'gem_mailer_chain_retry', [ $post_ID ] );
	}
}, 20, 2 );

add_action( 'gem_mailer_chain_retry', 'gem_try_chain_mail' );

/* B. relation/after-add-child (JetFormBuilder Connect-actie) */
add_action(
	'jet-engine/relation/after-add-child',
	function ( $relation, $parent_id, $child_id ) {

                $config = gem_mailer_get_option_int( GEM_MAILER_OPT_REL_TOPIC_REACTIE );
		if ( intval( $relation->id ) !== $config ) {
			return;
		}
		gem_try_chain_mail( $child_id );  /* child = reactie */
	},
	10,
	3
);
