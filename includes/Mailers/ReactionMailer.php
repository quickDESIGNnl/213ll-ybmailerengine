<?php
namespace GemMailer\Mailers;

use GemMailer\Support\Email;
use GemMailer\Support\Relations;
use GemMailer\Support\Settings;
use GemMailer\Support\Utils;
use WP_Post;
use function __;

/**
 * Verstuurt meldingen voor nieuwe reacties en replies binnen JetEngine forums.
 */
class ReactionMailer {
    /**
     * Queue of reaction IDs pending notification dispatch.
     *
     * @var array<int,int>
     */
    private array $queue = [];

    public function register(): void {
        add_action( 'transition_post_status', [ $this, 'maybe_notify' ], 10, 3 );
        add_action( 'shutdown', [ $this, 'process_queue' ] );
    }

    public function maybe_notify( string $new_status, string $old_status, WP_Post $post ): void {
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        $reaction_cpt = (string) Settings::get( Settings::OPT_REACTION_CPT, '' );
        if ( ! $reaction_cpt || $post->post_type !== $reaction_cpt ) {
            return;
        }

        $this->queue[ $post->ID ] = $post->ID;
    }

    public function process_queue(): void {
        if ( ! $this->queue ) {
            return;
        }

        foreach ( $this->queue as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
                continue;
            }

            $this->notify_topic_followers( $post );
            $this->notify_reply_followers( $post );
        }

        $this->queue = [];
    }

    private function notify_topic_followers( WP_Post $reaction ): void {
        if ( get_post_meta( $reaction->ID, Settings::META_REACTION_SENT, true ) ) {
            return;
        }

        $relation_topic_reaction = (int) Settings::get( Settings::OPT_TOPIC_REACTION_REL, 0 );
        $relation_topic_user     = (int) Settings::get( Settings::OPT_TOPIC_USER_RELATION, 0 );
        $template                = (string) Settings::get( Settings::OPT_TOPIC_EMAIL_TEMPLATE );

        if ( ! $relation_topic_reaction || ! $relation_topic_user || ! $template ) {
            return;
        }

        $topic_ids = Relations::parents( $relation_topic_reaction, $reaction->ID );
        if ( ! $topic_ids ) {
            return;
        }

        foreach ( $topic_ids as $topic_id ) {
            $user_ids = Relations::children( $relation_topic_user, $topic_id );
            $user_ids = Utils::filter_user_ids( $user_ids, (int) $reaction->post_author );

            if ( ! $user_ids ) {
                continue;
            }

            $context = [
                'topic_title'      => get_the_title( $topic_id ),
                'topic_link'       => get_permalink( $topic_id ),
                'reaction_author'  => get_the_author_meta( 'display_name', $reaction->post_author ),
                'reaction_link'    => get_permalink( $reaction ),
                'reaction_excerpt' => Utils::excerpt( $reaction->ID ),
                'site_name'        => get_bloginfo( 'name' ),
                'site_url'         => home_url(),
            ];

            $subject = sprintf(
                __( 'Nieuwe reactie op %s', 'gem-mailer' ),
                $context['topic_title'] ?: __( 'een onderwerp', 'gem-mailer' )
            );

            Email::send_to_users( $user_ids, $subject, $template, $context );
        }

        update_post_meta( $reaction->ID, Settings::META_REACTION_SENT, time() );
    }

    private function notify_reply_followers( WP_Post $reaction ): void {
        if ( get_post_meta( $reaction->ID, Settings::META_REPLY_SENT, true ) ) {
            return;
        }

        $relation_reaction_reply = (int) Settings::get( Settings::OPT_REACTION_REPLY_REL, 0 );
        $relation_reaction_user  = (int) Settings::get( Settings::OPT_REACTION_USER_REL, 0 );
        $template                = (string) Settings::get( Settings::OPT_REACTION_EMAIL_TPL );

        if ( ! $relation_reaction_reply || ! $relation_reaction_user || ! $template ) {
            return;
        }

        $parent_ids = Relations::parents( $relation_reaction_reply, $reaction->ID );
        if ( ! $parent_ids ) {
            return;
        }

        foreach ( $parent_ids as $parent_id ) {
            $user_ids = Relations::children( $relation_reaction_user, $parent_id );
            $user_ids = Utils::filter_user_ids( $user_ids, (int) $reaction->post_author );

            if ( ! $user_ids ) {
                continue;
            }

            $topic_rel = (int) Settings::get( Settings::OPT_TOPIC_REACTION_REL, 0 );
            $topic_ids = $topic_rel ? Relations::parents( $topic_rel, $parent_id ) : [];
            $topic_id  = $topic_ids ? (int) $topic_ids[0] : 0;

            $context = [
                'topic_title'      => $topic_id ? get_the_title( $topic_id ) : '',
                'topic_link'       => $topic_id ? get_permalink( $topic_id ) : '',
                'reaction_author'  => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $parent_id ) ),
                'reaction_excerpt' => Utils::excerpt( $parent_id ),
                'reply_author'     => get_the_author_meta( 'display_name', $reaction->post_author ),
                'reply_excerpt'    => Utils::excerpt( $reaction->ID ),
                'reply_link'       => get_permalink( $reaction ),
                'site_name'        => get_bloginfo( 'name' ),
                'site_url'         => home_url(),
            ];

            $subject = __( 'Nieuw antwoord op je reactie', 'gem-mailer' );

            Email::send_to_users( $user_ids, $subject, $template, $context );
        }

        update_post_meta( $reaction->ID, Settings::META_REPLY_SENT, time() );
    }
}
