<?php
/**
 * Shared option constants and helper utilities for the GEM Mailer plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

const GEM_MAILER_OPT_REL_THEMA_TOPIC   = 'gem_mailer_settings_gem_thema_onderwerp_relation';
const GEM_MAILER_OPT_REL_THEMA_USER    = 'gem_mailer_settings_gem_thema_user_relation';
const GEM_MAILER_OPT_TEMPLATE_TOPIC    = 'gem_mailer_settings_gem_nieuw_onderwerp_in_thema_email';
const GEM_MAILER_OPT_REL_TOPIC_REACTIE = 'gem_mailer_settings_gem_onderwerp_reactie_relation';
const GEM_MAILER_OPT_REL_REACTIE_USER  = 'gem_mailer_settings_gem_reactie_relation';
const GEM_MAILER_OPT_TEMPLATE_REACTIE  = 'gem_mailer_settings_reacties_email';
const GEM_MAILER_OPT_REL_REPLY_REACTIE = 'gem_mailer_settings_gem_reactie_reactie_relation';
const GEM_MAILER_OPT_TEMPLATE_REPLY    = 'gem_mailer_settings_reacties_reacties_email';

/**
 * Known aliases for legacy or JetEngine-provided option keys.
 */
function gem_mailer_option_aliases(): array {
        static $aliases = null;

        if ( null === $aliases ) {
                $aliases = [
                        GEM_MAILER_OPT_TEMPLATE_TOPIC    => [
                                'gem_mailer_settings_gem_nieuwe_onderwerp_in_thema_email',
                        ],
                        GEM_MAILER_OPT_REL_TOPIC_REACTIE => [
                                'gem_mailer_settings_onderwerpen-gem-cpt',
                                'gem_mailer_settings_gem_onderwerpen_relation',
                        ],
                        GEM_MAILER_OPT_REL_REPLY_REACTIE => [
                                'gem_mailer_settings_reacties-gem-cpt',
                                'gem_mailer_settings_gem_reactie-reactie_relation',
                        ],
                        GEM_MAILER_OPT_TEMPLATE_REPLY    => [
                                'gem_mailer_settings_reacties-reacties_email',
                        ],
                ];

                /**
                 * Filter the option alias map.
                 *
                 * Allows third parties to register extra aliases for option keys
                 * so the helper functions can resolve values stored under legacy
                 * JetEngine option slugs.
                 */
                $aliases = apply_filters( 'gem_mailer_option_aliases', $aliases );
        }

        return $aliases;
}

/**
 * Determine the actual option key that contains a configured value.
 */
function gem_mailer_resolve_option_key( string $canonical ): string {
        $sentinel = new \stdClass();

        $value = get_option( $canonical, $sentinel );
        if ( $sentinel !== $value ) {
                return $canonical;
        }

        $aliases = gem_mailer_option_aliases();

        foreach ( $aliases[ $canonical ] ?? [] as $alias ) {
                $value = get_option( $alias, $sentinel );
                if ( $sentinel !== $value ) {
                        return $alias;
                }
        }

        return $canonical;
}

/**
 * Retrieve a raw option value with alias support.
 */
function gem_mailer_get_option( string $key, $default = false ) {
        $resolved_key = gem_mailer_resolve_option_key( $key );

        return get_option( $resolved_key, $default );
}

/**
 * Retrieve an integer option value while gracefully handling JetEngine array payloads.
 */
function gem_mailer_get_option_int( string $key ): int {
        $raw = gem_mailer_get_option( $key, 0 );
        if ( is_array( $raw ) ) {
                $raw = reset( $raw );
        }
        return (int) $raw;
}

/**
 * Describe all configurable options so we can surface consistent help text in the admin UI.
 */
