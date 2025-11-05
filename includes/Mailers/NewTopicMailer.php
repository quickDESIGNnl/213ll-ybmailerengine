<?php
namespace GemMailer\Mailers;

use GemMailer\Support\Email;
use GemMailer\Support\Relations;
use GemMailer\Support\Settings;
use GemMailer\Support\Utils;
use WP_Post;
use function is_email;

/**
 * Dispatch notifications whenever een nieuw onderwerp gepubliceerd wordt.
 */
class NewTopicMailer {
    private const CRON_HOOK = 'gem_mailer_process_topic';

    public function register(): void {
        add_action( 'transition_post_status', [ $this, 'maybe_notify' ], 10, 3 );
        add_action( self::CRON_HOOK, [ $this, 'handle_scheduled' ] );
    }

    public function maybe_notify( string $new_status, string $old_status, WP_Post $post ): void {
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        $topic_cpt = (string) Settings::get( Settings::OPT_TOPIC_CPT, '' );
        if ( ! $topic_cpt || $post->post_type !== $topic_cpt ) {
            return;
        }

        $delay = max( 0, (int) Settings::get( Settings::OPT_THEMA_DELAY, 0 ) );
        $timestamp = time() + $delay;

        $existing = wp_next_scheduled( self::CRON_HOOK, [ $post->ID ] );
        if ( $existing ) {
            wp_unschedule_event( $existing, self::CRON_HOOK, [ $post->ID ] );
        }

        wp_schedule_single_event( $timestamp, self::CRON_HOOK, [ $post->ID ] );
    }

    public function handle_scheduled( int $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
            return;
        }

