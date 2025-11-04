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

        $query = $wpdb->prepare(
            "SELECT child_object_id FROM {$table} WHERE parent_object_id = %d",
            $parent_id
        );

        if ( ! $query ) {
            return [];
        }

        $ids = $wpdb->get_col( $query );
        if ( ! is_array( $ids ) ) {
            return [];
        }

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

        $query = $wpdb->prepare(
            "SELECT parent_object_id FROM {$table} WHERE child_object_id = %d",
            $child_id
        );

        if ( ! $query ) {
            return [];
        }

        $ids = $wpdb->get_col( $query );
        if ( ! is_array( $ids ) ) {
            return [];
        }

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

        if ( function_exists( 'jet_engine' ) ) {
            $engine = jet_engine();
            foreach ( self::relations_from_engine( $engine ) as $relation ) {
                $choices[ $relation['id'] ] = self::format_choice( $relation['label'], $relation['id'] );
            }
        }

        $wpdb = self::db();
        if ( $wpdb ) {
            foreach ( self::relations_from_database( $wpdb ) as $relation ) {
                $choices[ $relation['id'] ] = self::format_choice( $relation['label'], $relation['id'] );
            }
        }

        ksort( $choices );

        return $cache = $choices;
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

        $pattern = $table;
        if ( method_exists( $wpdb, 'esc_like' ) ) {
            $pattern = $wpdb->esc_like( $table );
        }

        $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern );
        if ( ! $query ) {
            return false;
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

        if ( is_object( $value ) ) {
            return (array) $value;
        }

        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return $value;
        }

        if ( function_exists( 'maybe_unserialize' ) ) {
            $unserialized = maybe_unserialize( $value );
        } else {
            $unserialized = @unserialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
        }

        if ( is_object( $unserialized ) ) {
            return (array) $unserialized;
        }

        if ( is_array( $unserialized ) ) {
            return $unserialized;
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
     * Retrieve relations from JetEngine's runtime registry.
     *
     * @param mixed $value Raw payload.
     *
     * @return mixed
     */
    private static function decode_payload( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return $value;
        }

        $source = null;
        if ( isset( $engine->relations->manager ) && is_object( $engine->relations->manager ) && method_exists( $engine->relations->manager, 'get_relations' ) ) {
            $source = $engine->relations->manager;
        } elseif ( isset( $engine->relations->query ) && is_object( $engine->relations->query ) && method_exists( $engine->relations->query, 'get_relations' ) ) {
            $source = $engine->relations->query;
        }

        if ( ! $source ) {
            return $relations;
        }

        $raw_relations = $source->get_relations();
        if ( ! is_array( $raw_relations ) ) {
            return $relations;
        }

        $unserialized = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $raw ) : @unserialize( $raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

        if ( false !== $unserialized || 'b:0;' === $raw ) {
            return $unserialized;
        }

            $label = trim( $label );

            if ( $id > 0 && '' !== $label ) {
                $relations[] = [
                    'id'    => $id,
                    'label' => $label,
                ];
            }
        }

        return $raw;
    }

    /**
     * Retrieve relations from JetEngine's database tables.
     *
     * @return array<int,array{id:int,label:string}>
     */
    private static function relations_from_database( wpdb $wpdb ): array {
        $relations = [];

        $table = $wpdb->prefix . 'jet_post_types';
        if ( ! self::table_exists( $wpdb, $table ) ) {
            return $relations;
        }

        $results = $wpdb->get_results( "SELECT id, name, slug, status, labels, args FROM {$table} WHERE status = 'relation'" );
        if ( ! is_array( $results ) ) {
            return $relations;
        }

        foreach ( $results as $relation ) {
            $parsed = self::parse_relation_row( $relation );
            if ( $parsed ) {
                $relations[ $parsed['id'] ] = $parsed;
            }

            return '';
        }

        return array_values( $relations );
    }

    /**
     * Normalise a database row from the jet_post_types table.
     *
     * @param mixed $value Label candidate.
     */
    private static function parse_relation_row( $relation ): ?array {
        if ( ! is_object( $relation ) || ! isset( $relation->id ) ) {
            return null;
        }

        $args = isset( $relation->args ) ? $relation->args : '';
        $decoded_args = self::maybe_decode( $args );
        if ( is_object( $decoded_args ) ) {
            $decoded_args = (array) $decoded_args;
        }

        if ( is_string( $value ) ) {
            return trim( $value );
        }

        if ( $relation_id <= 0 ) {
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
            $decoded_labels = self::maybe_decode( $relation->labels );
            if ( is_array( $decoded_labels ) && isset( $decoded_labels['name'] ) ) {
                $label = (string) $decoded_labels['name'];
            }
        }

        if ( '' === $label && is_array( $decoded_args ) && isset( $decoded_args['name'] ) ) {
            $label = (string) $decoded_args['name'];
        }

        $label = trim( $label );
        if ( '' === $label ) {
            return null;
        }

        if ( is_object( $value ) ) {
            return self::normalise_label_value( (array) $value );
        }

        if ( is_string( $value ) ) {
            return trim( $value );
        }

        if ( is_scalar( $value ) ) {
            return trim( (string) $value );
        }

        return '';
    }
}
