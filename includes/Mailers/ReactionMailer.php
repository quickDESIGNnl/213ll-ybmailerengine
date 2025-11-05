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
use function get_permalink;
use function get_post;
use function get_post_field;
use function get_post_meta;
use function get_the_author_meta;
use function get_the_title;
use function home_url;
use function time;
use function update_post_meta;
use function wp_clear_scheduled_hook;
use function wp_schedule_single_event;

/**
 * Verstuurt meldingen voor nieuwe reacties binnen JetEngine forums.
 */
class ReactionMailer {
    private const EVENT_HOOK = 'gem_mailer_process_reaction';

    public function register(): void {
        add_action( 'transition_post_status', [ $this, 'maybe_schedule' ], 10, 3 );
        add_action( self::EVENT_HOOK, [ $this, 'process_single' ] );
    }

    public function maybe_schedule( string $new_status, string $old_status, WP_Post $post ): void {
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        $reaction_cpt = (string) Settings::get( Settings::OPT_REACTION_CPT, '' );
        if ( ! $reaction_cpt || $post->post_type !== $reaction_cpt ) {
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

        $reaction_cpt = (string) Settings::get( Settings::OPT_REACTION_CPT, '' );
        if ( $reaction_cpt && $post->post_type !== $reaction_cpt ) {
            return;
        }

        $this->notify_participants( $post );
    }

    private function notify_participants( WP_Post $reaction ): void {
        if ( get_post_meta( $reaction->ID, Settings::META_REACTION_SENT, true ) ) {
            return;
        }

        $relation_topic_reaction = (int) Settings::get( Settings::OPT_TOPIC_REACTION_REL, 0 );
        $template                = (string) Settings::get( Settings::OPT_TOPIC_EMAIL_TEMPLATE );

        if ( ! $relation_topic_reaction || ! $template ) {
            return;
        }

        $topic_ids = Relations::parents( $relation_topic_reaction, $reaction->ID );
        if ( ! $topic_ids ) {
            return;
        }

        foreach ( $topic_ids as $topic_id ) {
            $user_ids = $this->collect_participants( (int) $topic_id, $reaction, $relation_topic_reaction );
            if ( ! $user_ids ) {
                continue;
            }

            $context = $this->build_context( (int) $topic_id, $reaction );

            $subject = sprintf(
                __( 'Nieuwe reactie op %s', 'gem-mailer' ),
                $context['topic_title'] ?: __( 'een onderwerp', 'gem-mailer' )
            );

            Email::send_to_users( $user_ids, $subject, $template, $context );
        }

        update_post_meta( $reaction->ID, Settings::META_REACTION_SENT, time() );
    }

    /**
     * @return int[]
     */
    private function collect_participants( int $topic_id, WP_Post $reaction, int $relation_id ): array {
        $user_ids = [];

        $topic_author = (int) get_post_field( 'post_author', $topic_id );
        if ( $topic_author ) {
            $user_ids[] = $topic_author;
        }

        $reaction_ids = Relations::children( $relation_id, $topic_id );
        foreach ( $reaction_ids as $reaction_id ) {
            $author = (int) get_post_field( 'post_author', $reaction_id );
            if ( $author ) {
                $user_ids[] = $author;
            }
        }

        return Utils::filter_user_ids( $user_ids, (int) $reaction->post_author );
    }

    private function build_context( int $topic_id, WP_Post $reaction ): array {
        $topic_title  = get_the_title( $topic_id );
        $topic_link   = get_permalink( $topic_id );
        $reply_author = get_the_author_meta( 'display_name', (int) $reaction->post_author );
        $reply_link   = get_permalink( $reaction );
        $reply_excerpt = Utils::excerpt( $reaction->ID );

        return [
            'topic_title'      => $topic_title,
            'topic_link'       => $topic_link,
            'reaction_author'  => $reply_author,
            'reaction_link'    => $reply_link,
            'reaction_excerpt' => $reply_excerpt,
            'post_title'       => $topic_title,
            'post_permalink'   => $topic_link,
            'reply_author'     => $reply_author,
            'reply_excerpt'    => $reply_excerpt,
            'reply_permalink'  => $reply_link,
            'site_name'        => get_bloginfo( 'name' ),
            'site_url'         => home_url(),
        ];
    }

}
