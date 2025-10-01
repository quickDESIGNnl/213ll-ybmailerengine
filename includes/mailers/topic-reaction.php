<?php
/**
 * Handle notifications when a new reaction is added to a topic.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../helpers.php';

add_action( 'transition_post_status', 'gem_mailer_maybe_notify_new_reaction', 10, 3 );

/**
 * Trigger notifications for a newly published reaction.
 */
function gem_mailer_maybe_notify_new_reaction( string $new_status, string $old_status, WP_Post $post ): void {
    if ( 'publish' !== $new_status || 'publish' === $old_status ) {
        return;
    }

    $reaction_cpt = gem_mailer_get_option( GEM_MAILER_OPT_REACTIE_CPT, '' );
    if ( ! $reaction_cpt || $post->post_type !== $reaction_cpt ) {
        return;
    }

    gem_mailer_notify_topic_followers( $post );
    gem_mailer_notify_reaction_followers( $post );
}

/**
 * Notify all users linked to the parent topic.
 */
function gem_mailer_notify_topic_followers( WP_Post $reaction ): void {
    if ( get_post_meta( $reaction->ID, GEM_MAILER_META_REACTION_SENT, true ) ) {
        return;
    }

    $relation_topic_reactie = (int) gem_mailer_get_option( GEM_MAILER_OPT_TOPIC_REACTIE_REL, 0 );
    $relation_topic_user    = (int) gem_mailer_get_option( GEM_MAILER_OPT_TOPIC_USER_RELATION, 0 );
    $template               = (string) gem_mailer_get_option( GEM_MAILER_OPT_TOPIC_EMAIL_TEMPLATE, '' );

    if ( ! $relation_topic_reactie || ! $relation_topic_user || ! $template ) {
        return;
    }

    $topic_ids = gem_mailer_relation_parents( $relation_topic_reactie, $reaction->ID );
    if ( ! $topic_ids ) {
        return;
    }

    foreach ( $topic_ids as $topic_id ) {
        $user_ids = gem_mailer_relation_children( $relation_topic_user, $topic_id );
        $user_ids = gem_mailer_filter_user_ids( $user_ids, (int) $reaction->post_author );

        if ( ! $user_ids ) {
            continue;
        }

        $context = [
            'topic_title'      => get_the_title( $topic_id ),
            'topic_link'       => get_permalink( $topic_id ),
            'reaction_author'  => get_the_author_meta( 'display_name', $reaction->post_author ),
            'reaction_link'    => get_permalink( $reaction ),
            'reaction_excerpt' => gem_mailer_prepare_excerpt( $reaction->ID ),
            'site_name'        => get_bloginfo( 'name' ),
            'site_url'         => home_url(),
        ];

        $subject = sprintf(
            __( 'Nieuwe reactie op %s', 'gem-mailer' ),
            $context['topic_title'] ?: __( 'een onderwerp', 'gem-mailer' )
        );

        gem_mailer_send_template_to_users( $user_ids, $subject, $template, $context );
    }

    update_post_meta( $reaction->ID, GEM_MAILER_META_REACTION_SENT, time() );
}

/**
 * Notify the followers of the parent reaction when a reply is posted.
 */
function gem_mailer_notify_reaction_followers( WP_Post $reaction ): void {
    if ( get_post_meta( $reaction->ID, GEM_MAILER_META_REPLY_SENT, true ) ) {
        return;
    }

    $relation_reactie_reply = (int) gem_mailer_get_option( GEM_MAILER_OPT_REACTIE_REPLY_REL, 0 );
    $relation_reactie_user  = (int) gem_mailer_get_option( GEM_MAILER_OPT_REACTIE_USER_REL, 0 );
    $template               = (string) gem_mailer_get_option( GEM_MAILER_OPT_REACTIE_EMAIL_TEMPLATE, '' );

    if ( ! $relation_reactie_reply || ! $relation_reactie_user || ! $template ) {
        return;
    }

    $parent_ids = gem_mailer_relation_parents( $relation_reactie_reply, $reaction->ID );
    if ( ! $parent_ids ) {
        return;
    }

    foreach ( $parent_ids as $parent_id ) {
        $user_ids = gem_mailer_relation_children( $relation_reactie_user, $parent_id );
        $user_ids = gem_mailer_filter_user_ids( $user_ids, (int) $reaction->post_author );

        if ( ! $user_ids ) {
            continue;
        }

        $topic_ids = gem_mailer_relation_parents( (int) gem_mailer_get_option( GEM_MAILER_OPT_TOPIC_REACTIE_REL, 0 ), $parent_id );
        $topic_id  = $topic_ids ? $topic_ids[0] : 0;

        $context = [
            'topic_title'      => $topic_id ? get_the_title( $topic_id ) : '',
            'topic_link'       => $topic_id ? get_permalink( $topic_id ) : '',
            'reaction_author'  => get_the_author_meta( 'display_name', get_post_field( 'post_author', $parent_id ) ),
            'reaction_excerpt' => gem_mailer_prepare_excerpt( $parent_id ),
            'reply_author'     => get_the_author_meta( 'display_name', $reaction->post_author ),
            'reply_excerpt'    => gem_mailer_prepare_excerpt( $reaction->ID ),
            'reply_link'       => get_permalink( $reaction ),
            'site_name'        => get_bloginfo( 'name' ),
            'site_url'         => home_url(),
        ];

        $subject = __( 'Nieuw antwoord op je reactie', 'gem-mailer' );

        gem_mailer_send_template_to_users( $user_ids, $subject, $template, $context );
    }

    update_post_meta( $reaction->ID, GEM_MAILER_META_REPLY_SENT, time() );
}
