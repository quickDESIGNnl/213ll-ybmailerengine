<?php
namespace GemMailer;

use GemMailer\Admin\ManualActions;
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
    private ManualActions $manualActions;

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
        $this->manualActions   = new ManualActions( $this->newTopicMailer );

        $this->settings->register();
        $this->newTopicMailer->register();
        $this->reactionMailer->register();
        $this->manualActions->register();
    }
}
