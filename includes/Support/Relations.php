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
        if ( $relation_id <= 0 ) {
            return null;
        }

        $wpdb = self::wpdb();
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

        $wpdb = self::wpdb();
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

        $wpdb = self::wpdb();
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

        foreach ( self::relations_from_engine() as $relation ) {
            $choices[ $relation['id'] ] = sprintf( '%s (#%d)', $relation['label'], $relation['id'] );
        }

        $wpdb = self::wpdb();
        if ( $wpdb ) {
            $table  = $wpdb->prefix . 'jet_post_types';
            $exists = self::table_exists( $wpdb, $table );
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

        if ( ! $engine ) {
            return $relations;
        }

        $candidates = [];
        if ( isset( $engine->relations ) && is_object( $engine->relations ) ) {
            $candidates[] = $engine->relations;
            foreach ( [ 'manager', 'query', 'repository' ] as $key ) {
                if ( isset( $engine->relations->{$key} ) && is_object( $engine->relations->{$key} ) ) {
                    $candidates[] = $engine->relations->{$key};
                }
            }
        }

        foreach ( $candidates as $candidate ) {
            if ( ! method_exists( $candidate, 'get_relations' ) ) {
                continue;
            }

            try {
                $raw = $candidate->get_relations();
            } catch ( \Throwable $e ) {
                continue;
            }

            if ( empty( $raw ) || ! is_iterable( $raw ) ) {
                continue;
            }

            foreach ( $raw as $relation ) {
                $normalised = self::normalise_engine_relation( $relation );
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

        $args         = isset( $relation->args ) ? $relation->args : '';
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
    private static function table_exists( \wpdb $wpdb, string $table ): bool {
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

    /**
     * Attempt to normalise relation data returned from JetEngine.
     *
     * @param mixed $relation Raw relation item.
     *
     * @return array{id:int,label:string}|null
     */
    private static function normalise_engine_relation( $relation ): ?array {
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
     * Retrieve the global wpdb instance when available.
     */
    private static function wpdb(): ?\wpdb {
        if ( isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'] instanceof \wpdb ) {
            return $GLOBALS['wpdb'];
        }

        return null;
    }
}
