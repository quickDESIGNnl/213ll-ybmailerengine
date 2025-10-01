<?php
/**
 * Shared option constants and helper utilities for the GEM Mailer plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

const GEM_MAILER_OPT_REL_THEMA_TOPIC   = 'gem_mailer_settings_gem_thema_onderwerp_relation';
const GEM_MAILER_OPT_REL_THEMA_USER    = 'gem_mailer_settings_gem_thema_user_relation';
const GEM_MAILER_OPT_TEMPLATE_TOPIC    = 'gem_mailer_settings_gem_nieuwe_onderwerp_in_thema_email';
const GEM_MAILER_OPT_REL_TOPIC_REACTIE = 'gem_mailer_settings_gem_onderwerp_reactie_relation';
const GEM_MAILER_OPT_REL_REACTIE_USER  = 'gem_mailer_settings_gem_reactie_relation';
const GEM_MAILER_OPT_TEMPLATE_REACTIE  = 'gem_mailer_settings_reacties_email';
const GEM_MAILER_OPT_REL_REPLY_REACTIE = 'gem_mailer_settings_gem_reactie-reactie_relation';
const GEM_MAILER_OPT_TEMPLATE_REPLY    = 'gem_mailer_settings_reacties-reacties_email';

/**
 * Retrieve an integer option value while gracefully handling JetEngine array payloads.
 */
function gem_mailer_get_option_int( string $key ): int {
        $raw = get_option( $key, 0 );
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
                        'description' => __( 'JetEngine relatie-ID waarmee een nieuw onderwerp aan zijn hoofdthema gekoppeld is. Parent: Thema (taxonomie of post), Child: Onderwerp (forums-post).', 'gem-mailer' ),
                        'used_by'     => __( 'Modules “Nieuw onderwerp in thema” en de JetFormBuilder hook voor nieuwe onderwerpen.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_REL_THEMA_USER    => [
                        'label'       => __( 'Relatie: Thema → Gebruiker', 'gem-mailer' ),
                        'description' => __( 'JetEngine relatie-ID die alle geabonneerde gebruikers bij een thema ophaalt. Parent: Thema, Child: Gebruiker.', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Nieuw onderwerp in thema” (bepaalt de ontvangerslijst).', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_TEMPLATE_TOPIC    => [
                        'label'       => __( 'E-mailsjabloon: nieuw onderwerp', 'gem-mailer' ),
                        'description' => __( 'HTML-sjabloon voor meldingen bij nieuwe onderwerpen. Beschikbare placeholders: {{recipient_name}}, {{thema_title}}, {{topic_title}}, {{topic_permalink}}, {{topic_excerpt}}, {{topic_author}}, {{site_name}}, {{site_url}}.', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Nieuw onderwerp in thema”.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_REL_TOPIC_REACTIE => [
                        'label'       => __( 'Relatie: Onderwerp → Reactie', 'gem-mailer' ),
                        'description' => __( 'JetEngine relatie-ID die een reactie koppelt aan het onderliggende onderwerp. Parent: Onderwerp (forums-post), Child: Reactie (gem-reacties).', 'gem-mailer' ),
                        'used_by'     => __( 'Modules “Reacties op onderwerpen” en “Reacties op reacties”, plus JetFormBuilder hooks voor reacties.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_REL_REACTIE_USER  => [
                        'label'       => __( 'Relatie: Reactie → Gebruiker', 'gem-mailer' ),
                        'description' => __( 'Optionele JetEngine relatie-ID die extra volgers of betrokken gebruikers aan een reactie koppelt. Parent: Reactie, Child: Gebruiker.', 'gem-mailer' ),
                        'used_by'     => __( 'Modules “Reacties op onderwerpen” en “Reacties op reacties”.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_TEMPLATE_REACTIE  => [
                        'label'       => __( 'E-mailsjabloon: reactie op onderwerp', 'gem-mailer' ),
                        'description' => __( 'HTML-sjabloon voor notificaties wanneer iemand een reactie op een onderwerp plaatst. Beschikbare placeholders: {{recipient_name}}, {{reaction_author}}, {{reaction_excerpt}}, {{reaction_permalink}}, {{topic_title}}, {{topic_permalink}}, {{site_name}}, {{site_url}}.', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Reacties op onderwerpen”.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_REL_REPLY_REACTIE => [
                        'label'       => __( 'Relatie: Reactie → Reactie (reply)', 'gem-mailer' ),
                        'description' => __( 'JetEngine relatie-ID voor de hiërarchie tussen reacties en hun replies. Parent: Hoofdreactie, Child: Reply (gem-reacties).', 'gem-mailer' ),
                        'used_by'     => __( 'Module “Reacties op reacties” en JetFormBuilder hook voor replies.', 'gem-mailer' ),
                ],
                GEM_MAILER_OPT_TEMPLATE_REPLY    => [
                        'label'       => __( 'E-mailsjabloon: reactie op reactie', 'gem-mailer' ),
                        'description' => __( 'HTML-sjabloon voor reply-notificaties. Beschikbare placeholders: {{recipient_name}}, {{reply_author}}, {{reply_excerpt}}, {{reply_permalink}}, {{reaction_author}}, {{reaction_excerpt}}, {{topic_title}}, {{topic_permalink}}, {{site_name}}, {{site_url}}.', 'gem-mailer' ),
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
                                        <th scope="col"><?php esc_html_e( 'Optiesleutel', 'gem-mailer' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Omschrijving', 'gem-mailer' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Gebruikt door', 'gem-mailer' ); ?></th>
                                </tr>
                        </thead>
                        <tbody>
                                <?php foreach ( $catalog as $key => $info ) : ?>
                                        <tr>
                                                <td><code><?php echo esc_html( $key ); ?></code></td>
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
