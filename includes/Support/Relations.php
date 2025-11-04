<?php
namespace GemMailer\Support;

/**
 * Helper utilities around JetEngine relations.
 */
final class Relations {
    private const STATUS_RELATION = 'relation';

    /** @var array<int,string>|null */
    private static $choices_cache = null;

    /** @var array<string,bool> */
    private static array $table_cache = [];

    private function __construct() {}

    /**
     * Retrieve the JetEngine relation table name for a relation ID.
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
     * Fetch parent object IDs for a given child object.
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

        $choices = self::relations_from_database();

        if ( ! $choices ) {
            $choices = self::relations_from_runtime();
        } else {
            foreach ( self::relations_from_runtime() as $id => $label ) {
                if ( ! isset( $choices[ $id ] ) ) {
                    $choices[ $id ] = $label;
                }
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
     * Gather relations from JetEngine's registry (runtime).
     *
     * @return array<int,string>
     */
    private static function relations_from_runtime(): array {
        if ( ! function_exists( 'jet_engine' ) ) {
            return [];
        }

        $engine = jet_engine();
        if ( ! $engine || ! isset( $engine->relations ) || ! is_object( $engine->relations ) ) {
            return [];
        }

        $providers = [];
        foreach ( [ 'manager', 'query' ] as $property ) {
            if ( isset( $engine->relations->{$property} )
                && is_object( $engine->relations->{$property} )
                && method_exists( $engine->relations->{$property}, 'get_relations' )
            ) {
                $providers[] = $engine->relations->{$property};
            }
        }

        if ( ! $providers ) {
            return [];
        }

        $choices = [];

        foreach ( $providers as $provider ) {
            $relations = $provider->get_relations();
            if ( ! is_array( $relations ) ) {
                continue;
            }

            foreach ( $relations as $relation ) {
                $relation = self::to_array( $relation );

                if ( ! $relation ) {
                    continue;
                }

                $args        = self::to_array( self::decode_payload( $relation['args'] ?? null ) );
                $relation_id = self::detect_relation_id( $relation, $args );

                if ( $relation_id <= 0 ) {
                    continue;
                }

                $label = self::derive_label( $relation, $args, $relation_id );

                $choices[ $relation_id ] = $label;
            }
        }

        return $choices;
    }

    /**
     * Gather relations from the JetEngine database table.
     *
     * @return array<int,string>
     */
    private static function relations_from_database(): array {
        $wpdb = self::db();
        if ( ! $wpdb ) {
            return [];
        }

        $table = $wpdb->prefix . 'jet_post_types';
        if ( ! self::table_exists( $wpdb, $table ) ) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT id, name, slug, label, labels, args FROM {$table} WHERE status = %s",
            self::STATUS_RELATION
        );

