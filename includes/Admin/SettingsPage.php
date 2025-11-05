<?php
namespace GemMailer\Admin;

use function __;
use function add_action;
use function add_menu_page;
use function current_user_can;
use function esc_attr;
use function esc_html_e;
use function esc_url;
use function sanitize_key;

/**
 * Minimal beheerpagina met uitsluitend testacties.
 */
class SettingsPage {
    private ?TestMailer $testMailer = null;

    public function setTestMailer( TestMailer $testMailer ): void {
        $this->testMailer = $testMailer;
    }

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'GEM Mailer', 'gem-mailer' ),
            __( 'GEM Mailer', 'gem-mailer' ),
            'manage_options',
            'gem-mailer',
            [ $this, 'render' ],
            'dashicons-email-alt2',
            58
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice = $this->current_notice();
        ?>
        <div class="wrap gem-mailer-settings">
            <h1><?php esc_html_e( 'GEM Mailer instellingen', 'gem-mailer' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Gebruik de JetEngine Options Page om relaties en templates te beheren. Deze pagina biedt alleen snelle testknoppen.', 'gem-mailer' ); ?>
            </p>

            <?php if ( $notice ) : ?>
                <div class="notice <?php echo esc_attr( $notice['class'] ); ?>">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <?php $this->render_test_actions(); ?>
        </div>
        <?php
    }

    /**
     * Render test mail action buttons when the controller is available.
     */
    private function render_test_actions(): void {
        if ( ! $this->testMailer ) {
            return;
        }

        ?>
        <div class="gem-mailer-test-actions">
            <p><strong><?php esc_html_e( 'Testmail versturen', 'gem-mailer' ); ?></strong></p>
            <p><?php esc_html_e( 'Gebruik deze knoppen om direct een testmail naar je eigen beheerdersadres te sturen.', 'gem-mai
ler' ); ?></p>
            <p class="gem-mailer-test-buttons">
                <a class="button button-secondary" href="<?php echo esc_url( $this->testMailer->url( 'new-topic' ) ); ?>">
                    <?php esc_html_e( 'Test: nieuw onderwerp', 'gem-mailer' ); ?>
                </a>
                <a class="button button-secondary" href="<?php echo esc_url( $this->testMailer->url( 'reaction' ) ); ?>">
                    <?php esc_html_e( 'Test: reactie in onderwerp', 'gem-mailer' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Determine if a success or error notice should be displayed.
     *
     * @return array{class:string,message:string}|null
     */
    private function current_notice(): ?array {
        if ( ! isset( $_GET['gem_mailer_test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return null;
        }

        $status = sanitize_key( (string) $_GET['gem_mailer_test'] ); // phpcs:ignore WordPress.Security.NonceVerification
        $type   = isset( $_GET['type'] ) ? sanitize_key( (string) $_GET['type'] ) : 'new-topic'; // phpcs:ignore WordPress.Security.NonceVerification

        if ( 'sent' === $status ) {
            $labels = [
                'new-topic' => __( 'Testmail voor een nieuw onderwerp is verzonden naar je eigen e-mailadres.', 'gem-mailer' ),
                'reaction'  => __( 'Testmail voor een reactie is verzonden naar je eigen e-mailadres.', 'gem-mailer' ),
            ];

            return [
                'class'   => 'notice-success',
                'message' => $labels[ $type ] ?? __( 'Testmail verzonden.', 'gem-mailer' ),
            ];
        }

        if ( 'error' === $status ) {
            $reason = isset( $_GET['gem_mailer_reason'] ) ? sanitize_key( (string) $_GET['gem_mailer_reason'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

            if ( 'no-email' === $reason ) {
                return [
                    'class'   => 'notice-error',
                    'message' => __( 'Kan geen testmail versturen omdat het beheeraccount geen geldig e-mailadres heeft.', 'gem-mailer' ),
                ];
            }

            return [
                'class'   => 'notice-error',
                'message' => __( 'De testmail kon niet worden verzonden.', 'gem-mailer' ),
            ];
        }

        return null;
    }
}
