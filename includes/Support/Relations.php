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
        if ( ! $relation_id ) {
            return null;
        }

        global $wpdb;

        $table  = $wpdb->prefix . 'jet_rel_' . $relation_id;
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

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

            $table  = $wpdb->prefix . 'jet_relations';
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists ) {
                $results = $wpdb->get_results( "SELECT id, name, slug FROM {$table} ORDER BY name" );
                foreach ( $results as $relation ) {
                    $label = $relation->name ?: $relation->slug;
                    if ( $label ) {
                        $choices[ (int) $relation->id ] = sprintf( '%s (#%d)', $label, $relation->id );
                    }
                }
            }
        }

        if ( ! $choices ) {
            global $wpdb;

            $table  = $wpdb->prefix . 'jet_post_types';
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists ) {
                $results = $wpdb->get_results( "SELECT id, name, slug, status, args FROM {$table}" );
                foreach ( $results as $relation ) {
                    $status = isset( $relation->status ) ? $relation->status : '';
                    $args   = isset( $relation->args ) ? $relation->args : '';

                    $decoded_args = maybe_unserialize( $args );
                    if ( is_string( $decoded_args ) ) {
                        $json_args = json_decode( $decoded_args, true );
                        if ( json_last_error() === JSON_ERROR_NONE ) {
                            $decoded_args = $json_args;
                        }
                    }

                    $type = '';
                    if ( is_array( $decoded_args ) && isset( $decoded_args['type'] ) ) {
                        $type = (string) $decoded_args['type'];
                    }

                    if ( 'relation' !== $status && 'relation' !== $type ) {
                        continue;
                    }

                    $label = $relation->name ?: $relation->slug;
                    if ( ! $label && is_array( $decoded_args ) && isset( $decoded_args['name'] ) ) {
                        $label = (string) $decoded_args['name'];
                    }

                    if ( $label ) {
                        $choices[ (int) $relation->id ] = sprintf( '%s (#%d)', $label, $relation->id );
                    }
                }
            }
        }

        ksort( $choices );

        $cache = $choices;

        return $choices;
    }
}
