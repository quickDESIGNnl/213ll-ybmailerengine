<?php
/**
 * Tests for new-topic mailer.
 */
class NewTopicMailTest extends WP_UnitTestCase {

    protected int $rel_to = 1;
    protected int $rel_tu = 2;

    public function setUp(): void {
        parent::setUp();
        register_post_type( 'forums' );
        global $wpdb;
        $wpdb->query( "CREATE TABLE {$wpdb->prefix}jet_rel_{$this->rel_to} (parent_object_id BIGINT(20), child_object_id BIGINT(20))" );
        $wpdb->query( "CREATE TABLE {$wpdb->prefix}jet_rel_{$this->rel_tu} (parent_object_id BIGINT(20), child_object_id BIGINT(20))" );
        update_option( 'gem_mailer_settings_gem_thema_onderwerp_relation', $this->rel_to );
        update_option( 'gem_mailer_settings_gem_thema_user_relation', $this->rel_tu );
    }

    public function test_topic_to_thema_and_mail_dedup(): void {
        $t1 = self::factory()->term->create( [ 'taxonomy' => 'category' ] );
        $t2 = self::factory()->term->create( [ 'taxonomy' => 'category' ] );

        $parent = self::factory()->post->create( [ 'post_type' => 'post', 'post_status' => 'publish' ] );
        wp_set_post_terms( $parent, [ $t1, $t2 ], 'category' );

        $author = self::factory()->user->create();
        $topic  = self::factory()->post->create( [ 'post_type' => 'forums', 'post_author' => $author ] );

        global $wpdb;
        $wpdb->insert( "{$wpdb->prefix}jet_rel_{$this->rel_to}", [ 'parent_object_id' => $parent, 'child_object_id' => $topic ] );

        $u1 = self::factory()->user->create();
        $u2 = self::factory()->user->create();
        $u3 = self::factory()->user->create();

        $wpdb->insert( "{$wpdb->prefix}jet_rel_{$this->rel_tu}", [ 'parent_object_id' => $t1, 'child_object_id' => $u1 ] );
        $wpdb->insert( "{$wpdb->prefix}jet_rel_{$this->rel_tu}", [ 'parent_object_id' => $t1, 'child_object_id' => $u2 ] );
        $wpdb->insert( "{$wpdb->prefix}jet_rel_{$this->rel_tu}", [ 'parent_object_id' => $t2, 'child_object_id' => $u2 ] );
        $wpdb->insert( "{$wpdb->prefix}jet_rel_{$this->rel_tu}", [ 'parent_object_id' => $t2, 'child_object_id' => $u3 ] );

        $themas = gem_topic_to_themas( $topic, $this->rel_to );
        $this->assertEqualSets( [ $t1, $t2 ], $themas );
        $this->assertEquals( $t1, gem_topic_to_thema( $topic, $this->rel_to ) );

        $mail_calls = 0;
        add_filter( 'pre_wp_mail', function ( $pre, $atts ) use ( &$mail_calls ) {
            $mail_calls++;
            return true;
        }, 10, 2 );

        gem_try_new_topic_mail( $topic );

        $this->assertEquals( 3, $mail_calls );
    }
}
