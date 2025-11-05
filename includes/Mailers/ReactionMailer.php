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
use function is_array;
use function is_numeric;
use function is_object;
use function method_exists;
use function property_exists;
use function time;
use function update_post_meta;

/**
 * Verstuurt meldingen voor nieuwe reacties binnen JetEngine forums.
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
        add_action( 'gem_jfb_notify_parent_author', [ $this, 'maybe_enqueue_from_form' ], 10, 10 );
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

    public function maybe_enqueue_from_form( ...$args ): void {
        $post_id = $this->resolve_post_id_from_args( $args );
        if ( ! $post_id ) {
            return;
        }

        $this->queue[ $post_id ] = $post_id;
    }

    public function process_queue(): void {
        if ( ! $this->queue ) {
            return;
        }

        $reaction_cpt = (string) Settings::get( Settings::OPT_REACTION_CPT, '' );

        foreach ( $this->queue as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
                continue;
            }

            if ( $reaction_cpt && $post->post_type !== $reaction_cpt ) {
                continue;
            }

            $this->notify_participants( $post );
        }

        $this->queue = [];
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

    /**
     * @param array<int,mixed> $args
     */
    private function resolve_post_id_from_args( array $args ): int {
        foreach ( $args as $arg ) {
            if ( is_numeric( $arg ) ) {
                return (int) $arg;
            }

            if ( is_array( $arg ) ) {
                $id = $this->post_id_from_array( $arg );
                if ( $id ) {
                    return $id;
                }
            }

            if ( is_object( $arg ) ) {
                $id = $this->post_id_from_object( $arg );
                if ( $id ) {
                    return $id;
                }
            }
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function post_id_from_array( array $data ): int {
        $keys = [ 'inserted_post_id', 'post_id', 'id', 'reaction_id' ];

        foreach ( $keys as $key ) {
            if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) {
                return (int) $data[ $key ];
            }
        }

        return 0;
    }

    private function post_id_from_object( object $data ): int {
        $keys = [ 'inserted_post_id', 'post_id', 'id', 'reaction_id' ];

        foreach ( $keys as $key ) {
            if ( property_exists( $data, $key ) && is_numeric( $data->{$key} ) ) {
                return (int) $data->{$key};
            }
        }

        if ( method_exists( $data, 'get_inserted_post_id' ) ) {
            $value = $data->get_inserted_post_id();
            if ( is_numeric( $value ) ) {
                return (int) $value;
            }
        }

        if ( method_exists( $data, 'get' ) ) {
            $value = $data->get( 'inserted_post_id' );
            if ( is_numeric( $value ) ) {
                return (int) $value;
            }
        }

        return 0;
    }
}
