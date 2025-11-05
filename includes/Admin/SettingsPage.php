<?php
namespace GemMailer\Admin;

use GemMailer\Support\Relations;
use GemMailer\Support\Settings;

/**
 * Tabbed settings page rendered in the WordPress admin.
 */
class SettingsPage {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'GEM Mailer', 'gem-mailer' ),
            __( 'GEM Mailer', 'gem-mailer' ),
            'manage_options',
            'gem-mailer',
            [ $this, 'render' ],
            'dashicons-email-alt2',
            58
        );
    }

    public function register_settings(): void {
        foreach ( $this->blueprint() as $tab ) {
            foreach ( $tab['fields'] as $field ) {
                $default = $field['default'] ?? '';
                register_setting(
                    'gem_mailer',
                    $field['option'],
                    [
                        'type'              => 'string',
                        'sanitize_callback' => $field['sanitize'] ?? null,
                        'default'           => $default,
                    ]
                );
            }
        }
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tabs       = $this->blueprint();
        $active_tab = isset( $_GET['tab'], $tabs[ $_GET['tab'] ] ) ? $_GET['tab'] : 'new-topic'; // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="wrap gem-mailer-settings">
            <h1><?php esc_html_e( 'GEM Mailer instellingen', 'gem-mailer' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Koppel JetEngine relaties en stel e-mailtemplates in voor forum meldingen. Alle opties gebruiken de sleutel gem_mailer_settings_{veld-naam}.', 'gem-mailer' ); ?>
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
                    <?php if ( 'new-topic' === $active_tab ) :
                        $base_admin   = admin_url( 'admin.php?page=gem-mailer' );
                        $test_example = add_query_arg( 'gem_mailer_test_email', rawurlencode( 'voorbeeld@example.com' ), $base_admin );
                        $process_url  = add_query_arg( 'gem_mailer_process_latest_topic', '1', $base_admin );
                        ?>
                        <div class="notice notice-info inline">
                            <p><?php printf( __( 'Testmail verzenden? Gebruik: %s en vervang het e-mailadres.', 'gem-mailer' ), '<code>' . esc_html( $test_example ) . '</code>' ); ?></p>
                            <p><?php printf( __( 'Laatste onderwerp verwerken? Bezoek: %s.', 'gem-mailer' ), '<code>' . esc_html( $process_url ) . '</code>' ); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ( $tab['fields'] as $field ) : ?>
                        <div class="gem-mailer-field">
                            <label for="<?php echo esc_attr( $field['option'] ); ?>">
                                <strong><?php echo esc_html( $field['label'] ); ?></strong>
                            </label>
                            <p class="description"><?php echo esc_html( $field['description'] ); ?></p>
                            <?php
                            $value = Settings::get( $field['option'], $field['default'] ?? '' );
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
                                case 'text':
                                case 'number':
                                    $attributes = [
                                        'type'  => $field['type'],
                                        'name'  => $field['option'],
                                        'id'    => $field['option'],
                                        'value' => $field['type'] === 'number' ? (int) $value : (string) $value,
                                        'class' => 'regular-text',
                                    ];

                                    if ( ! empty( $field['attrs'] ) && is_array( $field['attrs'] ) ) {
                                        foreach ( $field['attrs'] as $attr_key => $attr_value ) {
                                            $attributes[ $attr_key ] = $attr_value;
                                        }
                                    }

                                    echo '<input ' . $this->render_attributes( $attributes ) . ' />';
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
                                                <?php foreach ( $field['tags'] as $tag => $description ) : ?>
                                                    <li><code><?php echo esc_html( $tag ); ?></code> – <?php echo esc_html( $description ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <?php
                                    endif;
                                    break;
                                default:
                                    /**
                                     * Allow third-parties to render custom field types.
                                     */
                                    do_action( 'gem_mailer/settings/render_field', $field, $value );
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

    /**
     * Render an array of HTML attributes into a string.
     */
    private function render_attributes( array $attributes ): string {
        $html = [];

        foreach ( $attributes as $key => $value ) {
            if ( is_bool( $value ) ) {
                if ( $value ) {
                    $html[] = esc_attr( $key );
                }
                continue;
            }

            $html[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
        }

        return implode( ' ', $html );
    }

    /**
     * Blueprint definition for all tabs and fields.
     *
     * @return array<string,array<string,mixed>>
     */
    private function blueprint(): array {
        $relation_choices = [ 0 => __( '— selecteer relatie —', 'gem-mailer' ) ] + Relations::choices();

        $taxonomies       = get_taxonomies( [ 'public' => true ], 'objects' );
        $taxonomy_choices = [ '' => __( '— selecteer taxonomy —', 'gem-mailer' ) ];
        foreach ( $taxonomies as $taxonomy ) {
            $taxonomy_choices[ $taxonomy->name ] = sprintf( '%s (%s)', $taxonomy->label, $taxonomy->name );
        }

        $post_types       = get_post_types( [ 'public' => true ], 'objects' );
        $post_type_choices = [ '' => __( '— selecteer post type —', 'gem-mailer' ) ];
        foreach ( $post_types as $post_type ) {
            $post_type_choices[ $post_type->name ] = sprintf( '%s (%s)', $post_type->labels->singular_name, $post_type->name );
        }

        return [
            'new-topic' => [
                'label'       => __( 'Nieuw onderwerp in Forum-tax', 'gem-mailer' ),
                'description' => __( 'Selecteer de forum-taxonomy, het custom post type voor forumonderwerpen, de JetEngine relaties en stel het e-mailtemplate in voor een nieuw onderwerp.', 'gem-mailer' ),
                'fields'      => [
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_THEMA_TOPIC_TAXONOMY,
                        'label'       => __( 'Forum-taxonomy', 'gem-mailer' ),
                        'description' => __( 'Kies de taxonomy waarin de forumonderwerpen worden gepubliceerd.', 'gem-mailer' ),
                        'choices'     => $taxonomy_choices,
                        'sanitize'    => 'sanitize_key',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_THEMA_CPT,
                        'label'       => __( 'Thema (CPT)', 'gem-mailer' ),
                        'description' => __( 'Kies het JetEngine post type dat de hoofdonderwerpen/thema’s bevat.', 'gem-mailer' ),
                        'choices'     => $post_type_choices,
                        'sanitize'    => 'sanitize_key',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_TOPIC_CPT,
                        'label'       => __( 'Onderwerpen (CPT)', 'gem-mailer' ),
                        'description' => __( 'De custom post type waarin forumonderwerpen/discussies worden opgeslagen. Deze instelling wordt gedeeld met de tab voor reacties.', 'gem-mailer' ),
                        'choices'     => $post_type_choices,
                        'sanitize'    => 'sanitize_key',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_THEMA_RELATION,
                        'label'       => __( 'Relatie: Thema-object ↔ Taxonomy', 'gem-mailer' ),
                        'description' => __( 'Optioneel: gebruik wanneer thema-objecten aan de taxonomy-term gekoppeld zijn via een JetEngine relatie.', 'gem-mailer' ),
                        'choices'     => $relation_choices,
                        'sanitize'    => 'absint',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_THEMA_TOPIC_RELATION,
                        'label'       => __( 'Relatie: Thema ↔ Onderwerp', 'gem-mailer' ),
                        'description' => __( 'Optioneel: relatie tussen thema en onderwerp wanneer het onderwerp niet direct aan de taxonomy hangt.', 'gem-mailer' ),
                        'choices'     => $relation_choices,
                        'sanitize'    => 'absint',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_THEMA_USER_RELATION,
                        'label'       => __( 'Relatie: Thema ↔ Gebruiker', 'gem-mailer' ),
                        'description' => __( 'Relatie die gebruikers koppelt aan een thema of taxonomy-term. Deze gebruikers ontvangen de melding.', 'gem-mailer' ),
                        'choices'     => $relation_choices,
                        'sanitize'    => 'absint',
                    ],
                    [
                        'type'        => 'text',
                        'option'      => Settings::OPT_THEMA_EMAIL_SUBJECT,
                        'label'       => __( 'E-mail onderwerp', 'gem-mailer' ),
                        'description' => __( 'Onderwerpregel van de e-mail. Tags zijn ook hier beschikbaar.', 'gem-mailer' ),
                        'default'     => Settings::default( Settings::OPT_THEMA_EMAIL_SUBJECT ),
                    ],
                    [
                        'type'        => 'editor',
                        'option'      => Settings::OPT_THEMA_EMAIL_TEMPLATE,
                        'label'       => __( 'E-mailtemplate: nieuw onderwerp', 'gem-mailer' ),
                        'description' => __( 'HTML-template voor meldingen wanneer een nieuw onderwerp wordt geplaatst.', 'gem-mailer' ),
                        'default'     => Settings::default( Settings::OPT_THEMA_EMAIL_TEMPLATE ),
                        'tags'        => [
                            '{{recipient_name}}' => __( 'Naam van de ontvanger.', 'gem-mailer' ),
                            '{{post_title}}'     => __( 'Titel van het onderwerp.', 'gem-mailer' ),
                            '{{post_permalink}}' => __( 'Link naar het onderwerp.', 'gem-mailer' ),
                            '{{site_name}}'      => __( 'Naam van de site.', 'gem-mailer' ),
                            '{{site_url}}'       => __( 'URL van de site.', 'gem-mailer' ),
                            '{{reply_author}}'   => __( 'Naam van de auteur van de nieuwste reactie.', 'gem-mailer' ),
                            '{{reply_excerpt}}'  => __( 'Eerste 20 woorden van de reactie.', 'gem-mailer' ),
                            '{{reply_permalink}}'=> __( 'Directe link naar de reactie.', 'gem-mailer' ),
                        ],
                        'sanitize'    => 'wp_kses_post',
                    ],
                    [
                        'type'        => 'number',
                        'option'      => Settings::OPT_THEMA_DELAY,
                        'label'       => __( 'Vertraging (seconden)', 'gem-mailer' ),
                        'description' => __( 'Aantal seconden wachttijd voordat de e-mail wordt verstuurd, zodat een auteur nog kan aanpassen.', 'gem-mailer' ),
                        'default'     => 0,
                        'attrs'       => [
                            'min'  => 0,
                            'step' => 1,
                        ],
                        'sanitize'    => 'absint',
                    ],
                ],
            ],
            'topic-reaction' => [
                'label'       => __( 'Nieuwe reactie in onderwerp', 'gem-mailer' ),
                'description' => __( 'Kies hetzelfde forumonderwerp-CPT en de relaties waarmee reacties aan onderwerpen en gebruikers gekoppeld worden.', 'gem-mailer' ),
                'fields'      => [
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_TOPIC_CPT,
                        'label'       => __( 'Onderwerpen (CPT)', 'gem-mailer' ),
                        'description' => __( 'De custom post type waarin forumonderwerpen/discussies worden opgeslagen. Deze instelling wordt gedeeld met de tab voor nieuwe onderwerpen.', 'gem-mailer' ),
                        'choices'     => $post_type_choices,
                        'sanitize'    => 'sanitize_key',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_REACTION_CPT,
                        'label'       => __( 'Reacties (CPT)', 'gem-mailer' ),
                        'description' => __( 'Custom post type voor reacties en replies.', 'gem-mailer' ),
                        'choices'     => $post_type_choices,
                        'sanitize'    => 'sanitize_key',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_TOPIC_REACTION_REL,
                        'label'       => __( 'Relatie: Onderwerp ↔ Reactie', 'gem-mailer' ),
                        'description' => __( 'Relatie tussen onderwerp (parent) en reactie (child).', 'gem-mailer' ),
                        'choices'     => $relation_choices,
                        'sanitize'    => 'absint',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_TOPIC_USER_RELATION,
                        'label'       => __( 'Relatie: Onderwerp ↔ Gebruiker', 'gem-mailer' ),
                        'description' => __( 'Relatie met gebruikers die meldingen bij nieuwe reacties moeten ontvangen.', 'gem-mailer' ),
                        'choices'     => $relation_choices,
                        'sanitize'    => 'absint',
                    ],
                    [
                        'type'        => 'editor',
                        'option'      => Settings::OPT_TOPIC_EMAIL_TEMPLATE,
                        'label'       => __( 'E-mailtemplate: reactie op onderwerp', 'gem-mailer' ),
                        'description' => __( 'HTML-template voor meldingen bij een nieuwe reactie in een onderwerp.', 'gem-mailer' ),
                        'default'     => Settings::default( Settings::OPT_TOPIC_EMAIL_TEMPLATE ),
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
                'description' => __( 'Stel de relaties en template in voor replies op reacties.', 'gem-mailer' ),
                'fields'      => [
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_REACTION_USER_REL,
                        'label'       => __( 'Relatie: Reactie ↔ Gebruiker', 'gem-mailer' ),
                        'description' => __( 'Relatie met gebruikers die een melding ontvangen wanneer op een reactie gereageerd wordt.', 'gem-mailer' ),
                        'choices'     => $relation_choices,
                        'sanitize'    => 'absint',
                    ],
                    [
                        'type'        => 'select',
                        'option'      => Settings::OPT_REACTION_REPLY_REL,
                        'label'       => __( 'Relatie: Reactie ↔ Reply', 'gem-mailer' ),
                        'description' => __( 'Relatie waarbij de oorspronkelijke reactie parent is en de reply child.', 'gem-mailer' ),
                        'choices'     => $relation_choices,
                        'sanitize'    => 'absint',
                    ],
                    [
                        'type'        => 'editor',
                        'option'      => Settings::OPT_REACTION_EMAIL_TPL,
                        'label'       => __( 'E-mailtemplate: reactie op reactie', 'gem-mailer' ),
                        'description' => __( 'HTML-template voor meldingen wanneer iemand reageert op een bestaande reactie.', 'gem-mailer' ),
                        'default'     => Settings::default( Settings::OPT_REACTION_EMAIL_TPL ),
                        'tags'        => [
                            '{{recipient_name}}'  => __( 'Naam van de ontvanger.', 'gem-mailer' ),
                            '{{topic_title}}'     => __( 'Titel van het onderwerp.', 'gem-mailer' ),
                            '{{topic_link}}'      => __( 'Permalink naar het onderwerp.', 'gem-mailer' ),
                            '{{reaction_author}}' => __( 'Auteur van de oorspronkelijke reactie.', 'gem-mailer' ),
                            '{{reaction_excerpt}}'=> __( 'Samenvatting van de oorspronkelijke reactie.', 'gem-mailer' ),
                            '{{reply_author}}'    => __( 'Naam van de reply-auteur.', 'gem-mailer' ),
                            '{{reply_excerpt}}'   => __( 'Samenvatting van de reply.', 'gem-mailer' ),
                            '{{reply_permalink}}' => __( 'Permalink naar de reply.', 'gem-mailer' ),
                            '{{site_name}}'       => __( 'Naam van de site.', 'gem-mailer' ),
                            '{{site_url}}'        => __( 'URL van de site.', 'gem-mailer' ),
                        ],
                        'sanitize'    => 'wp_kses_post',
                    ],
                ],
            ],
        ];
    }
}
