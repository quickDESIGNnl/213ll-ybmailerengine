<?php
namespace GemMailer\Support;

use function absint;
use function apply_filters;
use function __;
use function is_array;
use function is_object;
use function method_exists;
use function sprintf;

/**
 * Thin wrapper around JetEngine relations to provide a stable API.
 */
final class Relations {
    private function __construct() {}

    /**
     * Retrieve available relations for select fields.
     *
     * @return array<int,string>
     */
    public static function choices(): array {
        $manager = self::manager();
        if ( ! $manager || ! method_exists( $manager, 'get_relations' ) ) {
            return [];
        }

        $choices   = [];
        $relations = $manager->get_relations();

        foreach ( $relations as $relation ) {
            $id    = 0;
            $label = '';

            if ( is_object( $relation ) ) {
                if ( method_exists( $relation, 'get_id' ) ) {
                    $id = (int) $relation->get_id();
                } elseif ( property_exists( $relation, 'id' ) ) {
                    $id = (int) $relation->id;
                }

                if ( method_exists( $relation, 'get_args' ) ) {
                    $args  = $relation->get_args();
                    $label = (string) ( $args['name'] ?? $args['title'] ?? $label );
                } elseif ( property_exists( $relation, 'name' ) ) {
                    $label = (string) $relation->name;
                } elseif ( property_exists( $relation, 'title' ) ) {
                    $label = (string) $relation->title;
                }
            } elseif ( is_array( $relation ) ) {
                $id    = (int) ( $relation['id'] ?? $relation['relation_id'] ?? 0 );
                $label = (string) ( $relation['name'] ?? $relation['title'] ?? '' );
            }

            if ( $id ) {
                $choices[ $id ] = $label ? sprintf( '%s (#%d)', $label, $id ) : sprintf( __( 'Relatie #%d', 'gem-mailer' ), $id );
            }
        }

        return $choices;
    }

    /**
     * Retrieve parent IDs for a relation child.
     *
     * @return int[]
     */
    public static function parents( int $relation_id, int $child_id ): array {
        if ( ! $relation_id || ! $child_id ) {
            return [];
        }

        $ids = self::query_related_ids( $relation_id, $child_id, 'parent' );

        /**
         * Allow overriding relation results.
         */
        return apply_filters( 'gem_mailer_relations_parents', $ids, $relation_id, $child_id );
    }

    /**
     * Retrieve child IDs for a relation parent.
     *
     * @return int[]
     */
    public static function children( int $relation_id, int $parent_id ): array {
        if ( ! $relation_id || ! $parent_id ) {
            return [];
        }

        $ids = self::query_related_ids( $relation_id, $parent_id, 'child' );

        return apply_filters( 'gem_mailer_relations_children', $ids, $relation_id, $parent_id );
    }

    /**
     * Try to resolve the JetEngine relation manager.
     */
    private static function manager() {
        if ( ! function_exists( 'jet_engine' ) ) {
            return null;
        }

        $engine = jet_engine();
        if ( ! $engine || ! property_exists( $engine, 'relations' ) ) {
            return null;
        }

        return $engine->relations;
    }

    /**
     * Query JetEngine for related IDs in a given direction.
     *
     * @return int[]
     */
    private static function query_related_ids( int $relation_id, int $object_id, string $direction ): array {
        $manager = self::manager();
        if ( ! $manager ) {
            return [];
        }

        $ids = [];

        if ( property_exists( $manager, 'storage' ) ) {
            $storage = $manager->storage;
            if ( is_object( $storage ) ) {
                if ( method_exists( $storage, 'get_related_ids' ) ) {
                    $result = $storage->get_related_ids(
                        $relation_id,
                        [
                            'object_id' => $object_id,
                            'direction' => $direction,
                        ]
                    );

                    if ( $result ) {
                        $ids = self::normalise_ids( $result );
                    }
                } elseif ( method_exists( $storage, 'get_parents' ) && 'parent' === $direction ) {
                    $ids = self::normalise_ids( $storage->get_parents( $relation_id, $object_id ) );
                } elseif ( method_exists( $storage, 'get_children' ) && 'child' === $direction ) {
                    $ids = self::normalise_ids( $storage->get_children( $relation_id, $object_id ) );
                }
            }
        }

        if ( ! $ids && method_exists( $manager, 'get_relation' ) ) {
            $relation = $manager->get_relation( $relation_id );
            if ( $relation ) {
                if ( 'parent' === $direction ) {
                    if ( method_exists( $relation, 'get_related_parents' ) ) {
                        $ids = self::normalise_ids( $relation->get_related_parents( $object_id ) );
                    } elseif ( method_exists( $relation, 'get_parents' ) ) {
                        $ids = self::normalise_ids( $relation->get_parents( $object_id ) );
                    }
                } else {
                    if ( method_exists( $relation, 'get_related_children' ) ) {
                        $ids = self::normalise_ids( $relation->get_related_children( $object_id ) );
                    } elseif ( method_exists( $relation, 'get_children' ) ) {
                        $ids = self::normalise_ids( $relation->get_children( $object_id ) );
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * Normalise relation results into an array of integers.
     *
     * @param mixed $ids
     *
     * @return int[]
     */
    private static function normalise_ids( $ids ): array {
        if ( is_object( $ids ) && method_exists( $ids, 'to_array' ) ) {
            $ids = $ids->to_array();
        }

        if ( ! is_array( $ids ) ) {
            $ids = [];
        }

        $ids = array_map( 'absint', $ids );
        $ids = array_filter( $ids );

        return array_values( array_unique( $ids ) );
    }
}
