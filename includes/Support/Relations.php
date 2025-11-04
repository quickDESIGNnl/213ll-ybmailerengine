<?php
namespace GemMailer\Support;

/**
 * Helper utilities around JetEngine relations.
 */
final class Relations {
    private function __construct() {}

    /**
     * Retrieve a JetEngine relation table for the provided relation ID.
     */
    public static function table( int $relation_id ): ?string {
        if ( ! $relation_id ) {
            return null;
        }

        if ( ! isset( $GLOBALS['wpdb'] ) || ! $GLOBALS['wpdb'] instanceof \wpdb ) {
            return null;
        }

        global $wpdb;

        $table  = $wpdb->prefix . 'jet_rel_' . $relation_id;
        $exists = self::table_exists( $table );

        return $exists ? $table : null;
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

        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT child_object_id FROM {$table} WHERE parent_object_id = %d",
                $parent_id
            )
        );

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

        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT parent_object_id FROM {$table} WHERE child_object_id = %d",
                $child_id
            )
        );

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

        $engine_relations = self::relations_from_engine();
        foreach ( $engine_relations as $relation ) {
            if ( empty( $relation['id'] ) || empty( $relation['label'] ) ) {
                continue;
            }

            $choices[ (int) $relation['id'] ] = sprintf( '%s (#%d)', $relation['label'], (int) $relation['id'] );
        }

        if ( ! $choices ) {
            global $wpdb;

            $table  = $wpdb->prefix . 'jet_post_types';
            $exists = self::table_exists( $table );
            if ( $exists ) {
                $results = $wpdb->get_results( "SELECT id, name, slug, status, labels, args FROM {$table} WHERE status = 'relation'" );
                foreach ( $results as $relation ) {
                    $parsed = self::parse_relation_row( $relation );
                    if ( ! $parsed ) {
                        continue;
                    }

                    $choices[ $parsed['id'] ] = sprintf( '%s (#%d)', $parsed['label'], $parsed['id'] );
                }
            }
        }

        ksort( $choices );

        $cache = $choices;

        return $choices;
    }

    /**
     * Normalise relations retrieved from JetEngine's runtime registry.
     *
     * @param mixed $engine
     *
     * @return array<int,array{id:int,label:string}>
     */
    private static function relations_from_engine(): array {
        $relations = [];

        if ( ! function_exists( 'jet_engine' ) ) {
            return $relations;
        }

        try {
            $engine = jet_engine();
        } catch ( \Throwable $e ) {
            return $relations;
        }

        if ( ! $engine || ! isset( $engine->relations ) || ! is_object( $engine->relations ) ) {
            return $relations;
        }

        $relation_source = null;
        if ( isset( $engine->relations->manager ) && is_object( $engine->relations->manager ) && method_exists( $engine->relations->manager, 'get_relations' ) ) {
            $relation_source = $engine->relations->manager;
        } elseif ( isset( $engine->relations->query ) && is_object( $engine->relations->query ) && method_exists( $engine->relations->query, 'get_relations' ) ) {
            $relation_source = $engine->relations->query;
        }

        if ( ! $relation_source ) {
            return $relations;
        }

        try {
            $raw_relations = $relation_source->get_relations();
        } catch ( \Throwable $e ) {
            return $relations;
        }

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

            if ( $id && $label ) {
                $relations[] = [
                    'id'    => $id,
                    'label' => trim( $label ),
                ];
            }
        }

        return $relations;
    }

    /**
     * Normalise a database row from the jet_post_types table.
     *
     * @param object $relation
     *
     * @return array{id:int,label:string}|null
     */
    private static function parse_relation_row( $relation ): ?array {
        if ( ! isset( $relation->id ) ) {
            return null;
        }

        $args = isset( $relation->args ) ? $relation->args : '';

        $decoded_args = self::maybe_decode( $args );

        $relation_id = 0;
        if ( is_array( $decoded_args ) ) {
            foreach ( [ 'relation_id', 'parent_rel', 'child_rel' ] as $key ) {
                if ( isset( $decoded_args[ $key ] ) && is_numeric( $decoded_args[ $key ] ) ) {
                    $relation_id = (int) $decoded_args[ $key ];
                    break;
                }
            }
        }

        if ( ! $relation_id ) {
            $relation_id = (int) $relation->id;
        }

        if ( ! $relation_id ) {
            return null;
        }

        $label = is_string( $relation->name ) && $relation->name ? $relation->name : '';
        if ( ! $label && isset( $relation->slug ) ) {
            $label = (string) $relation->slug;
        }

        if ( ! $label && isset( $relation->labels ) ) {
            $decoded_labels = self::maybe_decode( $relation->labels );
            if ( is_array( $decoded_labels ) && isset( $decoded_labels['name'] ) ) {
                $label = (string) $decoded_labels['name'];
            }
        }

        if ( ! $label && is_array( $decoded_args ) && isset( $decoded_args['name'] ) ) {
            $label = (string) $decoded_args['name'];
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
    private static function table_exists( string $table ): bool {
        global $wpdb;

        if ( ! $table || ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
            return false;
        }

        $pattern = $table;
        if ( method_exists( $wpdb, 'esc_like' ) ) {
            $pattern = $wpdb->esc_like( $pattern );
        }

        $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern );
        if ( ! $query ) {
            return false;
        }

        $exists = $wpdb->get_var( $query );

        return ! empty( $exists );
    }

    /**
     * Attempt to decode a JetEngine payload that may be serialized or JSON.
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

        if ( function_exists( 'json_decode' ) && function_exists( 'json_last_error' ) ) {
            $decoded = json_decode( $value, true );
            if ( JSON_ERROR_NONE === json_last_error() ) {
                return $decoded;
            }
        }

        return $value;
    }
}
