<?php
namespace GemMailer;

use GemMailer\Admin\SettingsPage;
use GemMailer\Mailers\NewTopicMailer;
use GemMailer\Mailers\ReactionMailer;

/**
 * Central plugin bootstrapper.
 */
final class Plugin {
    private static ?Plugin $instance = null;

    private SettingsPage $settings;
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
        $this->newTopicMailer  = new NewTopicMailer();
        $this->reactionMailer  = new ReactionMailer();

        $this->settings->register();
        $this->newTopicMailer->register();
        $this->reactionMailer->register();
    }
}
