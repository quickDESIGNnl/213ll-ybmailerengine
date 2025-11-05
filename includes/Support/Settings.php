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
    public const OPT_THEMA_EMAIL_DELAY     = 'gem_mailer_settings_vertraging_nieuw_onderwerp';

    public const OPT_TOPIC_CPT             = 'gem_mailer_settings_onderwerpen-gem-cpt';
    public const OPT_TOPIC_USER_RELATION   = 'gem_mailer_settings_gem_topic_user_relation';
    public const OPT_TOPIC_REACTION_REL    = 'gem_mailer_settings_gem_reactie_onderwerp_relation';
    public const OPT_TOPIC_EMAIL_TEMPLATE  = 'gem_mailer_settings_reacties_email';

    public const OPT_REACTION_CPT          = 'gem_mailer_settings_reacties-gem-cpt';
    public const OPT_REACTION_USER_REL     = 'gem_mailer_settings_gem_reactie_user_relation';
    public const OPT_REACTION_REPLY_REL    = 'gem_mailer_settings_gem_reactie_reactie_relation';
    public const OPT_REACTION_EMAIL_TPL    = 'gem_mailer_settings_gem_reactie_email';

    public const META_TOPIC_SENT           = '_gem_mailer_topic_notified';
    public const META_REACTION_SENT        = '_gem_mailer_reaction_topic_notified';
    public const META_REPLY_SENT           = '_gem_mailer_reaction_reply_notified';

    private const LEGACY_KEYS = [
        self::OPT_TOPIC_CPT            => 'gem_mailer_settings_gem_topic_cpt',
        self::OPT_TOPIC_REACTION_REL   => 'gem_mailer_settings_gem_topic_reactie_relation',
        self::OPT_TOPIC_EMAIL_TEMPLATE => 'gem_mailer_settings_gem_topic_email',
        self::OPT_REACTION_CPT         => 'gem_mailer_settings_gem_reactie_cpt',
    ];

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

        $missing = new \stdClass();
        $value   = self::get_option_value( $key, $missing );

        if ( $value === $missing ) {
            $value = $default ?? self::default( $key );
        }

        if ( is_array( $value ) && array_key_exists( 0, $value ) ) {
            return $value[0];
        }

        return $value;
    }

    /**
     * Retrieve an option while respecting legacy keys.
     *
     * @param mixed $missing Sentinel used to detect missing values.
     *
     * @return mixed
     */
    private static function get_option_value( string $key, $missing ) {
        $keys = self::option_keys( $key );

        foreach ( $keys as $candidate ) {
            $value = get_option( $candidate, $missing );
            if ( $value !== $missing ) {
                return $value;
            }
        }

        return $missing;
    }

    /**
     * Return all option keys (new + legacy) that should be checked for a value.
     *
     * @return string[]
     */
    private static function option_keys( string $key ): array {
        $keys = [ $key ];

        if ( isset( self::LEGACY_KEYS[ $key ] ) ) {
            $keys[] = self::LEGACY_KEYS[ $key ];
        }

        return $keys;
    }

    private static function default_new_topic_template(): string {
        return implode(
            "\n\n",
            [
                '<p>' . __( 'Hoi {{recipient_name}},', 'gem-mailer' ) . '</p>',
                '<p>' . __( 'Er is een nieuw onderwerp geplaatst op {{site_name}}.', 'gem-mailer' ) . '</p>',
                '<p><strong>{{post_title}}</strong></p>',
                '<p><a href="{{post_permalink}}">' . __( 'Bekijk het onderwerp', 'gem-mailer' ) . '</a></p>',
                '<p>' . __( 'Met vriendelijke groet,', 'gem-mailer' ) . '<br>{{site_name}}</p>',
            ]
        );
    }

    private static function default_topic_reaction_template(): string {
        return implode(
            "\n\n",
            [
                '<p>' . __( 'Hoi {{recipient_name}},', 'gem-mailer' ) . '</p>',
                '<p>' . __( 'Er is een nieuwe reactie geplaatst op het onderwerp {{post_title}}.', 'gem-mailer' ) . '</p>',
                '<p><strong>{{reply_author}}</strong></p>',
                '<p>{{reply_excerpt}}</p>',
                '<p><a href="{{reply_permalink}}">' . __( 'Bekijk de reactie', 'gem-mailer' ) . '</a></p>',
                '<p>' . __( 'Tot snel op', 'gem-mailer' ) . ' {{site_name}}</p>',
            ]
        );
    }

    private static function default_reply_template(): string {
        return implode(
            "\n\n",
            [
                '<p>' . __( 'Hoi {{recipient_name}},', 'gem-mailer' ) . '</p>',
                '<p>' . __( 'Er is een nieuw antwoord geplaatst op jouw reactie in het onderwerp {{post_title}}.', 'gem-mailer' ) . '</p>',
                '<p><strong>' . __( 'Reactie van', 'gem-mailer' ) . ' {{reply_author}}</strong></p>',
                '<p>{{reply_excerpt}}</p>',
                '<p><a href="{{reply_permalink}}">' . __( 'Lees het volledige antwoord', 'gem-mailer' ) . '</a></p>',
                '<p>' . __( 'Groeten van', 'gem-mailer' ) . ' {{site_name}}</p>',
            ]
        );
    }
}

