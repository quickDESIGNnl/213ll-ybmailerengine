<?php
/**
 * Handle notifications for new topics inside a forum taxonomy.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../helpers.php';

add_action( 'transition_post_status', 'gem_mailer_maybe_notify_new_topic', 10, 3 );

/**
 * Trigger a notification when a topic is first published.
 */
function gem_mailer_maybe_notify_new_topic( string $new_status, string $old_status, WP_Post $post ): void {
    if ( 'publish' !== $new_status || 'publish' === $old_status ) {
        return;
    }

    $topic_cpt = gem_mailer_get_option( GEM_MAILER_OPT_TOPIC_CPT, '' );
    if ( ! $topic_cpt || $post->post_type !== $topic_cpt ) {
        return;
    }

    if ( get_post_meta( $post->ID, GEM_MAILER_META_TOPIC_SENT, true ) ) {
        return;
    }

    $taxonomy         = (string) gem_mailer_get_option( GEM_MAILER_OPT_THEMA_TOPIC_TAXONOMY, '' );
    $rel_term_to_obj  = (int) gem_mailer_get_option( GEM_MAILER_OPT_THEMA_RELATION, 0 );
    $rel_topic_thema  = (int) gem_mailer_get_option( GEM_MAILER_OPT_THEMA_TOPIC_RELATION, 0 );
    $rel_thema_user   = (int) gem_mailer_get_option( GEM_MAILER_OPT_THEMA_USER_RELATION, 0 );
    $template         = (string) gem_mailer_get_option( GEM_MAILER_OPT_THEMA_EMAIL_TEMPLATE, '' );

    if ( ! $rel_thema_user || ! $template ) {
        return;
    }

    $thema_ids = [];
    if ( $rel_topic_thema ) {
        $thema_ids = gem_mailer_relation_parents( $rel_topic_thema, $post->ID );
    }

    $term_ids = [];
    if ( ! $thema_ids && $taxonomy ) {
        $term_ids = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $term_ids ) ) {
            $term_ids = [];
        }
        $thema_ids = array_map( 'intval', $term_ids );
    }

    if ( ! $thema_ids && ! $term_ids ) {
        return;
    }

    $targets = [];

    if ( $term_ids ) {
        foreach ( $term_ids as $term_id ) {
            $targets[] = [
                'lookup_id'        => (int) $term_id,
                'context_id'       => (int) $term_id,
                'context_taxonomy' => $taxonomy,
            ];
        }
    }

    if ( $thema_ids && ! $targets ) {
        foreach ( $thema_ids as $thema_id ) {
            $targets[] = [
                'lookup_id'        => (int) $thema_id,
                'context_id'       => (int) $thema_id,
                'context_taxonomy' => $taxonomy,
            ];
        }
    }

    if ( $rel_term_to_obj && $targets ) {
        $expanded = [];
        foreach ( $targets as $target ) {
            $parent_ids = gem_mailer_relation_parents( $rel_term_to_obj, $target['lookup_id'] );
            if ( $parent_ids ) {
                foreach ( $parent_ids as $parent_id ) {
                    $expanded[] = [
                        'lookup_id'        => (int) $parent_id,
                        'context_id'       => $target['context_id'],
                        'context_taxonomy' => $target['context_taxonomy'],
                    ];
                }
            } else {
                $expanded[] = $target;
            }
        }
        $targets = $expanded;
    }

    foreach ( $targets as $target ) {
        $user_ids = gem_mailer_relation_children( $rel_thema_user, $target['lookup_id'] );
        $user_ids = gem_mailer_filter_user_ids( $user_ids, (int) $post->post_author );

        if ( ! $user_ids ) {
            continue;
        }

        $thema_title = gem_mailer_resolve_entity_title( $target['context_id'], $target['context_taxonomy'] );
        $thema_link  = gem_mailer_resolve_entity_link( $target['context_id'], $target['context_taxonomy'] );

        $context = [
            'thema_title'   => $thema_title,
            'thema_link'    => $thema_link,
            'topic_title'   => get_the_title( $post ),
            'topic_link'    => get_permalink( $post ),
            'topic_excerpt' => gem_mailer_prepare_excerpt( $post->ID ),
            'topic_author'  => get_the_author_meta( 'display_name', $post->post_author ),
            'site_name'     => get_bloginfo( 'name' ),
            'site_url'      => home_url(),
        ];

        $subject = sprintf(
            __( 'Nieuw onderwerp in %s', 'gem-mailer' ),
            $thema_title ?: __( 'het forum', 'gem-mailer' )
        );

        gem_mailer_send_template_to_users( $user_ids, $subject, $template, $context );
    }

    update_post_meta( $post->ID, GEM_MAILER_META_TOPIC_SENT, time() );
}
