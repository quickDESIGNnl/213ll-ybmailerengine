<?php
namespace GemMailer\Support;

/**
 * Lightweight helper around JetEngine relations.
 */
final class Relations {
    private const STATUS_RELATION = 'relation';

    /** @var array<int, string>|null */
    private static $choices_cache = null;

    /** @var array<string, bool> */
    private static array $table_cache = [];

    private function __construct() {}

    /**
     * Fetch the relation table name if it exists.
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
     * Retrieve all children for a given parent object within a relation.
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

        $rows = $wpdb->get_col( $sql );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_map( 'intval', $rows );
    }

    /**
     * Retrieve all parents for a given child object within a relation.
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

        $rows = $wpdb->get_col( $sql );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_map( 'intval', $rows );
    }

    /**
     * Retrieve relation options from the JetEngine post types table.
     *
     * @return array<int, string>
     */
    public static function choices(): array {
        if ( null !== self::$choices_cache ) {
            return self::$choices_cache;
        }

        $choices = [];
        foreach ( self::fetch_database_relations() as $relation ) {
            $id    = isset( $relation['id'] ) ? (int) $relation['id'] : ( isset( $relation['ID'] ) ? (int) $relation['ID'] : 0 );
            $label = self::resolve_label( $relation );

            if ( $id > 0 && '' !== $label ) {
                $choices[ $id ] = $label;
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
     * Query JetEngine relations from the database.
     *
     * @return list<array<string, mixed>>
     */
    private static function fetch_database_relations(): array {
        $wpdb = self::db();
        if ( ! $wpdb ) {
            return [];
        }

        $table = $wpdb->prefix . 'jet_post_types';
        if ( ! self::table_exists( $wpdb, $table ) ) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT id, label, args FROM {$table} WHERE status = %s",
            self::STATUS_RELATION
        );

        if ( ! $sql ) {
            return [];
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Determine the human readable label for a relation row.
     *
     * @param array<string, mixed> $relation
     */
    private static function resolve_label( array $relation ): string {
        $label = '';

        if ( isset( $relation['label'] ) ) {
            $label = self::extract_name_field( $relation['label'] );
        }

        if ( '' !== $label ) {
            return $label;
        }

        if ( isset( $relation['args'] ) ) {
            $args = self::maybe_decode( $relation['args'] );

            if ( is_array( $args ) ) {
                if ( isset( $args['name'] ) && is_string( $args['name'] ) && '' !== trim( $args['name'] ) ) {
                    return trim( self::unslash( $args['name'] ) );
                }

                $parent = '';
                $child  = '';

                if ( isset( $args['parent_title'] ) && is_string( $args['parent_title'] ) ) {
                    $parent = trim( self::unslash( $args['parent_title'] ) );
                } elseif ( isset( $args['parent_object'] ) && is_string( $args['parent_object'] ) ) {
                    $parent = self::humanize_object( $args['parent_object'] );
                }

                if ( isset( $args['child_title'] ) && is_string( $args['child_title'] ) ) {
                    $child = trim( self::unslash( $args['child_title'] ) );
                } elseif ( isset( $args['child_object'] ) && is_string( $args['child_object'] ) ) {
                    $child = self::humanize_object( $args['child_object'] );
                }

                if ( '' !== $parent && '' !== $child ) {
                    return sprintf( 'Relatie: %1$s ↔ %2$s', $parent, $child );
                }
            }
        }

        return '';
    }

    /**
     * Extract the `name` property from a label payload.
     */
    private static function extract_name_field( $label ): string {
        if ( is_string( $label ) ) {
            $decoded = self::maybe_decode( $label );

            if ( is_array( $decoded ) ) {
                if ( isset( $decoded['name'] ) && is_string( $decoded['name'] ) ) {
                    $label = $decoded['name'];
                } elseif ( isset( $decoded['label'] ) && is_string( $decoded['label'] ) ) {
                    $label = $decoded['label'];
                }
            }
        }

        if ( is_string( $label ) ) {
            $label = trim( self::unslash( $label ) );
        }

        return is_string( $label ) ? $label : '';
    }

    /**
     * Attempt to decode JetEngine payloads (serialized or JSON).
     *
     * @param mixed $value
     * @return mixed
     */
    private static function maybe_decode( $value ) {
        if ( is_string( $value ) ) {
            $value = self::unslash( $value );

            $json = json_decode( $value, true );
            if ( is_array( $json ) ) {
                return $json;
            }

            $unserialized = self::maybe_unserialize( $value );
            if ( is_array( $unserialized ) ) {
                return $unserialized;
            }
        }

        return $value;
    }

    /**
     * Remove slashes from a value if present.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function unslash( $value ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        if ( function_exists( 'wp_unslash' ) ) {
            return wp_unslash( $value );
        }

        return stripslashes( $value );
    }

    /**
     * Human readable representation of a JetEngine object slug.
     */
    private static function humanize_object( string $object ): string {
        $object = trim( $object );
        if ( '' === $object ) {
            return '';
        }

        $parts = explode( '::', $object );
        $label = end( $parts );
        if ( false === $label ) {
            $label = $object;
        }

        $label = str_replace( [ '-', '_' ], ' ', $label );
        $label = ucwords( strtolower( $label ) );

        return $label;
    }

    /**
     * Lightweight reimplementation of maybe_unserialize for environments
     * where WordPress core helpers may not yet be loaded.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function maybe_unserialize( $value ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        if ( function_exists( 'maybe_unserialize' ) ) {
            return maybe_unserialize( $value );
        }

        $trimmed = trim( $value );
        if ( '' === $trimmed ) {
            return $value;
        }

        if ( ! self::looks_serialized( $trimmed ) ) {
            return $value;
        }

        $result = @unserialize( $trimmed, [ 'allowed_classes' => false ] );

        if ( false === $result && 'b:0;' !== $trimmed ) {
            return $value;
        }

        return $result;
    }

    /**
     * Basic detection for serialized strings.
     */
    private static function looks_serialized( string $value ): bool {
        if ( '' === $value ) {
            return false;
        }

        if ( 'N;' === $value ) {
            return true;
        }

        if ( ! preg_match( '/^[adObis]:/', $value ) ) {
            return false;
        }

        return false !== @unserialize( $value, [ 'allowed_classes' => false ] );
    }

    /**
     * Retrieve the global wpdb instance when available.
     */
    private static function db() {
        global $wpdb;

        if ( isset( $wpdb ) && is_object( $wpdb ) ) {
            return $wpdb;
        }

        return null;
    }

    /**
     * Determine whether a database table exists.
     */
    private static function table_exists( $wpdb, string $table ): bool {
        if ( isset( self::$table_cache[ $table ] ) ) {
            return self::$table_cache[ $table ];
        }

        $like = $table;
        if ( function_exists( 'esc_sql' ) ) {
            $like = esc_sql( $like );
        }

        $sql = $wpdb->prepare( 'SHOW TABLES LIKE %s', $like );
        if ( ! $sql ) {
            return self::$table_cache[ $table ] = false;
        }

        $found = $wpdb->get_var( $sql );

        return self::$table_cache[ $table ] = ( $found === $table );
    }
}
