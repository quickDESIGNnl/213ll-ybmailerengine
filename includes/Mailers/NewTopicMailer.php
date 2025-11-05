<?php
namespace GemMailer\Mailers;

use GemMailer\Support\Email;
use GemMailer\Support\Relations;
use GemMailer\Support\Settings;
use GemMailer\Support\Utils;
use WP_Post;
use function __;
use function add_action;
use function get_bloginfo;
use function get_post;
use function get_post_meta;
use function get_permalink;
use function get_the_author_meta;
use function get_the_title;
use function home_url;
use function is_wp_error;
use function time;
use function update_post_meta;
use function wp_clear_scheduled_hook;
use function wp_get_object_terms;
use function wp_schedule_single_event;

/**
 * Dispatch notifications whenever een nieuw onderwerp gepubliceerd wordt.
 */
class NewTopicMailer {
    private const EVENT_HOOK = 'gem_mailer_process_new_topic';

    public function register(): void {
        add_action( 'transition_post_status', [ $this, 'maybe_notify' ], 10, 3 );
        add_action( self::EVENT_HOOK, [ $this, 'process_single' ] );
    }

    public function maybe_notify( string $new_status, string $old_status, WP_Post $post ): void {
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        $topic_cpt    = (string) Settings::get( Settings::OPT_TOPIC_CPT, '' );
        $allowed_cpts = array_filter(
            array_unique(
                array_merge(
                    $topic_cpt ? [ $topic_cpt ] : [],
                    [ 'onderwerpen' ]
                )
            )
        );

        if ( ! in_array( $post->post_type, $allowed_cpts, true ) ) {
            return;
        }

        $delay = max( 0, (int) Settings::get( Settings::OPT_THEMA_EMAIL_DELAY, 0 ) );

        wp_clear_scheduled_hook( self::EVENT_HOOK, [ $post->ID ] );
        wp_schedule_single_event( time() + $delay, self::EVENT_HOOK, [ $post->ID ] );
    }

    public function process_single( int $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
            return;
        }

        $this->notify_post( $post );
    }

    private function notify_post( WP_Post $post ): void {
        if ( get_post_meta( $post->ID, Settings::META_TOPIC_SENT, true ) ) {
            return;
        }

        $taxonomy        = (string) Settings::get( Settings::OPT_THEMA_TOPIC_TAXONOMY, '' );
        $rel_term_object = (int) Settings::get( Settings::OPT_THEMA_RELATION, 0 );
        $rel_topic_thema = (int) Settings::get( Settings::OPT_THEMA_TOPIC_RELATION, 0 );
        $rel_thema_users = (int) Settings::get( Settings::OPT_THEMA_USER_RELATION, 0 );
        $template        = (string) Settings::get( Settings::OPT_THEMA_EMAIL_TEMPLATE );
        $subject_tpl     = (string) Settings::get( Settings::OPT_THEMA_EMAIL_SUBJECT );

        if ( ! $rel_thema_users || ! $template || ! $subject_tpl ) {
            return;
        }

        $thema_ids = [];
        if ( $rel_topic_thema ) {
            $thema_ids = Relations::parents( $rel_topic_thema, $post->ID );
        }

        $term_ids = [];
        if ( ! $thema_ids && $taxonomy ) {
            $terms = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
            if ( ! is_wp_error( $terms ) ) {
                $term_ids = array_map( 'intval', $terms );
                $thema_ids = $term_ids;
            }
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
                    'context_taxonomy' => '',
                ];
            }
        }

        if ( $rel_term_object && $targets ) {
            $expanded = [];
            foreach ( $targets as $target ) {
                $parents = Relations::parents( $rel_term_object, $target['lookup_id'] );
                if ( $parents ) {
                    foreach ( $parents as $parent_id ) {
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
            $user_ids = Relations::children( $rel_thema_users, $target['lookup_id'] );
            $user_ids = Utils::filter_user_ids( $user_ids, (int) $post->post_author );

            if ( ! $user_ids ) {
                continue;
            }

            $thema_title = Utils::resolve_title( $target['context_id'], $target['context_taxonomy'] );
            $thema_link  = Utils::resolve_link( $target['context_id'], $target['context_taxonomy'] );

            $context = [
                'thema_title'   => $thema_title,
                'thema_link'    => $thema_link,
                'topic_title'   => get_the_title( $post ),
                'topic_link'    => get_permalink( $post ),
                'topic_excerpt' => Utils::excerpt( $post->ID ),
                'topic_author'  => get_the_author_meta( 'display_name', $post->post_author ),
                'post_title'    => get_the_title( $post ),
                'post_permalink'=> get_permalink( $post ),
                'site_name'     => get_bloginfo( 'name' ),
                'site_url'      => home_url(),
                'reply_author'  => '',
                'reply_excerpt' => '',
                'reply_permalink' => '',
            ];

            Email::send_to_users( $user_ids, $subject_tpl, $template, $context );
        }

        update_post_meta( $post->ID, Settings::META_TOPIC_SENT, time() );
    }
}
