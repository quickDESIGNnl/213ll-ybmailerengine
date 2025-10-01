<?php
/**
 * Settings page with tabbed UI for the GEM Mailer configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../helpers.php';

/**
 * Register plugin settings and admin page.
 */
function gem_mailer_register_settings_page(): void {
    add_menu_page(
        __( 'GEM Mailer', 'gem-mailer' ),
        __( 'GEM Mailer', 'gem-mailer' ),
        'manage_options',
        'gem-mailer',
        'gem_mailer_render_settings_page',
        'dashicons-email-alt2',
        58
    );
}
add_action( 'admin_menu', 'gem_mailer_register_settings_page' );

add_action( 'admin_init', 'gem_mailer_register_settings' );

/**
 * Return the configuration for each tab and field.
 *
 * @return array<string,array{label:string,description:string,fields:array<int,array<string,mixed>>}>
 */
function gem_mailer_settings_blueprint(): array {
    $relation_choices = [ 0 => __( '— selecteer relatie —', 'gem-mailer' ) ] + gem_mailer_get_relation_choices();

    $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
    $taxonomy_choices = [ '' => __( '— selecteer taxonomy —', 'gem-mailer' ) ];
    foreach ( $taxonomies as $taxonomy ) {
        $taxonomy_choices[ $taxonomy->name ] = sprintf( '%s (%s)', $taxonomy->label, $taxonomy->name );
    }

    $post_types = get_post_types( [ 'public' => true ], 'objects' );
    $post_type_choices = [ '' => __( '— selecteer post type —', 'gem-mailer' ) ];
    foreach ( $post_types as $post_type ) {
        $post_type_choices[ $post_type->name ] = sprintf( '%s (%s)', $post_type->labels->singular_name, $post_type->name );
    }

    return [
        'new-topic' => [
            'label'       => __( 'Nieuw onderwerp in Forum-tax', 'gem-mailer' ),
            'description' => __( 'Stel in welke Forum-taxonomy en JetEngine-relaties gebruikt worden om ontvangers te verzamelen wanneer er een nieuw onderwerp wordt aangemaakt.', 'gem-mailer' ),
            'fields'      => [
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_THEMA_TOPIC_TAXONOMY,
                    'label'       => __( 'Forum-taxonomy', 'gem-mailer' ),
                    'description' => __( 'Kies de taxonomy waarin de onderwerpen worden gepubliceerd (bijvoorbeeld forum_thema).', 'gem-mailer' ),
                    'choices'     => $taxonomy_choices,
                    'sanitize'    => 'sanitize_key',
                ],
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_THEMA_RELATION,
                    'label'       => __( 'Relatie: Thema-object ↔ Taxonomy', 'gem-mailer' ),
                    'description' => __( 'Optionele JetEngine-relatie waarbij het thema-object (bijv. een CPT) als parent dient en de taxonomy-term als child. Gebruik dit wanneer gebruikers aan het thema-object hangen in plaats van direct aan de term.', 'gem-mailer' ),
                    'choices'     => $relation_choices,
                    'sanitize'    => 'absint',
                ],
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_THEMA_TOPIC_RELATION,
                    'label'       => __( 'Relatie: Thema ↔ Onderwerp', 'gem-mailer' ),
                    'description' => __( 'Optionele JetEngine-relatie waarmee een onderwerp gekoppeld is aan een thema. Gebruik dit wanneer de taxonomy niet direct op het onderwerp staat.', 'gem-mailer' ),
                    'choices'     => $relation_choices,
                    'sanitize'    => 'absint',
                ],
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_THEMA_USER_RELATION,
                    'label'       => __( 'Relatie: Thema ↔ Gebruiker', 'gem-mailer' ),
                    'description' => __( 'Selecteer de JetEngine-relatie waarmee ingeschreven gebruikers aan een thema zijn gekoppeld. De child-objecten van de relatie moeten gebruikers-ID’s zijn.', 'gem-mailer' ),
                    'choices'     => $relation_choices,
                    'sanitize'    => 'absint',
                ],
                [
                    'type'        => 'editor',
                    'option'      => GEM_MAILER_OPT_THEMA_EMAIL_TEMPLATE,
                    'label'       => __( 'E-mailtemplate: nieuw onderwerp', 'gem-mailer' ),
                    'description' => __( 'HTML-template die naar alle ingeschreven gebruikers wordt gestuurd wanneer er een nieuw onderwerp wordt aangemaakt.', 'gem-mailer' ),
                    'tags'        => [
                        '{{recipient_name}}' => __( 'Naam van de ontvanger.', 'gem-mailer' ),
                        '{{thema_title}}'    => __( 'Titel van het thema.', 'gem-mailer' ),
                        '{{thema_link}}'     => __( 'Permalink naar het thema.', 'gem-mailer' ),
                        '{{topic_title}}'    => __( 'Titel van het nieuwe onderwerp.', 'gem-mailer' ),
                        '{{topic_link}}'     => __( 'Permalink naar het onderwerp.', 'gem-mailer' ),
                        '{{topic_excerpt}}'  => __( 'Samenvatting van het onderwerp.', 'gem-mailer' ),
                        '{{topic_author}}'   => __( 'Naam van de auteur van het onderwerp.', 'gem-mailer' ),
                        '{{site_name}}'      => __( 'Naam van de site.', 'gem-mailer' ),
                        '{{site_url}}'       => __( 'URL van de site.', 'gem-mailer' ),
                    ],
                    'sanitize'    => 'wp_kses_post',
                ],
            ],
        ],
        'topic-reaction' => [
            'label'       => __( 'Nieuwe reactie in onderwerp', 'gem-mailer' ),
            'description' => __( 'Bepaal welke post types en relaties gebruikt worden om meldingen voor nieuwe reacties te versturen.', 'gem-mailer' ),
            'fields'      => [
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_TOPIC_CPT,
                    'label'       => __( 'Onderwerpen (CPT)', 'gem-mailer' ),
                    'description' => __( 'Selecteer de JetEngine custom post type die onderwerpen/discussies representeert.', 'gem-mailer' ),
                    'choices'     => $post_type_choices,
                    'sanitize'    => 'sanitize_key',
                ],
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_REACTIE_CPT,
                    'label'       => __( 'Reacties (CPT)', 'gem-mailer' ),
                    'description' => __( 'Selecteer de post type die reacties bevat. Deze wordt gebruikt voor zowel nieuwe reacties als replies.', 'gem-mailer' ),
                    'choices'     => $post_type_choices,
                    'sanitize'    => 'sanitize_key',
                ],
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_TOPIC_REACTIE_REL,
                    'label'       => __( 'Relatie: Onderwerp ↔ Reactie', 'gem-mailer' ),
                    'description' => __( 'JetEngine-relatie waarbij het onderwerp als parent fungeert en de reactie als child. Nodig om bij een reactie het onderwerp op te halen.', 'gem-mailer' ),
                    'choices'     => $relation_choices,
                    'sanitize'    => 'absint',
                ],
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_TOPIC_USER_RELATION,
                    'label'       => __( 'Relatie: Onderwerp ↔ Gebruiker', 'gem-mailer' ),
                    'description' => __( 'Relatie waarmee je gebruikers koppelt aan een onderwerp (bijv. volgers of moderatoren). Deze gebruikers ontvangen een melding bij elke nieuwe reactie.', 'gem-mailer' ),
                    'choices'     => $relation_choices,
                    'sanitize'    => 'absint',
                ],
                [
                    'type'        => 'editor',
                    'option'      => GEM_MAILER_OPT_TOPIC_EMAIL_TEMPLATE,
                    'label'       => __( 'E-mailtemplate: reactie op onderwerp', 'gem-mailer' ),
                    'description' => __( 'HTML-template die wordt verstuurd wanneer er een nieuwe reactie onder een onderwerp wordt geplaatst.', 'gem-mailer' ),
                    'tags'        => [
                        '{{recipient_name}}'   => __( 'Naam van de ontvanger.', 'gem-mailer' ),
                        '{{topic_title}}'      => __( 'Titel van het onderwerp.', 'gem-mailer' ),
                        '{{topic_link}}'       => __( 'Permalink naar het onderwerp.', 'gem-mailer' ),
                        '{{reaction_author}}'  => __( 'Naam van de auteur van de reactie.', 'gem-mailer' ),
                        '{{reaction_link}}'    => __( 'Permalink naar de reactie.', 'gem-mailer' ),
                        '{{reaction_excerpt}}' => __( 'Samenvatting van de reactie.', 'gem-mailer' ),
                        '{{site_name}}'        => __( 'Naam van de site.', 'gem-mailer' ),
                        '{{site_url}}'         => __( 'URL van de site.', 'gem-mailer' ),
                    ],
                    'sanitize'    => 'wp_kses_post',
                ],
            ],
        ],
        'reaction-reply' => [
            'label'       => __( 'Reactie op reactie', 'gem-mailer' ),
            'description' => __( 'Configureer hoe replies op bestaande reacties worden afgehandeld.', 'gem-mailer' ),
            'fields'      => [
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_REACTIE_USER_REL,
                    'label'       => __( 'Relatie: Reactie ↔ Gebruiker', 'gem-mailer' ),
                    'description' => __( 'Relatie die gebruikers koppelt aan een reactie (bijvoorbeeld geabonneerde deelnemers).', 'gem-mailer' ),
                    'choices'     => $relation_choices,
                    'sanitize'    => 'absint',
                ],
                [
                    'type'        => 'select',
                    'option'      => GEM_MAILER_OPT_REACTIE_REPLY_REL,
                    'label'       => __( 'Relatie: Reactie ↔ Reply', 'gem-mailer' ),
                    'description' => __( 'JetEngine-relatie waarbij de parent de oorspronkelijke reactie is en de child de reply.', 'gem-mailer' ),
                    'choices'     => $relation_choices,
                    'sanitize'    => 'absint',
                ],
                [
                    'type'        => 'editor',
                    'option'      => GEM_MAILER_OPT_REACTIE_EMAIL_TEMPLATE,
                    'label'       => __( 'E-mailtemplate: reactie op reactie', 'gem-mailer' ),
                    'description' => __( 'HTML-template voor meldingen wanneer er een reply op een bestaande reactie binnenkomt.', 'gem-mailer' ),
                    'tags'        => [
                        '{{recipient_name}}'  => __( 'Naam van de ontvanger.', 'gem-mailer' ),
                        '{{topic_title}}'     => __( 'Titel van het onderwerp.', 'gem-mailer' ),
                        '{{topic_link}}'      => __( 'Permalink naar het onderwerp.', 'gem-mailer' ),
                        '{{reaction_author}}' => __( 'Auteur van de oorspronkelijke reactie.', 'gem-mailer' ),
                        '{{reaction_excerpt}}'=> __( 'Samenvatting van de oorspronkelijke reactie.', 'gem-mailer' ),
                        '{{reply_author}}'    => __( 'Naam van de auteur van de reply.', 'gem-mailer' ),
                        '{{reply_excerpt}}'   => __( 'Samenvatting van de reply.', 'gem-mailer' ),
                        '{{reply_link}}'      => __( 'Permalink naar de reply.', 'gem-mailer' ),
                        '{{site_name}}'       => __( 'Naam van de site.', 'gem-mailer' ),
                        '{{site_url}}'        => __( 'URL van de site.', 'gem-mailer' ),
                    ],
                    'sanitize'    => 'wp_kses_post',
                ],
            ],
        ],
    ];
}