        $this->notify_post( $post );
    }

    public function process_latest_topic(): bool {
        $post = $this->latest_topic();
        if ( ! $post ) {
            return false;
        }

        $this->notify_post( $post, true );

        return true;
    }

    public function send_test_email( string $email ): bool {
        if ( ! is_email( $email ) ) {
            return false;
        }

        $post = $this->latest_topic();
        if ( ! $post ) {
            return false;
        }

        $template         = (string) Settings::get( Settings::OPT_THEMA_EMAIL_TEMPLATE );
        $subject_template = (string) Settings::get( Settings::OPT_THEMA_EMAIL_SUBJECT );

        if ( ! $template || ! $subject_template ) {
            return false;
        }

        $targets = $this->resolve_targets( $post );
        $target  = $targets[0] ?? [
            'user_lookup_id'   => 0,
            'context_id'       => 0,
            'context_taxonomy' => '',
        ];

        $context = $this->build_context( $post, $target );
        $context['recipient_name'] = $email;

        $subject = Email::render( $subject_template, $context );
        $message = Email::render( $template, $context );

        return (bool) wp_mail( $email, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    private function notify_post( WP_Post $post, bool $force = false ): void {
        if ( ! $force && get_post_meta( $post->ID, Settings::META_TOPIC_SENT, true ) ) {
            return;
        }

        $template         = (string) Settings::get( Settings::OPT_THEMA_EMAIL_TEMPLATE );
        $subject_template = (string) Settings::get( Settings::OPT_THEMA_EMAIL_SUBJECT );
        $relation_users   = (int) Settings::get( Settings::OPT_THEMA_USER_RELATION, 0 );

        if ( ! $relation_users || ! $template || ! $subject_template ) {
            return;
        }

        $targets = $this->resolve_targets( $post );
        if ( ! $targets ) {
            return;
        }

        $notified = false;

        foreach ( $targets as $target ) {
            $lookup_id = (int) ( $target['user_lookup_id'] ?? 0 );
            if ( ! $lookup_id ) {
                continue;
            }

            $user_ids = Relations::children( $relation_users, $lookup_id );
            $user_ids = Utils::filter_user_ids( $user_ids, (int) $post->post_author );

            if ( ! $user_ids ) {
                continue;
            }

            $context = $this->build_context( $post, $target );

            Email::send_to_users( $user_ids, $subject_template, $template, $context );
            $notified = true;
        }

        if ( $notified ) {
            update_post_meta( $post->ID, Settings::META_TOPIC_SENT, time() );
        }
    }

    /**
     * Resolve the relation targets for a topic.
     *
     * @return array<int,array<string,int|string>>
     */
    private function resolve_targets( WP_Post $post ): array {
        $taxonomy        = (string) Settings::get( Settings::OPT_THEMA_TOPIC_TAXONOMY, '' );
        $rel_term_object = (int) Settings::get( Settings::OPT_THEMA_RELATION, 0 );
        $rel_topic_thema = (int) Settings::get( Settings::OPT_THEMA_TOPIC_RELATION, 0 );

        $targets = [];

        $lookup_ids = [];
        if ( $rel_topic_thema ) {
            $lookup_ids = Relations::parents( $rel_topic_thema, $post->ID );
        }

        $term_ids = [];
        if ( $taxonomy ) {
            $terms = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
            if ( ! is_wp_error( $terms ) ) {
                $term_ids = array_map( 'intval', $terms );
            }
        }

        if ( $term_ids ) {
            foreach ( $term_ids as $term_id ) {
                $targets[] = [
                    'user_lookup_id'   => (int) $term_id,
                    'context_id'       => (int) $term_id,
                    'context_taxonomy' => $taxonomy,
                ];
            }
        }

        if ( $lookup_ids && ! $targets ) {
            foreach ( $lookup_ids as $lookup_id ) {
                $targets[] = [
                    'user_lookup_id'   => (int) $lookup_id,
                    'context_id'       => (int) $lookup_id,
                    'context_taxonomy' => '',
                ];
            }
        }

        if ( $rel_term_object && $targets ) {
            $expanded = [];
            foreach ( $targets as $target ) {
                $parents = Relations::parents( $rel_term_object, (int) $target['context_id'] );
                if ( $parents ) {
                    foreach ( $parents as $parent_id ) {
                        $expanded[] = $target + [ 'theme_id' => (int) $parent_id ];
                    }
                } else {
                    $expanded[] = $target;
                }
            }
            $targets = $expanded;
        }

        return $targets;
    }

    private function build_context( WP_Post $post, array $target ): array {
        $context_taxonomy = (string) ( $target['context_taxonomy'] ?? '' );
        $context_id       = (int) ( $target['context_id'] ?? 0 );
        $theme_id         = (int) ( $target['theme_id'] ?? 0 );

        $reply = $this->resolve_latest_reply_context( $post );

        $thema_id_for_context = $theme_id ?: $context_id;
        $thema_taxonomy       = $theme_id ? '' : $context_taxonomy;

        $topic_title   = get_the_title( $post );
        $topic_link    = get_permalink( $post );
        $topic_excerpt = Utils::excerpt( $post->ID, 40 );
        $topic_author  = get_the_author_meta( 'display_name', $post->post_author );

        return [
            'thema_title'     => Utils::resolve_title( $thema_id_for_context, $thema_taxonomy ),
            'thema_link'      => Utils::resolve_link( $thema_id_for_context, $thema_taxonomy ),
            'topic_title'     => $topic_title,
            'topic_link'      => $topic_link,
            'topic_excerpt'   => $topic_excerpt,
            'topic_author'    => $topic_author,
            'post_title'      => $topic_title,
            'post_permalink'  => $topic_link,
            'post_excerpt'    => $topic_excerpt,
            'site_name'       => get_bloginfo( 'name' ),
            'site_url'        => home_url(),
            'reply_author'    => $reply['reply_author'],
            'reply_excerpt'   => $reply['reply_excerpt'],
            'reply_permalink' => $reply['reply_permalink'],
        ];
    }

    /**
     * Resolve data for the most recent reactie on a topic.
     *
     * @return array{reply_author:string,reply_excerpt:string,reply_permalink:string}
     */
    private function resolve_latest_reply_context( WP_Post $post ): array {
        $defaults = [
            'reply_author'    => '',
            'reply_excerpt'   => '',
            'reply_permalink' => '',
        ];

        $relation = (int) Settings::get( Settings::OPT_TOPIC_REACTION_REL, 0 );
        if ( ! $relation ) {
            return $defaults;
        }

        $reaction_ids = Relations::children( $relation, $post->ID );
        if ( ! $reaction_ids ) {
            return $defaults;
        }

        $latest = null;

        foreach ( $reaction_ids as $reaction_id ) {
            $reaction = get_post( $reaction_id );
            if ( ! $reaction instanceof WP_Post || 'publish' !== $reaction->post_status ) {
                continue;
            }

            if ( ! $latest || strtotime( $reaction->post_date_gmt ) > strtotime( $latest->post_date_gmt ) ) {
                $latest = $reaction;
            }
        }

        if ( ! $latest ) {
            return $defaults;
        }

        return [
            'reply_author'    => get_the_author_meta( 'display_name', $latest->post_author ),
            'reply_excerpt'   => Utils::excerpt( $latest->ID, 20 ),
            'reply_permalink' => get_permalink( $latest ),
        ];
    }

    private function latest_topic(): ?WP_Post {
        $topic_cpt = (string) Settings::get( Settings::OPT_TOPIC_CPT, '' );
        if ( ! $topic_cpt ) {
            return null;
        }

        $posts = get_posts(
            [
                'post_type'      => $topic_cpt,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        if ( ! $posts ) {
            return null;
        }

        $post = $posts[0];

        return $post instanceof WP_Post ? $post : null;
    }
}
