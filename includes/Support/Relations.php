<?php
namespace GemMailer\Support;

use wpdb;

/**
 * Helper utilities around JetEngine relations.
 */
final class Relations {
    private const RELATION_STATUS = 'relation';

    /** @var array<int,string>|null */
    private static $choices_cache = null;

    private function __construct() {}

    /**
     * Retrieve a JetEngine relation table for the provided relation ID.
     */
    public static function table( int $relation_id ): ?string {
        if ( $relation_id <= 0 ) {
            return null;
        }

        $wpdb = self::db();
        if ( ! $wpdb ) {
            return null;
        }

        $table = $wpdb->prefix . 'jet_rel_' . $relation_id;

        return self::table_exists( $wpdb, $table ) ? $table : null;
    }

    /**
     * Fetch child object IDs for a given parent object.
     *
     * @return int[]
     */
    public static function children( int $relation_id, int $parent_id ): array {
        $table = self::table( $relation_id );
        if ( ! $table ) {
            return [];
        }

        $wpdb = self::db();
        if ( ! $wpdb ) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT child_object_id FROM {$table} WHERE parent_object_id = %d",
            $parent_id
        );

        if ( ! $sql ) {
            return [];
        }

        $ids = $wpdb->get_col( $sql );
        if ( ! is_array( $ids ) ) {
            return [];
        }

