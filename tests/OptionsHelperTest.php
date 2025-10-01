<?php
/**
 * Tests for option helper fallbacks.
 */
class OptionsHelperTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        delete_option( GEM_MAILER_OPT_REL_TOPIC_REACTIE );
        delete_option( 'gem_mailer_settings_onderwerpen-gem-cpt' );
        delete_option( GEM_MAILER_OPT_TEMPLATE_REACTIE );
    }

    public function test_prefers_canonical_option_when_present(): void {
        update_option( GEM_MAILER_OPT_REL_TOPIC_REACTIE, 21 );
        update_option( 'gem_mailer_settings_onderwerpen-gem-cpt', 42 );

        $this->assertSame( 21, gem_mailer_get_option_int( GEM_MAILER_OPT_REL_TOPIC_REACTIE ) );
    }

    public function test_falls_back_to_alias_when_canonical_missing(): void {
        delete_option( GEM_MAILER_OPT_REL_TOPIC_REACTIE );
        update_option( 'gem_mailer_settings_onderwerpen-gem-cpt', 84 );

        $this->assertSame( 84, gem_mailer_get_option_int( GEM_MAILER_OPT_REL_TOPIC_REACTIE ) );
    }

    public function test_string_helper_uses_resolved_option_key(): void {
        delete_option( GEM_MAILER_OPT_TEMPLATE_REACTIE );
        $expected = '<p>Hallo wereld</p>';
        update_option( GEM_MAILER_OPT_TEMPLATE_REACTIE, $expected );

        $this->assertSame( $expected, gem_mailer_get_option( GEM_MAILER_OPT_TEMPLATE_REACTIE, '' ) );
    }

    public function test_alias_for_new_topic_template_is_resolved(): void {
        delete_option( GEM_MAILER_OPT_TEMPLATE_TOPIC );
        $legacy_key = 'gem_mailer_settings_gem_nieuwe_onderwerp_in_thema_email';
        delete_option( $legacy_key );

        $expected = '<p>Nieuw onderwerp</p>';
        update_option( $legacy_key, $expected );

        $this->assertSame( $expected, gem_mailer_get_option( GEM_MAILER_OPT_TEMPLATE_TOPIC, '' ) );
    }

    public function test_alias_for_reply_relation_is_resolved(): void {
        delete_option( GEM_MAILER_OPT_REL_REPLY_REACTIE );
        $legacy_key = 'gem_mailer_settings_gem_reactie-reactie_relation';
        delete_option( $legacy_key );

        update_option( $legacy_key, 123 );

        $this->assertSame( 123, gem_mailer_get_option_int( GEM_MAILER_OPT_REL_REPLY_REACTIE ) );
    }

    public function test_alias_for_reply_template_is_resolved(): void {
        delete_option( GEM_MAILER_OPT_TEMPLATE_REPLY );
        $legacy_key = 'gem_mailer_settings_reacties-reacties_email';
        delete_option( $legacy_key );

        $expected = '<p>Reply</p>';
        update_option( $legacy_key, $expected );

        $this->assertSame( $expected, gem_mailer_get_option( GEM_MAILER_OPT_TEMPLATE_REPLY, '' ) );
    }
}