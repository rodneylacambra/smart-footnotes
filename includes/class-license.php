<?php
/**
 * License management for Smart Footnotes Pro.
 *
 * Gates PRO features. Ready for Freemius SDK integration.
 * Replace the stub below with Freemius initialization once the SDK is added.
 *
 * @package SmartFootnotesPro
 */

defined( 'ABSPATH' ) || exit;

class SFN_License {

    private static ?SFN_License $instance = null;

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Returns true when the PRO addon is active and has a valid license.
     *
     * Integration point: replace this stub with a Freemius license check,
     * e.g. `return sfnfs()->can_use_premium_code();`
     *
     * The PRO addon itself can also override this by filtering 'sfn_is_pro_active'.
     */
    public function is_pro_active(): bool {
        // Check if PRO addon has registered itself.
        $pro_registered = apply_filters( 'sfn_pro_is_registered', false );

        if ( ! $pro_registered ) {
            return false;
        }

        // ── Freemius stub ─────────────────────────────────────────────────────
        // Once the Freemius SDK is added, uncomment and adapt:
        //
        // if ( function_exists( 'sfnfs' ) ) {
        //     return sfnfs()->can_use_premium_code();
        // }

        return (bool) apply_filters( 'sfn_pro_license_valid', false );
    }

    /**
     * Show an admin notice if a PRO feature is accessed without a license.
     */
    public function maybe_show_upgrade_notice( string $feature = '' ): void {
        if ( $this->is_pro_active() ) {
            return;
        }

        $message = $feature
            ? sprintf(
                /* translators: 1: feature name, 2: upgrade URL */
                __( '"%1$s" requires Smart Footnotes Pro. <a href="%2$s">Upgrade now</a>.', 'smart-footnotes' ),
                esc_html( $feature ),
                esc_url( 'https://example.com/smart-footnotes-pro/upgrade' )
            )
            : __( 'This feature requires Smart Footnotes Pro.', 'smart-footnotes' );

        add_action( 'admin_notices', function () use ( $message ) {
            echo '<div class="notice notice-warning is-dismissible"><p>'
                . wp_kses( $message, [ 'a' => [ 'href' => [] ] ] )
                . '</p></div>';
        } );
    }
}
