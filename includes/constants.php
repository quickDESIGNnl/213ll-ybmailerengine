<?php
/**
 * Core option names and meta keys for the GEM Mailer plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GEM_MAILER_OPT_THEMA_RELATION        = 'gem_mailer_settings_gem_thema_relation';
const GEM_MAILER_OPT_THEMA_USER_RELATION   = 'gem_mailer_settings_gem_thema_user_relation';
const GEM_MAILER_OPT_THEMA_TOPIC_RELATION  = 'gem_mailer_settings_gem_thema_topic_relation';
const GEM_MAILER_OPT_THEMA_TOPIC_TAXONOMY  = 'gem_mailer_settings_gem_thema_topic_tax';
const GEM_MAILER_OPT_THEMA_EMAIL_TEMPLATE  = 'gem_mailer_settings_gem_thema_email';

const GEM_MAILER_OPT_TOPIC_CPT             = 'gem_mailer_settings_gem_topic_cpt';
const GEM_MAILER_OPT_TOPIC_USER_RELATION   = 'gem_mailer_settings_gem_topic_user_relation';
const GEM_MAILER_OPT_TOPIC_REACTIE_REL     = 'gem_mailer_settings_gem_topic_reactie_relation';
const GEM_MAILER_OPT_TOPIC_EMAIL_TEMPLATE  = 'gem_mailer_settings_gem_topic_email';

const GEM_MAILER_OPT_REACTIE_CPT           = 'gem_mailer_settings_gem_reactie_cpt';
const GEM_MAILER_OPT_REACTIE_USER_REL      = 'gem_mailer_settings_gem_reactie_user_relation';
const GEM_MAILER_OPT_REACTIE_REPLY_REL     = 'gem_mailer_settings_gem_reactie_reactie_relation';
const GEM_MAILER_OPT_REACTIE_EMAIL_TEMPLATE = 'gem_mailer_settings_gem_reactie_email';

const GEM_MAILER_META_TOPIC_SENT           = '_gem_mailer_topic_notified';
const GEM_MAILER_META_REACTION_SENT        = '_gem_mailer_reaction_topic_notified';
const GEM_MAILER_META_REPLY_SENT           = '_gem_mailer_reaction_reply_notified';
