
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

        $table      = $wpdb->prefix . 'jet_rel_' . $relation_id;
        $like_table = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : $table;
        $exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like_table ) );

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

        if ( function_exists( 'jet_engine' ) ) {
            $engine = jet_engine();

            $relation_source = null;
            if ( $engine && isset( $engine->relations ) && is_object( $engine->relations ) ) {
                if ( isset( $engine->relations->manager ) && is_object( $engine->relations->manager ) && method_exists( $engine->relations->manager, 'get_relations' ) ) {
                    $relation_source = $engine->relations->manager;
                } elseif ( isset( $engine->relations->query ) && is_object( $engine->relations->query ) && method_exists( $engine->relations->query, 'get_relations' ) ) {
                    $relation_source = $engine->relations->query;
                }
            }

            if ( $relation_source ) {
                $relations = $relation_source->get_relations();
                if ( is_array( $relations ) ) {
                    foreach ( $relations as $relation ) {
                        $id    = 0;
                        $label = '';

                        if ( is_array( $relation ) ) {
                            $id    = isset( $relation['id'] ) ? (int) $relation['id'] : 0;
                            $label = isset( $relation['name'] ) ? $relation['name'] : ( $relation['slug'] ?? '' );
                        } elseif ( is_object( $relation ) ) {
                            $id    = isset( $relation->id ) ? (int) $relation->id : 0;
                            $label = isset( $relation->name ) ? $relation->name : ( $relation->slug ?? '' );
                        }

                        if ( $id && $label ) {
                            $choices[ $id ] = sprintf( '%s (#%d)', $label, $id );
                        }
                    }
                }

                $choices[ (int) $relation['id'] ] = sprintf( '%s (#%d)', $relation['label'], (int) $relation['id'] );
            }

            $choices[ (int) $relation['id'] ] = sprintf( '%s (#%d)', $relation['label'], (int) $relation['id'] );
        }

        if ( ! $choices ) {
            global $wpdb;

            $table  = $wpdb->prefix . 'jet_post_types';
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists ) {
                $results = $wpdb->get_results( "SELECT id, name, slug, status, labels, args FROM {$table} WHERE status = 'relation'" );
                foreach ( $results as $relation ) {
                    $args = isset( $relation->args ) ? $relation->args : '';

                    $decoded_args = maybe_unserialize( $args );
                    if ( is_string( $decoded_args ) ) {
                        $json_args = json_decode( $decoded_args, true );
                        if ( json_last_error() === JSON_ERROR_NONE ) {
                            $decoded_args = $json_args;
                        }
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

                    if ( ! $relation_id ) {
                        $relation_id = (int) $relation->id;
                    }

                    if ( ! $relation_id ) {
                        continue;
                    }

                    $label = $relation->name ?: $relation->slug;

                    if ( ! $label && isset( $relation->labels ) ) {
                        $decoded_labels = maybe_unserialize( $relation->labels );
                        if ( is_array( $decoded_labels ) && isset( $decoded_labels['name'] ) ) {
                            $label = (string) $decoded_labels['name'];
                        }
                    }

                    if ( ! $label && is_array( $decoded_args ) && isset( $decoded_args['name'] ) ) {
                        $label = (string) $decoded_args['name'];
                    }

                    if ( $label ) {
                        $choices[ $relation_id ] = sprintf( '%s (#%d)', $label, $relation_id );
                    }
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
    private static function relations_from_engine( $engine ): array {
        $relations = [];

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

        $raw_relations = $relation_source->get_relations();
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

        $decoded_args = maybe_unserialize( $args );
        if ( is_string( $decoded_args ) ) {
            $json_args = json_decode( $decoded_args, true );
            if ( JSON_ERROR_NONE === json_last_error() ) {
                $decoded_args = $json_args;
            }
        }

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
            $decoded_labels = maybe_unserialize( $relation->labels );
            if ( is_object( $decoded_labels ) ) {
                $decoded_labels = (array) $decoded_labels;
            }
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
}
