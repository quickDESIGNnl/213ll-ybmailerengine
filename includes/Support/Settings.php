<?php
namespace GemMailer\Support;

/**
 * Collection of option keys and helper utilities for retrieving settings.
 */
final class Settings {
    public const OPT_THEMA_RELATION        = 'gem_mailer_settings_gem_thema_relation';
    public const OPT_THEMA_USER_RELATION   = 'gem_mailer_settings_gem_thema_user_relation';
    public const OPT_THEMA_TOPIC_RELATION  = 'gem_mailer_settings_gem_thema_topic_relation';
    public const OPT_THEMA_TOPIC_TAXONOMY  = 'gem_mailer_settings_gem_thema_topic_tax';
    public const OPT_THEMA_EMAIL_TEMPLATE  = 'gem_mailer_settings_gem_thema_email';

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
     * Retrieve an option value with JetEngine specific normalisation.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function get( string $key, $default = '' ) {
        $value = get_option( $key, $default );

        if ( is_array( $value ) && array_key_exists( 0, $value ) ) {
            return $value[0];
        }

        return $value;
    }
}
