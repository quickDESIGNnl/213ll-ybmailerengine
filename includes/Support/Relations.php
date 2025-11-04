<?php
namespace GemMailer\Support;

use wpdb;

/**
 * Helper utilities around JetEngine relations.
 */
final class Relations {
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

        return array_map( 'intval', $ids );
    }

    /**
     * Retrieve all configured JetEngine relations for select inputs.
     *
     * @return array<int,string>
     */
    public static function choices(): array {
        static $cache = null;

        if ( null !== $cache ) {
            return $cache;
        }

        $choices = [];

        foreach ( self::runtime_relations() as $relation ) {
            $choices[ $relation['id'] ] = self::format_choice( $relation['label'], $relation['id'] );
        }

        foreach ( self::stored_relations() as $relation ) {
            $choices[ $relation['id'] ] = self::format_choice( $relation['label'], $relation['id'] );
        }

        ksort( $choices );

        return $cache = $choices;
    }

    /**
     * Normalise relations registered at runtime by JetEngine.
     *
     * @return array<int,array{id:int,label:string}>
     */
    private static function runtime_relations(): array {
        if ( ! function_exists( 'jet_engine' ) ) {
            return [];
        }

        try {
            $engine = jet_engine();
        } catch ( \Throwable $exception ) {
            return [];
        }

        if ( ! $engine || ! isset( $engine->relations ) || ! is_object( $engine->relations ) ) {
            return [];
        }

        $sources = [];
        foreach ( [ 'manager', 'query', 'repository' ] as $key ) {
            if ( isset( $engine->relations->{$key} ) && is_object( $engine->relations->{$key} ) ) {
                $sources[] = $engine->relations->{$key};
            }
        }

        if ( empty( $sources ) ) {
            $sources[] = $engine->relations;
        }

        $relations = [];

        foreach ( $sources as $source ) {
            if ( ! method_exists( $source, 'get_relations' ) ) {
                continue;
            }

            try {
                $raw = $source->get_relations();
            } catch ( \Throwable $exception ) {
                continue;
            }

            if ( empty( $raw ) || ! is_iterable( $raw ) ) {
                continue;
            }

            foreach ( $raw as $relation ) {
                $normalised = self::normalise_runtime_relation( $relation );
                if ( $normalised ) {
                    $relations[ $normalised['id'] ] = $normalised;
                }
            }

            if ( $relations ) {
                break;
            }
        }

        return array_values( $relations );
    }

    /**
     * Normalise relation definitions stored inside jet_post_types.
     *
     * @return array<int,array{id:int,label:string}>
     */
    private static function stored_relations(): array {
        $wpdb = self::db();
        if ( ! $wpdb ) {
            return [];
        }

        $table = $wpdb->prefix . 'jet_post_types';
        if ( ! self::table_exists( $wpdb, $table ) ) {
            return [];
        }

        $results = $wpdb->get_results( "SELECT id, name, slug, labels, args FROM {$table} WHERE status = 'relation'" );
        if ( empty( $results ) ) {
            return [];
        }

        $relations = [];

        foreach ( $results as $relation ) {
            $parsed = self::parse_relation_row( $relation );
            if ( $parsed ) {
                $relations[ $parsed['id'] ] = $parsed;
            }
        }

        return array_values( $relations );
    }

    /**
     * Convert a raw runtime relation into a consistent shape.
     *
     * @param mixed $relation Relation entry from JetEngine runtime.
     *
     * @return array{id:int,label:string}|null
     */
    private static function normalise_runtime_relation( $relation ): ?array {
        if ( is_array( $relation ) ) {
            $id    = isset( $relation['id'] ) ? (int) $relation['id'] : 0;
            $label = isset( $relation['name'] ) ? (string) $relation['name'] : (string) ( $relation['slug'] ?? '' );
        } elseif ( is_object( $relation ) ) {
            $id    = isset( $relation->id ) ? (int) $relation->id : 0;
            $label = isset( $relation->name ) ? (string) $relation->name : (string) ( $relation->slug ?? '' );
        } else {
            return null;
        }

        $label = trim( $label );

        if ( $id <= 0 || '' === $label ) {
            return null;
        }

        return [
            'id'    => $id,
            'label' => $label,
        ];
    }

    /**
     * Parse a single row from jet_post_types that represents a relation.
     *
     * @param object $relation
     *
     * @return array{id:int,label:string}|null
     */
    private static function parse_relation_row( $relation ): ?array {
        if ( ! isset( $relation->id ) ) {
            return null;
        }

        $args = self::maybe_decode( $relation->args ?? '' );

        $relation_id = 0;
        if ( is_array( $args ) ) {
            foreach ( [ 'relation_id', 'parent_rel', 'child_rel' ] as $key ) {
                if ( isset( $args[ $key ] ) && is_numeric( $args[ $key ] ) ) {
                    $relation_id = (int) $args[ $key ];
                    break;
                }
            }
        }

        if ( ! $relation_id ) {
            $relation_id = (int) $relation->id;
        }

        if ( $relation_id <= 0 ) {
            return null;
        }

        $label = '';

        if ( isset( $relation->name ) && is_string( $relation->name ) ) {
            $label = $relation->name;
        }

        if ( '' === $label && isset( $relation->slug ) ) {
            $label = (string) $relation->slug;
        }

        if ( '' === $label && isset( $relation->labels ) ) {
            $labels = self::maybe_decode( $relation->labels );
            if ( is_array( $labels ) && isset( $labels['name'] ) ) {
                $label = (string) $labels['name'];
            }
        }

        if ( '' === $label && is_array( $args ) && isset( $args['name'] ) ) {
            $label = (string) $args['name'];
        }

        $label = trim( $label );

        if ( '' === $label ) {
            return null;
        }

        return [
            'id'    => $relation_id,
            'label' => $label,
        ];
    }

    /**
     * Determine whether the provided table exists in the database.
     */
    private static function table_exists( wpdb $wpdb, string $table ): bool {
        if ( '' === $table ) {
            return false;
        }

        $pattern = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : $table;
        $query   = $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern );

        if ( ! $query ) {
            return false;
        }

        return (bool) $wpdb->get_var( $query );
    }

    /**
     * Decode JetEngine payloads stored as serialized PHP or JSON.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function maybe_decode( $value ) {
        if ( is_array( $value ) ) {
            return $value;
        }

        if ( is_object( $value ) ) {
            return (array) $value;
        }

        if ( ! is_string( $value ) ) {
            return $value;
        }

        $trimmed = trim( $value );
        if ( '' === $trimmed ) {
            return $value;
        }

        if ( function_exists( 'maybe_unserialize' ) ) {
            $unserialized = maybe_unserialize( $value );
            if ( $unserialized !== $value ) {
                if ( is_object( $unserialized ) ) {
                    return (array) $unserialized;
                }

                return $unserialized;
            }
        }

        $decoded = json_decode( $value, true );
        if ( JSON_ERROR_NONE === json_last_error() ) {
            return $decoded;
        }

        return $value;
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
