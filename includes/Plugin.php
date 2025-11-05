<?php
namespace GemMailer;

use GemMailer\Admin\SettingsPage;
use GemMailer\Admin\TestMailer;
use GemMailer\Mailers\NewTopicMailer;
use GemMailer\Mailers\ReactionMailer;

/**
 * Central plugin bootstrapper.
 */
final class Plugin {
    private static ?Plugin $instance = null;

    private SettingsPage $settings;
    private TestMailer $testMailer;
    private NewTopicMailer $newTopicMailer;
    private ReactionMailer $reactionMailer;

    private function __construct() {}

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        $this->settings        = new SettingsPage();
        $this->testMailer      = new TestMailer();
        $this->newTopicMailer  = new NewTopicMailer();
        $this->reactionMailer  = new ReactionMailer();

        $this->settings->setTestMailer( $this->testMailer );
        $this->settings->register();
        $this->testMailer->register();
        $this->newTopicMailer->register();
        $this->reactionMailer->register();
    }
}