function gem_mailer_option_catalog(): array {
        return [
                GEM_MAILER_OPT_REL_THEMA_TOPIC   => [
                        'label'       => __( 'Relatie: Thema → Onderwerp', 'gem-mailer' ),
                        'description' => __( 'Kies de JetEngine-relatie die ieder forumonderwerp (child) koppelt aan het hoofdthema (parent). Parent: Thema (term of posttype), Child: Onderwerp (forums).', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Nieuw onderwerp in thema” en de JetFormBuilder-hook voor nieuwe onderwerpen.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_REL_THEMA_USER    => [
                        'label'       => __( 'Relatie: Thema → Gebruiker', 'gem-mailer' ),
                        'description' => __( 'Selecteer de relatie waarmee je alle ingeschreven gebruikers (child) bij een thema (parent) ophaalt. De IDs vormen de ontvangerslijst.', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Nieuw onderwerp in thema”.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_TEMPLATE_TOPIC    => [
                        'label'       => __( 'E-mailsjabloon: nieuw onderwerp', 'gem-mailer' ),
                        'description' => __( 'HTML-sjabloon voor meldingen van nieuwe onderwerpen. Beschikbare placeholders: {{recipient_name}}, {{thema_title}}, {{topic_title}}, {{topic_permalink}}, {{topic_excerpt}}, {{topic_author}}, {{site_name}}, {{site_url}}.', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Nieuw onderwerp in thema”.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_REL_TOPIC_REACTIE => [
                        'label'       => __( 'Relatie: Onderwerp → Reactie', 'gem-mailer' ),
                        'description' => __( 'Relatie die een reactie (child) aan het onderliggende onderwerp (parent) koppelt. Nodig om de keten van reacties naar het onderwerp op te bouwen.', 'gem-mailer' ),
                        'used_by'     => __( 'Modules “Reacties op onderwerpen”, “Reacties op reacties” en JetFormBuilder-hooks voor reacties.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_REL_REACTIE_USER  => [
                        'label'       => __( 'Relatie: Reactie → Gebruiker', 'gem-mailer' ),
                        'description' => __( 'Optionele relatie waarbij extra betrokken gebruikers (child) aan een reactie (parent) hangen. Gebruik dit voor volgers of moderators.', 'gem-mailer' ),
                        'used_by'     => __( 'Modules “Reacties op onderwerpen” en “Reacties op reacties”.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_TEMPLATE_REACTIE  => [
                        'label'       => __( 'E-mailsjabloon: reactie op onderwerp', 'gem-mailer' ),
                        'description' => __( 'HTML-sjabloon voor meldingen wanneer iemand op een onderwerp reageert. Placeholders: {{recipient_name}}, {{reaction_author}}, {{reaction_excerpt}}, {{reaction_permalink}}, {{topic_title}}, {{topic_permalink}}, {{site_name}}, {{site_url}}.', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Reacties op onderwerpen”.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_REL_REPLY_REACTIE => [
                        'label'       => __( 'Relatie: Reactie → Reactie (reply)', 'gem-mailer' ),
                        'description' => __( 'Kies de JetEngine-relatie die replies (child) koppelt aan hun hoofdreactie (parent) zodat de volledige reactieboom beschikbaar is.', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Reacties op reacties” en JetFormBuilder-hook voor replies.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_TEMPLATE_REPLY    => [
                        'label'       => __( 'E-mailsjabloon: reactie op reactie', 'gem-mailer' ),
                        'description' => __( 'HTML-sjabloon voor meldingen van replies op reacties. Placeholders: {{recipient_name}}, {{reply_author}}, {{reply_excerpt}}, {{reply_permalink}}, {{reaction_author}}, {{reaction_excerpt}}, {{topic_title}}, {{topic_permalink}}, {{site_name}}, {{site_url}}.', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Reacties op reacties”.', 'gem-mailer' ),
                ],
        ];
}

/**
 * Register a read-only settings help page that lists all option keys and their purpose.
 */
function gem_mailer_register_settings_help(): void {
        add_options_page(
                __( 'GEM Mailer instellingen', 'gem-mailer' ),
                __( 'GEM Mailer', 'gem-mailer' ),
                'manage_options',
                'gem-mailer-settings',
                'gem_mailer_render_settings_help'
        );
}
add_action( 'admin_menu', 'gem_mailer_register_settings_help' );

/**
 * Render the settings overview table.
 */
function gem_mailer_render_settings_help(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
                return;
        }

        $catalog = gem_mailer_option_catalog();
        ?>
        <div class="wrap">
                <h1><?php esc_html_e( 'GEM Mailer instellingenoverzicht', 'gem-mailer' ); ?></h1>
                <p><?php esc_html_e( 'Onderstaande tabel toont alle option keys die de mailmodules gebruiken. Controleer of iedere JetEngine-relatie of sjabloon op de juiste sleutel is opgeslagen.', 'gem-mailer' ); ?></p>
                <table class="widefat striped">
                        <thead>
                                <tr>
                                        <th scope="col"><?php esc_html_e( 'Veld-naam', 'gem-mailer' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Optiesleutel', 'gem-mailer' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Omschrijving', 'gem-mailer' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Gebruikt door', 'gem-mailer' ); ?></th>
                                </tr>
                        </thead>
                        <tbody>
                                <?php foreach ( $catalog as $key => $info ) : ?>
                                        <?php
                                        $display_key = gem_mailer_resolve_option_key( $key );
                                        $slug        = 0 === strpos( $key, 'gem_mailer_settings_' )
                                                ? substr( $key, strlen( 'gem_mailer_settings_' ) )
                                                : $key;
                                        ?>
                                        <tr>
                                                <td><code><?php echo esc_html( $slug ); ?></code></td>
                                                <td>
                                                        <code><?php echo esc_html( $display_key ); ?></code>
                                                        <?php if ( $display_key !== $key ) : ?>
                                                                <p class="description">
                                                                        <?php
                                                                        printf(
                                                                                wp_kses_post( __( 'Waarde gevonden op alias <code>%1$s</code>; kopieer naar <code>%2$s</code> om JetEngine consistent te houden.', 'gem-mailer' ) ),
                                                                                esc_html( $display_key ),
                                                                                esc_html( $key )
                                                                        );
                                                                        ?>
                                                                </p>
                                                        <?php endif; ?>
                                                </td>
                                                <td>
                                                        <strong><?php echo esc_html( $info['label'] ); ?></strong>
                                                        <p class="description"><?php echo esc_html( $info['description'] ); ?></p>
                                                </td>
                                                <td><?php echo esc_html( $info['used_by'] ); ?></td>
                                        </tr>
                                <?php endforeach; ?>
                        </tbody>
                </table>
        </div>
        <?php
}