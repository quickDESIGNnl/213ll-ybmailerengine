<?php
namespace GemMailer\Admin;

use GemMailer\Mailers\NewTopicMailer;

use function __;
use function admin_url;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function get_transient;
use function is_admin;
use function is_email;
use function remove_query_arg;
use function sanitize_email;
use function set_transient;
use function sprintf;
use function wp_get_referer;
use function wp_safe_redirect;
use function wp_unslash;

/**
 * Handle manual trigger links for test mails and replaying the latest topic notification.
 */
class ManualActions {
    private const NOTICE_KEY = 'gem_mailer_manual_notice';

    private NewTopicMailer $newTopicMailer;

    public function __construct( NewTopicMailer $newTopicMailer ) {
        $this->newTopicMailer = $newTopicMailer;
    }

    public function register(): void {
        add_action( 'admin_init', [ $this, 'maybe_handle' ] );
        add_action( 'admin_notices', [ $this, 'render_notice' ] );
    }

    public function maybe_handle(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['gem_mailer_test_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $email = sanitize_email( wp_unslash( $_GET['gem_mailer_test_email'] ) ); // phpcs:ignore WordPress.Security

            $success = $email && is_email( $email ) && $this->newTopicMailer->send_test_email( $email );
            $message = $success
                ? sprintf( __( 'Testmail verzonden naar %s.', 'gem-mailer' ), $email )
                : __( 'Kon testmail niet verzenden. Controleer het e-mailadres en of er onderwerpen beschikbaar zijn.', 'gem-mailer' );

            $this->store_notice( $message, $success ? 'success' : 'error' );
            $this->redirect_without( [ 'gem_mailer_test_email' ] );
        }

        if ( isset( $_GET['gem_mailer_process_latest_topic'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $success = $this->newTopicMailer->process_latest_topic();
            $message = $success
                ? __( 'Laatste onderwerp verwerkt en meldingen verzonden.', 'gem-mailer' )
                : __( 'Geen gepubliceerd onderwerp gevonden om te verwerken.', 'gem-mailer' );

            $this->store_notice( $message, $success ? 'success' : 'error' );
            $this->redirect_without( [ 'gem_mailer_process_latest_topic' ] );
        }
    }

    public function render_notice(): void {
        $notice = get_transient( self::NOTICE_KEY );
        if ( ! $notice || empty( $notice['message'] ) ) {
            return;
        }

        delete_transient( self::NOTICE_KEY );

        $type = 'notice-' . ( 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success' );

        printf(
            '<div class="notice %1$s"><p>%2$s</p></div>',
            esc_attr( $type ),
            esc_html( (string) $notice['message'] )
        );
    }

    private function store_notice( string $message, string $type ): void {
        set_transient(
            self::NOTICE_KEY,
            [
                'message' => $message,
                'type'    => $type,
            ],
            30
        );
    }

    private function redirect_without( array $keys ): void {
        $referer = wp_get_referer();
        $target  = $referer ? remove_query_arg( $keys, $referer ) : remove_query_arg( $keys, admin_url( 'admin.php?page=gem-mailer' ) );

        wp_safe_redirect( $target );
        exit;
    }
}