/**
 * Register settings for every configurable option.
 */
function gem_mailer_register_settings(): void {
    foreach ( gem_mailer_settings_blueprint() as $tab ) {
        foreach ( $tab['fields'] as $field ) {
            register_setting(
                'gem_mailer',
                $field['option'],
                [
                    'type'              => 'string',
                    'sanitize_callback' => $field['sanitize'] ?? null,
                    'default'           => '',
                ]
            );
        }
    }
}

/**
 * Render the admin settings page.
 */
function gem_mailer_render_settings_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $tabs        = gem_mailer_settings_blueprint();
    $active_tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? $_GET['tab'] : 'new-topic'; // phpcs:ignore WordPress.Security.NonceVerification
    ?>
    <div class="wrap gem-mailer-settings">
        <h1><?php esc_html_e( 'GEM Mailer instellingen', 'gem-mailer' ); ?></h1>
        <p class="description">
            <?php esc_html_e( 'Gebruik onderstaande tabbladen om alle relaties, post types en e-mailtemplates voor de mailer-engine vast te leggen. De opties zijn gelijk aan de JetEngine velden (gem_mailer_settings_{veld-naam}).', 'gem-mailer' ); ?>
        </p>

        <h2 class="nav-tab-wrapper">
            <?php foreach ( $tabs as $tab_id => $tab ) : ?>
                <?php $classes = 'nav-tab' . ( $tab_id === $active_tab ? ' nav-tab-active' : '' ); ?>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'gem-mailer', 'tab' => $tab_id ], admin_url( 'admin.php' ) ) ); ?>" class="<?php echo esc_attr( $classes ); ?>">
                    <?php echo esc_html( $tab['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'gem_mailer' );

            $tab = $tabs[ $active_tab ];
            ?>
            <div class="gem-mailer-tab">
                <p><?php echo esc_html( $tab['description'] ); ?></p>
                <?php foreach ( $tab['fields'] as $field ) : ?>
                    <div class="gem-mailer-field">
                        <label for="<?php echo esc_attr( $field['option'] ); ?>">
                            <strong><?php echo esc_html( $field['label'] ); ?></strong>
                        </label>
                        <p class="description"><?php echo esc_html( $field['description'] ); ?></p>
                        <?php
                        $value = gem_mailer_get_option( $field['option'], '' );
                        switch ( $field['type'] ) {
                            case 'select':
                                ?>
                                <select name="<?php echo esc_attr( $field['option'] ); ?>" id="<?php echo esc_attr( $field['option'] ); ?>">
                                    <?php foreach ( $field['choices'] as $choice_value => $choice_label ) : ?>
                                        <option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( (string) $value, (string) $choice_value ); ?>>
                                            <?php echo esc_html( $choice_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php
                                break;
                            case 'editor':
                                $editor_id = $field['option'];
                                wp_editor(
                                    (string) $value,
                                    $editor_id,
                                    [
                                        'textarea_name' => $field['option'],
                                        'textarea_rows' => 10,
                                        'media_buttons' => false,
                                    ]
                                );
                                if ( ! empty( $field['tags'] ) ) :
                                    ?>
                                    <div class="gem-mailer-tags">
                                        <p><strong><?php esc_html_e( 'Beschikbare tags', 'gem-mailer' ); ?></strong></p>
                                        <ul>
                                            <?php foreach ( $field['tags'] as $tag => $tag_description ) : ?>
                                                <li><code><?php echo esc_html( $tag ); ?></code> – <?php echo esc_html( $tag_description ); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php
                                endif;
                                break;
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
