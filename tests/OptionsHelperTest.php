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
}
