<?php
namespace GemMailer\Support;

/**
 * Utilities for rendering and dispatching templated emails.
 */
final class Email {
    private function __construct() {}

    /**
     * Replace placeholder tags within an email template.
     */
    public static function render( string $template, array $context ): string {
        $replacements = [];
        foreach ( $context as $key => $value ) {
            $replacements[ '{{' . $key . '}}' ] = $value;
        }

        return strtr( $template, $replacements );
    }

    /**
     * Send the template to all provided users.
     *
     * @param int[]  $user_ids
     * @param string $subject
     * @param string $template
     * @param array  $context
     */
    public static function send_to_users( array $user_ids, string $subject, string $template, array $context ): void {
        foreach ( $user_ids as $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user || ! is_email( $user->user_email ) ) {
                continue;
            }

            $context_for_user = array_merge(
                $context,
                [ 'recipient_name' => $user->display_name ]
            );

            $message = self::render( $template, $context_for_user );
            $subject_line = self::render( $subject, $context_for_user );

            wp_mail(
                $user->user_email,
                $subject_line,
                $message,
                [ 'Content-Type: text/html; charset=UTF-8' ]
            );
        }
    }
}
