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
use function get_post_meta;
use function get_the_author_meta;
use function get_the_title;
use function home_url;
use function time;
use function update_post_meta;

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
        add_action( 'gem_jfb_notify_parent_author', [ $this, 'queue_from_form_action' ], 10, 2 );
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

    public function queue_from_form_action( $reaction_reference = null, $submission = null ): void {
        $post = null;

        if ( $reaction_reference instanceof WP_Post ) {
            $post = $reaction_reference;
        } elseif ( is_array( $reaction_reference ) && isset( $reaction_reference['post_id'] ) ) {
            $post = get_post( (int) $reaction_reference['post_id'] );
        } elseif ( is_array( $submission ) && isset( $submission['post_id'] ) ) {
            $post = get_post( (int) $submission['post_id'] );
        } else {
            $post = get_post( (int) $reaction_reference );
        }

        if ( $post instanceof WP_Post ) {
            $this->queue[ $post->ID ] = $post->ID;
        }
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

            $this->notify_followers( $post );
        }

        $this->queue = [];
    }

    private function notify_followers( WP_Post $reaction ): void {
        if ( get_post_meta( $reaction->ID, Settings::META_REACTION_SENT, true ) ) {
            return;
        }

        $relation_topic_reaction = (int) Settings::get( Settings::OPT_TOPIC_REACTION_REL, 0 );
        $template                = (string) Settings::get( Settings::OPT_REACTION_EMAIL_TEMPLATE );

        if ( ! $relation_topic_reaction || ! $template ) {
            return;
        }

        $topic_ids = Relations::parents( $relation_topic_reaction, $reaction->ID );
        if ( ! $topic_ids ) {
            return;
        }

        foreach ( $topic_ids as $topic_id ) {
            $topic = get_post( $topic_id );
            if ( ! $topic instanceof WP_Post || 'publish' !== $topic->post_status ) {
                continue;
            }

            $user_ids = $this->collect_recipient_ids( $topic, $reaction, $relation_topic_reaction );
            if ( ! $user_ids ) {
                continue;
            }

            $context = $this->build_context( $topic, $reaction );
            $subject = __( 'Nieuwe reactie op {{post_title}}', 'gem-mailer' );

            Email::send_to_users( $user_ids, $subject, $template, $context );
        }

        update_post_meta( $reaction->ID, Settings::META_REACTION_SENT, time() );
    }

    /**
     * Collect all unique recipient IDs for a topic, excluding the current author.
     *
     * @return int[]
     */
    private function collect_recipient_ids( WP_Post $topic, WP_Post $reaction, int $relation_id ): array {
        $recipients = [];

        if ( $topic->post_author ) {
            $recipients[] = (int) $topic->post_author;
        }

        $related_reactions = Relations::children( $relation_id, $topic->ID );

        foreach ( $related_reactions as $related_id ) {
            if ( (int) $related_id === $reaction->ID ) {
                continue;
            }

            $related_post = get_post( (int) $related_id );
            if ( ! $related_post instanceof WP_Post || 'publish' !== $related_post->post_status ) {
                continue;
            }

            if ( $related_post->post_author ) {
                $recipients[] = (int) $related_post->post_author;
            }
        }

        return Utils::filter_user_ids( $recipients, (int) $reaction->post_author );
    }

    /**
     * Prepare the placeholder context shared by all reaction recipients.
     *
     * @return array<string,string>
     */
    private function build_context( WP_Post $topic, WP_Post $reaction ): array {
        $reply_author  = get_the_author_meta( 'display_name', $reaction->post_author );
        $reply_link    = get_permalink( $reaction );
        $reply_excerpt = Utils::excerpt( $reaction->ID, 20 );

        return [
            'topic_title'      => get_the_title( $topic ),
            'topic_link'       => get_permalink( $topic ),
            'reaction_author'  => $reply_author,
            'reaction_link'    => $reply_link,
            'reaction_excerpt' => $reply_excerpt,
            'post_title'       => get_the_title( $topic ),
            'post_permalink'   => get_permalink( $topic ),
            'reply_author'     => $reply_author,
            'reply_excerpt'    => $reply_excerpt,
            'reply_link'       => $reply_link,
            'reply_permalink'  => $reply_link,
            'site_name'        => get_bloginfo( 'name' ),
            'site_url'         => home_url(),
        ];
    }
}