        return array_map( 'intval', $ids );
    }

    /**
     * Fetch parent object IDs for a child object.
     *
     * @return int[]
     */
    public static function parents( int $relation_id, int $child_id ): array {
        $table = self::table( $relation_id );
        if ( ! $table ) {
            return [];
        }

        $wpdb = self::db();
        if ( ! $wpdb ) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT parent_object_id FROM {$table} WHERE child_object_id = %d",
            $child_id
        );

        if ( ! $sql ) {
            return [];
        }

        $ids = $wpdb->get_col( $sql );
        if ( ! is_array( $ids ) ) {
            return [];
        }

        return array_map( 'intval', $ids );
    }

    /**
     * Retrieve all configured JetEngine relations for select inputs.
     *
     * @return array<int,string>
     */
    public static function choices(): array {
        if ( null !== self::$choices_cache ) {
            return self::$choices_cache;
        }

        $choices = self::collect_from_database();

        foreach ( self::collect_from_runtime() as $relation_id => $label ) {
            if ( ! isset( $choices[ $relation_id ] ) ) {
                $choices[ $relation_id ] = $label;
            }
        }

        if ( $choices ) {
            uasort( $choices, static function ( string $a, string $b ): int {
                return strnatcasecmp( $a, $b );
            } );
        }

        return self::$choices_cache = $choices;
    }

    /**
     * Retrieve relations from the JetEngine runtime, when available.
     *
     * @return array<int,string>
     */
    private static function collect_from_runtime(): array {
        if ( ! function_exists( 'jet_engine' ) ) {
            return [];
        }

        $engine = jet_engine();
        if ( ! $engine || ! isset( $engine->relations ) || ! is_object( $engine->relations ) ) {
            return [];
        }

        $sources = [];
        foreach ( [ 'manager', 'query' ] as $property ) {
            if ( isset( $engine->relations->{$property} )
                && is_object( $engine->relations->{$property} )
                && method_exists( $engine->relations->{$property}, 'get_relations' )
            ) {
                $sources[] = $engine->relations->{$property};
            }
        }

        if ( ! $sources ) {
            return [];
        }

        $choices = [];

        foreach ( $sources as $source ) {
            $relations = $source->get_relations();
            if ( ! is_array( $relations ) ) {
                continue;
            }

            foreach ( $relations as $relation ) {
                if ( is_object( $relation ) ) {
                    $relation = (array) $relation;
                }

                if ( ! is_array( $relation ) ) {
                    continue;
                }

                $args        = self::to_array( $relation['args'] ?? [] );
                $relation_id = self::extract_relation_id( $relation, $args );
                if ( $relation_id <= 0 ) {
                    continue;
                }

                $label = self::build_label( $relation, $args );

                if ( '' === $label && isset( $relation['name'] ) ) {
                    $label = self::normalise_label_candidate( $relation['name'] );
                }

                if ( '' === $label && isset( $relation['slug'] ) ) {
                    $label = self::normalise_label_candidate( $relation['slug'] );
                }

                if ( '' === $label ) {
                    $label = sprintf( 'Relatie #%d', $relation_id );
                }

                $choices[ $relation_id ] = self::format_choice( $label, $relation_id );
            }
        }

        return $choices;
    }

    /**
     * Retrieve relations from JetEngine's database tables.
     *
     * @return array<int,string>
     */
    private static function collect_from_database(): array {
        $wpdb = self::db();
        if ( ! $wpdb ) {
            return [];
        }

        $table = $wpdb->prefix . 'jet_post_types';
        if ( ! self::table_exists( $wpdb, $table ) ) {
            return [];
        }

        $sql = $wpdb->prepare( "SELECT id, name, slug, label, labels, args FROM {$table} WHERE status = %s", self::RELATION_STATUS );
        if ( ! $sql ) {
            return [];
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        $choices = [];

        foreach ( $rows as $row ) {
            $args        = self::to_array( self::decode_payload( $row['args'] ?? '' ) );
            $relation_id = self::extract_relation_id( $row, $args );

            if ( $relation_id <= 0 ) {
                continue;
            }

            $label = self::build_label( $row, $args );
            if ( '' === $label ) {
                $label = sprintf( 'Relatie #%d', $relation_id );
            }

            $choices[ $relation_id ] = self::format_choice( $label, $relation_id );
        }

        return $choices;
    }

    /**
     * Extract a human-readable label for a relation.
     *
     * @param array<string,mixed> $relation Raw relation row/definition.
     * @param array<string,mixed> $args     Decoded JetEngine args payload.
     */
    private static function build_label( array $relation, array $args ): string {
        $candidates = [];

        foreach ( [ 'label', 'labels' ] as $column ) {
            if ( isset( $relation[ $column ] ) ) {
                $candidates[] = self::normalise_label_candidate( self::decode_payload( $relation[ $column ] ) );
            }
        }

        foreach ( [ 'name', 'slug' ] as $column ) {
            if ( isset( $relation[ $column ] ) ) {
                $candidates[] = self::normalise_label_candidate( $relation[ $column ] );
            }
        }

        foreach ( [ 'label', 'name', 'singular_name', 'plural_name', 'title' ] as $key ) {
            if ( isset( $args[ $key ] ) ) {
                $candidates[] = self::normalise_label_candidate( $args[ $key ] );
            }
        }

        $parent = '';
        if ( isset( $args['parent_label'] ) ) {
            $parent = self::normalise_label_candidate( $args['parent_label'] );
        }
        if ( '' === $parent && isset( $args['parent_object'] ) ) {
            $parent = self::describe_object( $args['parent_object'], $args, 'parent' );
        }

        $child = '';
        if ( isset( $args['child_label'] ) ) {
            $child = self::normalise_label_candidate( $args['child_label'] );
        }
        if ( '' === $child && isset( $args['child_object'] ) ) {
            $child = self::describe_object( $args['child_object'], $args, 'child' );
        }

        if ( '' !== $parent && '' !== $child ) {
            $candidates[] = sprintf( '%s ↔ %s', $parent, $child );
        } elseif ( '' !== $parent ) {
            $candidates[] = $parent;
        } elseif ( '' !== $child ) {
            $candidates[] = $child;
        }

        foreach ( $candidates as $candidate ) {
            $candidate = self::normalise_label_candidate( $candidate );
            if ( '' !== $candidate ) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Convert a JetEngine object reference into a human-readable label.
     *
     * @param array<string,mixed> $args  Decoded JetEngine args payload.
     */
    private static function describe_object( string $object, array $args, string $role ): string {
        $object = trim( $object );
        if ( '' === $object ) {
            return '';
        }

        $type = '';
        $slug = $object;

        if ( false !== strpos( $object, '::' ) ) {
            list( $type, $slug ) = array_pad( explode( '::', $object, 2 ), 2, '' );
        }

        $type = strtolower( trim( $type ) );
        $slug = trim( $slug );

        $from_args = sprintf( '%s_object_label', $role );
        if ( isset( $args[ $from_args ] ) ) {
            $label = self::normalise_label_candidate( $args[ $from_args ] );
            if ( '' !== $label ) {
                return $label;
            }
        }

        if ( isset( $args['objects'] ) && is_array( $args['objects'] ) && isset( $args['objects'][ $object ] ) ) {
            $label = self::normalise_label_candidate( $args['objects'][ $object ] );
            if ( '' !== $label ) {
                return $label;
            }
        }

        switch ( $type ) {
            case 'posts':
            case 'post':
            case 'post_type':
                if ( function_exists( 'get_post_type_object' ) && '' !== $slug ) {
                    $post_type = get_post_type_object( $slug );
                    if ( $post_type ) {
                        if ( isset( $post_type->labels->singular_name ) && $post_type->labels->singular_name ) {
                            return (string) $post_type->labels->singular_name;
                        }
                        if ( isset( $post_type->label ) && $post_type->label ) {
                            return (string) $post_type->label;
                        }
                    }
                }
                break;
            case 'terms':
            case 'tax':
            case 'taxonomy':
                if ( function_exists( 'get_taxonomy' ) && '' !== $slug ) {
                    $taxonomy = get_taxonomy( $slug );
                    if ( $taxonomy ) {
                        if ( isset( $taxonomy->labels->singular_name ) && $taxonomy->labels->singular_name ) {
                            return (string) $taxonomy->labels->singular_name;
                        }
                        if ( isset( $taxonomy->label ) && $taxonomy->label ) {
                            return (string) $taxonomy->label;
                        }
                    }
                }
                break;
            case 'users':
            case 'user':
                if ( function_exists( '__' ) ) {
                    return __( 'Gebruiker', 'gem-mailer' );
                }

                return 'Gebruiker';
        }

        if ( '' === $slug ) {
            $slug = $object;
        }

        return self::humanize_slug( $slug );
    }

    /**
     * Determine the relation ID from a row/definition.
     *
     * @param array<string,mixed> $relation Raw relation row/definition.
     * @param array<string,mixed> $args     Decoded JetEngine args payload.
     */
    private static function extract_relation_id( array $relation, array $args ): int {
        $candidates = [];

        foreach ( [
            'relation_id',
            'relationId',
            'id',
            'rel_id',
            'relId',
            'parent_rel',
            'child_rel',
            'parent_rel_id',
            'child_rel_id',
        ] as $key ) {
            if ( isset( $args[ $key ] ) && self::is_numeric_like( $args[ $key ] ) ) {
                $candidates[] = (int) $args[ $key ];
            }
        }

        if ( isset( $relation['id'] ) && self::is_numeric_like( $relation['id'] ) ) {
            $candidates[] = (int) $relation['id'];
        }

        if ( isset( $relation['slug'] ) && is_string( $relation['slug'] ) && preg_match( '/(\d+)/', $relation['slug'], $match ) ) {
            $candidates[] = (int) $match[1];
        }

        foreach ( $candidates as $candidate ) {
            if ( $candidate > 0 ) {
                return $candidate;
            }
        }

        return 0;
    }

    /**
     * Determine whether the provided table exists in the database.
     */
    private static function table_exists( wpdb $wpdb, string $table ): bool {
        if ( '' === $table ) {
            return false;
        }

        $pattern = $table;
        if ( method_exists( $wpdb, 'esc_like' ) ) {
            $pattern = $wpdb->esc_like( $table );
        }

        $sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern );
        if ( ! $sql ) {
            return false;
        }

        return (bool) $wpdb->get_var( $sql );
    }

    /**
     * Decode JetEngine payloads stored as serialized PHP or JSON.
     *
     * @param mixed $value Raw payload.
     *
     * @return mixed
     */
    private static function decode_payload( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return $value;
        }

        if ( ! is_string( $value ) ) {
            return $value;
        }

        $raw = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;
        $raw = trim( $raw );

        if ( '' === $raw ) {
            return '';
        }

        $unserialized = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $raw ) : @unserialize( $raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

        if ( false !== $unserialized || 'b:0;' === $raw ) {
            return $unserialized;
        }

        $decoded = json_decode( $raw, true );
        if ( JSON_ERROR_NONE === json_last_error() ) {
            return $decoded;
        }

        return $raw;
    }

    /**
     * Normalise a label-like value to a trimmed string.
     *
     * @param mixed $value Label candidate.
     */
    private static function normalise_label_candidate( $value ): string {
        if ( is_array( $value ) ) {
            foreach ( [ 'name', 'label', 'singular_name', 'plural_name', 'title' ] as $key ) {
                if ( isset( $value[ $key ] ) ) {
                    $candidate = self::normalise_label_candidate( $value[ $key ] );
                    if ( '' !== $candidate ) {
                        return $candidate;
                    }
                }
            }

            $first = reset( $value );
            if ( false !== $first ) {
                return self::normalise_label_candidate( $first );
            }

            return '';
        }

        if ( is_object( $value ) ) {
            return self::normalise_label_candidate( (array) $value );
        }

        if ( is_string( $value ) ) {
            return trim( $value );
        }

        if ( is_scalar( $value ) ) {
            return trim( (string) $value );
        }

        return '';
    }

    /**
     * Convert a slug-like value to a readable string.
     */
    private static function humanize_slug( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        $value = preg_replace( '/[_\-]+/', ' ', $value );
        $value = preg_replace( '/\s+/', ' ', $value );

        return ucwords( strtolower( $value ) );
    }

    /**
     * Determine whether a value is numeric-like.
     *
     * @param mixed $value The value to test.
     */
    private static function is_numeric_like( $value ): bool {
        if ( is_int( $value ) || is_float( $value ) ) {
            return true;
        }

        if ( is_string( $value ) ) {
            $value = trim( $value );

            return '' !== $value && is_numeric( $value );
        }

        return false;
    }

    /**
     * Convert mixed input into an array.
     *
     * @param mixed $value Value to normalise.
     *
     * @return array<string,mixed>
     */
    private static function to_array( $value ): array {
        if ( is_array( $value ) ) {
            return $value;
        }

        if ( is_object( $value ) ) {
            return (array) $value;
        }

        return [];
    }

    /**
     * Format a select option label.
     */
    private static function format_choice( string $label, int $id ): string {
        return sprintf( '%s (#%d)', $label, $id );
    }

    /**
     * Retrieve the global wpdb instance when available.
     */
    private static function db(): ?wpdb {
        if ( isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'] instanceof wpdb ) {
            return $GLOBALS['wpdb'];
        }

        return null;
    }
}
