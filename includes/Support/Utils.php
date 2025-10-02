<?php
namespace GemMailer\Support;

/**
 * Shared helper utilities.
 */
final class Utils {
    private function __construct() {}

    /**
     * Filter out invalid or duplicate user IDs and optionally the author.
     *
     * @param int[] $user_ids
     */
    public static function filter_user_ids( array $user_ids, int $exclude_user = 0 ): array {
        $user_ids = array_unique( array_map( 'intval', $user_ids ) );

        if ( $exclude_user ) {
            $user_ids = array_filter(
                $user_ids,
                static fn( int $id ): bool => $id && $id !== $exclude_user
            );
        }

        return array_values( $user_ids );
    }

    /**
     * Prepare a trimmed plain-text excerpt for a post.
     */
    public static function excerpt( int $post_id, int $length = 40 ): string {
        $content = get_post_field( 'post_content', $post_id );
        $content = wp_strip_all_tags( $content );

        return wp_trim_words( $content, $length, '…' );
    }

    /**
     * Resolve a readable title for either a taxonomy term or a post.
     */
    public static function resolve_title( int $entity_id, string $taxonomy = '' ): string {
        if ( $taxonomy ) {
            $term = get_term( $entity_id, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) {
                return $term->name;
            }
        }

        $post = get_post( $entity_id );
        if ( $post ) {
            return get_the_title( $post );
        }

        return '';
    }

    /**
     * Resolve a permalink for either a term or a post.
     */
    public static function resolve_link( int $entity_id, string $taxonomy = '' ): string {
        if ( $taxonomy ) {
            $term_link = get_term_link( (int) $entity_id, $taxonomy );
            if ( ! is_wp_error( $term_link ) ) {
                return $term_link;
            }
        }

        $post_link = get_permalink( $entity_id );

        return $post_link ?: home_url();
    }
}
