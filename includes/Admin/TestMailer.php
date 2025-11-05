<?php
namespace GemMailer\Admin;

use GemMailer\Support\Email;
use GemMailer\Support\Settings;
use WP_User;
use function __;
use function add_action;
use function add_query_arg;
use function admin_url;
use function check_admin_referer;
use function current_user_can;
use function get_bloginfo;
use function home_url;
use function in_array;
use function is_email;
use function sanitize_key;
use function wp_get_current_user;
use function wp_mail;
use function wp_nonce_url;
use function wp_safe_redirect;

/**
 * Handle admin triggered test mails.
 */
class TestMailer {
    private const ACTION = 'gem_mailer_send_test';

    /**
     * Allowed template identifiers.
     *
     * @var string[]
     */
    private array $types = [
        'new-topic',
        'reaction',
    ];

    public function register(): void {
        add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
    }

    public function handle(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        check_admin_referer( self::ACTION );

        $type = isset( $_GET['type'] ) ? sanitize_key( (string) $_GET['type'] ) : 'new-topic'; // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! in_array( $type, $this->types, true ) ) {
            $type = 'new-topic';
        }

        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) ) {
            $this->redirect( 'error', 'no-email', $type );
        }

        $payload = $this->payload( $type );

        if ( ! $payload ) {
            $this->redirect( 'error', 'unknown-type', $type );
        }

        [ $subject_tpl, $template, $context ] = $payload;

        $context_with_user = array_merge(
            $context,
            [ 'recipient_name' => $user->display_name ]
        );

        $message = Email::render( $template, $context_with_user );
        $subject = Email::render( $subject_tpl, $context_with_user );

        wp_mail(
            $user->user_email,
            $subject,
            $message,
            [ 'Content-Type: text/html; charset=UTF-8' ]
        );

        $this->redirect( 'sent', '', $type );
    }

    /**
     * Prepare subject, template and context for a test mail.
     *
     * @return array{0:string,1:string,2:array<string,string>}|null
     */
    private function payload( string $type ): ?array {
        switch ( $type ) {
            case 'new-topic':
                $template = (string) Settings::get( Settings::OPT_THEMA_EMAIL_TEMPLATE );
                $subject  = (string) Settings::get( Settings::OPT_THEMA_EMAIL_SUBJECT );
                $context  = [
                    'thema_title'   => __( 'Voorbeeld thema', 'gem-mailer' ),
                    'thema_link'    => home_url( '/forum/thema/voorbeeld' ),
                    'topic_title'   => __( 'Voorbeeld onderwerp', 'gem-mailer' ),
                    'topic_link'    => home_url( '/forum/onderwerpen/voorbeeld' ),
                    'topic_excerpt' => __( 'Dit is een voorbeeldtekst voor een nieuw forumonderwerp.', 'gem-mailer' ),
                    'topic_author'  => __( 'Forumtester', 'gem-mailer' ),
                    'post_title'    => __( 'Voorbeeld onderwerp', 'gem-mailer' ),
                    'post_permalink'=> home_url( '/forum/onderwerpen/voorbeeld' ),
                    'site_name'     => get_bloginfo( 'name' ),
                    'site_url'      => home_url(),
                    'reply_author'  => '',
                    'reply_excerpt' => '',
                    'reply_permalink' => '',
                ];

                return [ $subject, $template, $context ];

            case 'reaction':
                $template = (string) Settings::get( Settings::OPT_REACTION_EMAIL_TEMPLATE );
                $context  = [
                    'topic_title'      => __( 'Voorbeeld onderwerp', 'gem-mailer' ),
                    'topic_link'       => home_url( '/forum/onderwerpen/voorbeeld' ),
                    'reaction_author'  => __( 'Reactie auteur', 'gem-mailer' ),
                    'reaction_link'    => home_url( '/forum/reacties/voorbeeld' ),
                    'reaction_excerpt' => __( 'Dit is een voorbeeld van een reactie binnen het onderwerp.', 'gem-mailer' ),
                    'post_title'       => __( 'Voorbeeld onderwerp', 'gem-mailer' ),
                    'post_permalink'   => home_url( '/forum/onderwerpen/voorbeeld' ),
                    'reply_author'     => __( 'Reactie auteur', 'gem-mailer' ),
                    'reply_excerpt'    => __( 'Dit is een voorbeeld van een reactie binnen het onderwerp.', 'gem-mailer' ),
                    'reply_link'       => home_url( '/forum/reacties/voorbeeld' ),
                    'reply_permalink'  => home_url( '/forum/reacties/voorbeeld' ),
                    'site_name'        => get_bloginfo( 'name' ),
                    'site_url'         => home_url(),
                ];

                $subject = __( '[Test] Nieuwe reactie op {{post_title}}', 'gem-mailer' );

                return [ $subject, $template, $context ];
        }

        return null;
    }

    private function redirect( string $status, string $code, string $type ): void {
        $url = add_query_arg(
            array_filter(
                [
                    'page'              => 'gem-mailer',
                    'gem_mailer_test'   => $status,
                    'gem_mailer_reason' => $code,
                    'type'              => $type,
                ]
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    public function url( string $type ): string {
        if ( ! in_array( $type, $this->types, true ) ) {
            $type = 'new-topic';
        }

        $url = add_query_arg(
            [
                'action' => self::ACTION,
                'type'   => $type,
            ],
            admin_url( 'admin-post.php' )
        );

        return wp_nonce_url( $url, self::ACTION );
    }
}
