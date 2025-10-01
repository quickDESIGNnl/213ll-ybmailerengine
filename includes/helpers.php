<?php
/**
 * Shared helper utilities for the GEM Mailer plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/constants.php';

/**
 * Retrieve a raw option value while providing a default fallback.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default value when the option is empty.
 *
 * @return mixed
 */
function gem_mailer_get_option( string $key, $default = '' ) {
    $value = get_option( $key, $default );

    if ( is_array( $value ) && isset( $value[0] ) ) {
        // JetEngine stores select values as arrays.
        return $value[0];
    }

    return $value;
}

/**
 * Determine whether the JetEngine relation table exists for the provided relation ID.
 */
function gem_mailer_relation_table( int $relation_id ): ?string {
    if ( ! $relation_id ) {
        return null;
    }

    global $wpdb;

    $table = $wpdb->prefix . 'jet_rel_' . $relation_id;

    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

    return $exists ? $table : null;
}

/**
 * Fetch all child IDs attached to the provided parent ID through a JetEngine relation.
 *
 * @return int[]
 */
function gem_mailer_relation_children( int $relation_id, int $parent_id ): array {
    $table = gem_mailer_relation_table( $relation_id );
    if ( ! $table ) {
        return [];
    }

    global $wpdb;

    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT child_object_id FROM {$table} WHERE parent_object_id = %d",
        $parent_id
    ) );

    return array_map( 'intval', $ids );
}

/**
 * Fetch all parent IDs for a given child object in a JetEngine relation.
 *
 * @return int[]
 */
function gem_mailer_relation_parents( int $relation_id, int $child_id ): array {
    $table = gem_mailer_relation_table( $relation_id );
    if ( ! $table ) {
        return [];
    }

    global $wpdb;

    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT parent_object_id FROM {$table} WHERE child_object_id = %d",
        $child_id
    ) );

    return array_map( 'intval', $ids );
}

/**
 * Retrieve all configured JetEngine relations to populate select fields.
 *
 * @return array<int,string>
 */
function gem_mailer_get_relation_choices(): array {
    static $cache = null;

    if ( null !== $cache ) {
        return $cache;
    }

    $choices = [];

    if ( function_exists( 'jet_engine' ) && method_exists( jet_engine()->relations, 'query' ) ) {
        $relations = jet_engine()->relations->query->get_relations();
        if ( is_array( $relations ) ) {
            foreach ( $relations as $relation ) {
                $id    = isset( $relation['id'] ) ? (int) $relation['id'] : 0;
                $label = isset( $relation['name'] ) ? $relation['name'] : ( $relation['slug'] ?? '' );
                if ( $id && $label ) {
                    $choices[ $id ] = sprintf( '%s (#%d)', $label, $id );
                }
            }
        }
    }

    if ( ! $choices ) {
        global $wpdb;

        $table = $wpdb->prefix . 'jet_relations';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists ) {
            $results = $wpdb->get_results( "SELECT id, name, slug FROM {$table} ORDER BY name" );
            foreach ( $results as $relation ) {
                $label = $relation->name ?: $relation->slug;
                $choices[ (int) $relation->id ] = sprintf( '%s (#%d)', $label, $relation->id );
            }
        }
    }

    ksort( $choices );

    $cache = $choices;

    return $choices;
}

/**
 * Filter a list of user IDs to remove duplicates, invalid IDs and optionally the author.
 *
 * @param int[] $user_ids
 * @param int   $exclude_user Optional user ID to exclude (e.g. the author).
 *
 * @return int[]
 */
function gem_mailer_filter_user_ids( array $user_ids, int $exclude_user = 0 ): array {
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
 * Prepare a plain-text excerpt for the provided post ID.
 */
function gem_mailer_prepare_excerpt( int $post_id, int $length = 40 ): string {
    $content = get_post_field( 'post_content', $post_id );
    $content = wp_strip_all_tags( $content );

    return wp_trim_words( $content, $length, '…' );
}

/**
 * Render an email template with the supplied placeholder context.
 */
function gem_mailer_render_template( string $template, array $context ): string {
    $replacements = [];
    foreach ( $context as $key => $value ) {
        $replacements[ '{{' . $key . '}}' ] = $value;
    }

    return strtr( $template, $replacements );
}

/**
 * Send a templated email to a list of recipients.
 *
 * @param int[]  $user_ids Array of user IDs.
 * @param string $subject  Subject line.
 * @param string $template HTML template with placeholders.
 * @param array  $context  Placeholder context.
 */
function gem_mailer_send_template_to_users( array $user_ids, string $subject, string $template, array $context ): void {
    foreach ( $user_ids as $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user || ! is_email( $user->user_email ) ) {
            continue;
        }

        $message = gem_mailer_render_template(
            $template,
            array_merge(
                $context,
                [ 'recipient_name' => $user->display_name ]
            )
        );

        wp_mail(
            $user->user_email,
            $subject,
            $message,
            [ 'Content-Type: text/html; charset=UTF-8' ]
        );
    }
}

/**
 * Resolve a title for a taxonomy term or post ID.
 */
function gem_mailer_resolve_entity_title( int $entity_id, string $taxonomy = '' ): string {
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
 * Resolve a permalink for a taxonomy term or post ID.
 */
function gem_mailer_resolve_entity_link( int $entity_id, string $taxonomy = '' ): string {
    if ( $taxonomy ) {
        $term_link = get_term_link( (int) $entity_id, $taxonomy );
        if ( ! is_wp_error( $term_link ) ) {
            return $term_link;
        }
    }

    $post_link = get_permalink( $entity_id );
    return $post_link ?: home_url();
}