        if ( ! $sql ) {
            return [];
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $rows ) || ! $rows ) {
            return [];
        }

        $choices = [];

        foreach ( $rows as $row ) {
            $args = self::to_array( self::decode_payload( $row['args'] ?? null ) );

            $relation_id = self::detect_relation_id( $row, $args );
            if ( $relation_id <= 0 ) {
                continue;
            }

            $choices[ $relation_id ] = self::derive_label( $row, $args, $relation_id );
        }

        return $choices;
    }

    /**
     * Build a label for a relation using table columns and args.
     *
     * @param array<string,mixed> $relation Raw relation row/definition.
     * @param array<string,mixed> $args     Decoded JetEngine args payload.
     */
    private static function derive_label( array $relation, array $args, int $relation_id ): string {
        $candidates = [];

        foreach ( [ 'label', 'labels' ] as $column ) {
            if ( isset( $relation[ $column ] ) ) {
                $candidates[] = self::normalise_label( self::decode_payload( $relation[ $column ] ) );
            }
        }

        foreach ( [ 'name', 'slug' ] as $column ) {
            if ( isset( $relation[ $column ] ) ) {
                $candidates[] = self::normalise_label( $relation[ $column ] );
            }
        }

        foreach ( [ 'label', 'name', 'singular_name', 'plural_name', 'title' ] as $key ) {
            if ( isset( $args[ $key ] ) ) {
                $candidates[] = self::normalise_label( $args[ $key ] );
            }
        }

        $parent = self::resolve_object_label( $args, 'parent' );
        $child  = self::resolve_object_label( $args, 'child' );

        if ( '' !== $parent && '' !== $child ) {
            $candidates[] = sprintf( '%s ↔ %s', $parent, $child );
        } elseif ( '' !== $parent ) {
            $candidates[] = $parent;
        } elseif ( '' !== $child ) {
            $candidates[] = $child;
        }

        foreach ( $candidates as $candidate ) {
            $candidate = self::normalise_label( $candidate );
            if ( '' !== $candidate ) {
                return self::format_choice( $candidate, $relation_id );
            }
        }

        return self::format_choice( sprintf( 'Relatie #%d', $relation_id ), $relation_id );
    }

    /**
     * Resolve an object label from the args array.
     *
     * @param array<string,mixed> $args
     */
    private static function resolve_object_label( array $args, string $role ): string {
        $label_key = $role . '_label';
        if ( isset( $args[ $label_key ] ) ) {
            $label = self::normalise_label( $args[ $label_key ] );
            if ( '' !== $label ) {
                return $label;
            }
        }

        $object_key = $role . '_object';
        if ( isset( $args[ $object_key ] ) && is_string( $args[ $object_key ] ) ) {
            $object = trim( $args[ $object_key ] );
            if ( '' !== $object ) {
                $objects = isset( $args['objects'] ) && is_array( $args['objects'] ) ? $args['objects'] : [];

                if ( isset( $objects[ $object ] ) ) {
                    $label = self::normalise_label( $objects[ $object ] );
                    if ( '' !== $label ) {
                        return $label;
                    }
                }

                $label = self::describe_object( $object );
                if ( '' !== $label ) {
                    return $label;
                }
            }
        }

        return '';
    }

    /**
     * Describe a JetEngine object reference.
     */
    private static function describe_object( string $object ): string {
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

        switch ( $type ) {
            case 'posts':
            case 'post':
            case 'post_type':
                if ( '' !== $slug && function_exists( 'get_post_type_object' ) ) {
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
                if ( '' !== $slug && function_exists( 'get_taxonomy' ) ) {
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
     * Determine the relation ID from a relation row/definition.
     *
     * @param array<string,mixed> $relation
     * @param array<string,mixed> $args
     */
    private static function detect_relation_id( array $relation, array $args ): int {
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
     * Check if a table exists in the database.
     */
    private static function table_exists( $wpdb, string $table ): bool {
        if ( ! $wpdb || '' === $table ) {
            return false;
        }

        if ( isset( self::$table_cache[ $table ] ) ) {
            return self::$table_cache[ $table ];
        }

        $pattern = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : $table;
        $pattern = '%' . $pattern . '%';

        $sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern );
        if ( ! $sql ) {
            self::$table_cache[ $table ] = false;

            return false;
        }

        $exists = (bool) $wpdb->get_var( $sql );
        self::$table_cache[ $table ] = $exists;

        return $exists;
    }

    /**
     * Decode serialized/JSON payloads stored by JetEngine.
     *
     * @param mixed $value
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
     * Normalise a label candidate into a trimmed string.
     *
     * @param mixed $value
     */
    private static function normalise_label( $value ): string {
        if ( is_array( $value ) ) {
            foreach ( [ 'name', 'label', 'singular_name', 'plural_name', 'title' ] as $key ) {
                if ( isset( $value[ $key ] ) ) {
                    $candidate = self::normalise_label( $value[ $key ] );
                    if ( '' !== $candidate ) {
                        return $candidate;
                    }
                }
            }

            $first = reset( $value );
            if ( false !== $first ) {
                return self::normalise_label( $first );
            }

            return '';
        }

        if ( is_object( $value ) ) {
            return self::normalise_label( (array) $value );
        }

        if ( is_string( $value ) ) {
            $value = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;

            return trim( $value );
        }

        if ( is_scalar( $value ) ) {
            return trim( (string) $value );
        }

        return '';
    }

    /**
     * Convert a slug to a human-readable string.
     */
    private static function humanize_slug( string $value ): string {
        $value = preg_replace( '/[_\-]+/', ' ', trim( $value ) );
        $value = preg_replace( '/\s+/', ' ', (string) $value );

        return ucwords( strtolower( $value ) );
    }

    /**
     * Determine if a value behaves like a number.
     *
     * @param mixed $value
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
     * Force any value into an array representation.
     *
     * @param mixed $value
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
     * Format the select label consistently.
     */
    private static function format_choice( string $label, int $relation_id ): string {
        return sprintf( '%s (#%d)', $label, $relation_id );
    }

    /**
     * Access the global wpdb instance when available.
     */
    private static function db() {
        if ( isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'] instanceof \wpdb ) {
            return $GLOBALS['wpdb'];
        }

        return null;
    }
}
