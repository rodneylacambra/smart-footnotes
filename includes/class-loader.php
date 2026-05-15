<?php
/**
 * Plugin loader for Smart Footnotes (free).
 *
 * @package SmartFootnotes
 */

defined( 'ABSPATH' ) || exit;

class SFN_Loader {

    private static ?SFN_Loader $instance = null;

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        $this->wire_license();
        $this->wire_settings();
        $this->wire_renderer();
        $this->wire_meta();
        $this->wire_assets();
    }

    // ── i18n ──────────────────────────────────────────────────────────────────
    // WordPress automatically loads translations for plugins hosted on .org
    // since version 4.6. No manual load_plugin_textdomain() needed.

    // ── License ───────────────────────────────────────────────────────────────

    private function wire_license(): void {
        add_filter( 'sfn_is_pro_active', [ SFN_License::get_instance(), 'is_pro_active' ] );
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    private function wire_settings(): void {
        $settings = SFN_Settings::get_instance();
        add_action( 'admin_menu', [ $settings, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $settings, 'register_settings' ] );
    }

    // ── Renderer ──────────────────────────────────────────────────────────────

    private function wire_renderer(): void {
        $renderer = SFN_Renderer::get_instance();
        add_filter( 'the_content', [ $renderer, 'append_footnote_list' ], 15 );
        add_shortcode( 'sfn',      [ $renderer, 'shortcode' ] );
    }

    // ── Per-post meta box + REST meta ─────────────────────────────────────────

    private function wire_meta(): void {
        SFN_Meta_Box::get_instance()->init();

        foreach ( [ '_sfn_label_override', '_sfn_theme_override', '_sfn_mode_override' ] as $key ) {
            register_post_meta( '', $key, [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
                'sanitize_callback' => 'sanitize_text_field',
            ] );
        }
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    private function wire_assets(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
    }

    public function enqueue_frontend(): void {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        $has_shortcode = $post && has_shortcode( $post->post_content, 'sfn' );

        if ( ! $has_shortcode ) {
            return;
        }

        wp_enqueue_style(
            'sfn-frontend',
            SFN_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            SFN_VERSION
        );

        wp_enqueue_style(
            'sfn-themes',
            SFN_PLUGIN_URL . 'assets/css/themes.css',
            [ 'sfn-frontend' ],
            SFN_VERSION
        );

        wp_enqueue_script(
            'sfn-frontend',
            SFN_PLUGIN_URL . 'assets/js/frontend.js',
            [],
            SFN_VERSION,
            [ 'strategy' => 'defer', 'in_footer' => true ]
        );

        wp_enqueue_script(
            'sfn-tooltip',
            SFN_PLUGIN_URL . 'assets/js/tooltip.js',
            [ 'sfn-frontend' ],
            SFN_VERSION,
            [ 'strategy' => 'defer', 'in_footer' => true ]
        );

        wp_localize_script( 'sfn-frontend', 'sfnData', [
            'mode'    => SFN_Settings::get_instance()->get_display_mode(),
            'theme'   => SFN_Settings::get_instance()->get_theme(),
            'isPro'   => sfn_is_pro_active(),
            'restUrl' => rest_url( 'sfn/v1/' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'i18n'    => [
                'Footnote'        => __( 'Footnote',        'smart-footnotes' ),
                'Close footnote'  => __( 'Close footnote',  'smart-footnotes' ),
                'Active footnote' => __( 'Active footnote', 'smart-footnotes' ),
            ],
        ] );
    }
}
