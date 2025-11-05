<?php
namespace GemMailer\Support;

use function __;

/**
 * Collection of option keys and helper utilities for retrieving settings.
 */
final class Settings {
    public const OPT_THEMA_RELATION        = 'gem_mailer_settings_gem_thema_relation';
    public const OPT_THEMA_USER_RELATION   = 'gem_mailer_settings_gem_thema_user_relation';
    public const OPT_THEMA_TOPIC_RELATION  = 'gem_mailer_settings_gem_thema_onderwerp_relation';
    public const OPT_THEMA_TOPIC_TAXONOMY  = 'gem_mailer_settings_gem_thema_topic_tax';
    public const OPT_THEMA_CPT             = 'gem_mailer_settings_cpt_thema';
    public const OPT_THEMA_EMAIL_TEMPLATE  = 'gem_mailer_settings_gem_nieuwe--onderwerp-in-thema_email';
    public const OPT_THEMA_EMAIL_SUBJECT   = 'gem_mailer_settings_mail_titel_o';
    public const OPT_THEMA_DELAY           = 'gem_mailer_settings_vertraging_nieuw_onderwerp';

    public const OPT_TOPIC_CPT             = 'gem_mailer_settings_gem_topic_cpt';
    public const OPT_TOPIC_USER_RELATION   = 'gem_mailer_settings_gem_topic_user_relation';
    public const OPT_TOPIC_REACTION_REL    = 'gem_mailer_settings_gem_topic_reactie_relation';
    public const OPT_TOPIC_EMAIL_TEMPLATE  = 'gem_mailer_settings_gem_topic_email';

    public const OPT_REACTION_CPT          = 'gem_mailer_settings_gem_reactie_cpt';
    public const OPT_REACTION_USER_REL     = 'gem_mailer_settings_gem_reactie_user_relation';
    public const OPT_REACTION_REPLY_REL    = 'gem_mailer_settings_gem_reactie_reactie_relation';
    public const OPT_REACTION_EMAIL_TPL    = 'gem_mailer_settings_gem_reactie_email';

    public const META_TOPIC_SENT           = '_gem_mailer_topic_notified';
    public const META_REACTION_SENT        = '_gem_mailer_reaction_topic_notified';
    public const META_REPLY_SENT           = '_gem_mailer_reaction_reply_notified';

    private function __construct() {}

    /**
     * Retrieve a default value for an option key.
     */
    public static function default( string $key ): string {
        switch ( $key ) {
            case self::OPT_THEMA_EMAIL_TEMPLATE:
                return self::default_new_topic_template();
            case self::OPT_THEMA_EMAIL_SUBJECT:
                return __( 'Nieuw onderwerp: {{post_title}}', 'gem-mailer' );
            case self::OPT_TOPIC_EMAIL_TEMPLATE:
                return self::default_topic_reaction_template();
            case self::OPT_REACTION_EMAIL_TPL:
                return self::default_reply_template();
            default:
                return '';
        }
    }

    /**
     * Retrieve an option value with JetEngine specific normalisation.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function get( string $key, $default = null ) {
        if ( null === $default ) {
            $default = self::default( $key );
        }

        $value = get_option( $key, $default );

        if ( is_array( $value ) && array_key_exists( 0, $value ) ) {
            return $value[0];
        }

        return $value;
    }

    private static function default_new_topic_template(): string {
        return implode(
            "\n\n",
            [
                '<p>' . __( 'Hoi {{recipient_name}},', 'gem-mailer' ) . '</p>',
                '<p>' . __( 'Er is een nieuw onderwerp geplaatst op het forum.', 'gem-mailer' ) . '</p>',
                '<p><a href="{{post_permalink}}"><strong>{{post_title}}</strong></a></p>',
                '<p>' . __( 'Bekijk het onderwerp via bovenstaande link.', 'gem-mailer' ) . '</p>',
                '<p>' . __( 'Met vriendelijke groet,', 'gem-mailer' ) . '<br>{{site_name}}</p>',
            ]
        );
    }

    private static function default_topic_reaction_template(): string {
        return implode(
            "\n\n",
            [
                '<p>' . __( 'Hoi {{recipient_name}},', 'gem-mailer' ) . '</p>',
                '<p>' . __( 'Er is een nieuwe reactie geplaatst op het onderwerp {{topic_title}}.', 'gem-mailer' ) . '</p>',
                '<p>{{reaction_excerpt}}</p>',
                '<p><a href="{{reaction_link}}">' . __( 'Bekijk de reactie', 'gem-mailer' ) . '</a></p>',
                '<p>' . __( 'Tot snel op', 'gem-mailer' ) . ' {{site_name}}</p>',
            ]
        );
    }

    private static function default_reply_template(): string {
        return implode(
            "\n\n",
            [
                '<p>' . __( 'Hoi {{recipient_name}},', 'gem-mailer' ) . '</p>',
                '<p>' . __( 'Er is een nieuw antwoord geplaatst op jouw reactie.', 'gem-mailer' ) . '</p>',
                '<p><strong>' . __( 'Reactie van', 'gem-mailer' ) . ' {{reply_author}}</strong></p>',
                '<p>{{reply_excerpt}}</p>',
                '<p><a href="{{reply_permalink}}">' . __( 'Lees het volledige antwoord', 'gem-mailer' ) . '</a></p>',
                '<p>' . __( 'Groeten van', 'gem-mailer' ) . ' {{site_name}}</p>',
            ]
        );
    }
}
