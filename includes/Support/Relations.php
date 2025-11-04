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

        $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern );
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

    /**
     * Retrieve relations from JetEngine's runtime registry.
     *
     * @param mixed $engine
     *
     * @return array<int,array{id:int,label:string}>
     */
    private static function relations_from_engine( $engine ): array {
        $relations = [];

        if ( ! $engine || ! isset( $engine->relations ) || ! is_object( $engine->relations ) ) {
            return $relations;
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

        foreach ( $raw_relations as $relation ) {
            $id    = 0;
            $label = '';

            if ( is_array( $relation ) ) {
                $id    = isset( $relation['id'] ) ? (int) $relation['id'] : 0;
                $label = isset( $relation['name'] ) ? (string) $relation['name'] : (string) ( $relation['slug'] ?? '' );
            } elseif ( is_object( $relation ) ) {
                $id    = isset( $relation->id ) ? (int) $relation->id : 0;
                $label = isset( $relation->name ) ? (string) $relation->name : (string) ( $relation->slug ?? '' );
            }

            $label = trim( $label );

            if ( $id > 0 && '' !== $label ) {
                $relations[] = [
                    'id'    => $id,
                    'label' => $label,
                ];
            }
        }

        return $relations;
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
        }

        return array_values( $relations );
    }

    /**
     * Normalise a database row from the jet_post_types table.
     *
     * @param object $relation
     *
     * @return array{id:int,label:string}|null
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

        $relation_id = 0;
        if ( is_array( $decoded_args ) ) {
            foreach ( [ 'relation_id', 'parent_rel', 'child_rel' ] as $key ) {
                if ( isset( $decoded_args[ $key ] ) && is_numeric( $decoded_args[ $key ] ) ) {
                    $relation_id = (int) $decoded_args[ $key ];
                    break;
                }
            }
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
