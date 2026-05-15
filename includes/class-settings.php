<?php
/**
 * Settings management for Smart Footnotes Pro.
 *
 * @package SmartFootnotesPro
 */

defined( 'ABSPATH' ) || exit;

class SFN_Settings {

    private static ?SFN_Settings $instance = null;

    /** Option key stored in wp_options. */
    const OPTION_KEY = 'sfn_settings';

    /** Cached settings array. */
    private array $settings = [];

    private function __construct() {
        $this->settings = get_option( self::OPTION_KEY, [] );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Defaults ──────────────────────────────────────────────────────────────

    public function get_defaults(): array {
        return [
            // General
            'display_mode'    => 'classic',
            'numbering_type'  => 'numeric',
            // Label (FREE)
            'label'           => 'Footnotes',
            'show_label'      => true,
            // Deduplication (FREE)
            'combine_duplicates' => true,
            // Label styling (PRO)
            'label_font_size'  => '',
            'label_color'      => '',
            'label_align'      => 'left',
            'label_uppercase'  => false,
            // Design
            'theme'     => 'minimal',
            // Custom theme
            'custom_theme_enabled' => false,
            'theme_styles'         => [
                'background_color'  => '#ffffff',
                'text_color'        => '#1a1a2e',
                'text_muted_color'  => '#6b6b8a',
                'primary_color'     => '#534ab7',
                'link_color'        => '#534ab7',
                'border_color'      => '#e0ddf8',
                'border_radius'     => '8px',
                'border_width'      => '1px',
                'divider_style'     => 'solid',
                'padding'           => '16px',
                'margin_top'        => '2.5rem',
                'list_spacing'      => '6px',
                'font_size'         => '14px',
                'line_height'       => '1.6',
                'font_family'       => '',
                'padding_mobile'    => '12px',
            ],
            // Advanced (PRO)
            'enable_analytics'      => false,
            'enable_reusable_notes' => false,
        ];
    }

    /** Write defaults to the database on first activation. */
    public function set_defaults(): void {
        if ( false === get_option( self::OPTION_KEY ) ) {
            update_option( self::OPTION_KEY, $this->get_defaults() );
            $this->settings = $this->get_defaults();
        }
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function get( string $key, mixed $fallback = null ): mixed {
        $defaults = $this->get_defaults();
        if ( isset( $this->settings[ $key ] ) ) {
            return $this->settings[ $key ];
        }
        return $defaults[ $key ] ?? $fallback;
    }

    public function get_display_mode(): string {
        return sfn_sanitize_display_mode( $this->get( 'display_mode', 'classic' ) );
    }

    public function get_numbering_type(): string {
        return sfn_sanitize_numbering_type( $this->get( 'numbering_type', 'numeric' ) );
    }

    public function get_theme(): string {
        $allowed = [ 'minimal', 'modern', 'academic' ];
        $theme   = $this->get( 'theme', 'minimal' );
        return in_array( $theme, $allowed, true ) ? $theme : 'minimal';
    }

    public function get_custom_theme_enabled(): bool {
        return true; // Always enabled — toggle removed per UX decision.
    }

    /**
     * Returns the merged theme_styles array — defaults filled in for any missing keys.
     */
    public function get_theme_styles(): array {
        $defaults = $this->get_defaults()['theme_styles'];
        $stored   = $this->get( 'theme_styles', [] );
        return array_merge( $defaults, is_array( $stored ) ? $stored : [] );
    }

    /**
     * PRO: Build the inline CSS variable string to inject on the container element.
     * Also injects the --sfn-color-primary from the top-level setting for consistency.
     *
     * @param string|null $post_theme  Per-post theme override slug (from post meta).
     * @return string  Empty string when custom theme is disabled or PRO is inactive.
     */
    public function get_inline_theme_styles( ?string $post_theme = null ): string {
        if ( ! $this->get_custom_theme_enabled() ) {
            return '';
        }

        $s = $this->get_theme_styles();
        $s = apply_filters( 'sfn_theme_styles', $s, $this->settings );

        $css = sprintf(
            '--sfn-color-bg:%s;--sfn-color-text:%s;--sfn-color-text-muted:%s;'
            . '--sfn-color-primary:%s;--sfn-color-link:%s;'
            . '--sfn-color-border:%s;--sfn-radius:%s;--sfn-border-width:%s;'
            . '--sfn-divider-style:%s;--sfn-padding:%s;--sfn-spacing:%s;'
            . '--sfn-list-spacing:%s;--sfn-font-size:%s;--sfn-line-height:%s;'
            . '--sfn-padding-mobile:%s;',
            esc_attr( $s['background_color'] ),
            esc_attr( $s['text_color'] ),
            esc_attr( $s['text_muted_color'] ),
            esc_attr( $s['primary_color'] ),
            esc_attr( $s['link_color'] ),
            esc_attr( $s['border_color'] ),
            sfn_sanitize_css_unit( $s['border_radius'] ),
            sfn_sanitize_css_unit( $s['border_width'] ),
            in_array( $s['divider_style'], [ 'solid', 'dashed', 'none' ], true ) ? $s['divider_style'] : 'solid',
            sfn_sanitize_css_unit( $s['padding'] ),
            sfn_sanitize_css_unit( $s['margin_top'] ),
            sfn_sanitize_css_unit( $s['list_spacing'] ),
            sfn_sanitize_css_unit( $s['font_size'] ),
            sfn_sanitize_css_unit( $s['line_height'], true ),
            sfn_sanitize_css_unit( $s['padding_mobile'] )
        );

        if ( ! empty( $s['font_family'] ) ) {
            $css .= '--sfn-font-family:' . esc_attr( $s['font_family'] ) . ';';
        }

        return $css;
    }

    public function get_label(): string {
        $label = $this->get( 'label', '' );
        return $label !== '' ? $label : __( 'Footnotes', 'smart-footnotes' );
    }

    public function get_show_label(): bool {
        return (bool) $this->get( 'show_label', true );
    }

    public function get_combine_duplicates(): bool {
        return (bool) $this->get( 'combine_duplicates', true );
    }

    /**
     * PRO: resolve the effective label, optionally auto-switching
     * based on the active numbering type (dynamic labels).
     *
     * @param string|null $numbering_type  Active numbering type for this render pass.
     * @param string|null $post_label      Per-post override (from post meta), or null.
     */
    public function get_effective_label( ?string $numbering_type = null, ?string $post_label = null ): string {
        // Per-post override takes highest priority — now FREE.
        if ( $post_label !== null && $post_label !== '' ) {
            return sanitize_text_field( $post_label );
        }

        // Dynamic label based on numbering type — now FREE.
        if ( $numbering_type ) {
            $dynamic = apply_filters( 'sfn_dynamic_label', null, $numbering_type );
            if ( $dynamic !== null ) {
                return sanitize_text_field( $dynamic );
            }

            // Built-in dynamic map — only applies when no custom label is set.
            $map = [
                'roman'   => __( 'References', 'smart-footnotes' ),
                'symbol'  => __( 'Notes',      'smart-footnotes' ),
                'alpha'   => __( 'Footnotes',  'smart-footnotes' ),
                'numeric' => __( 'Footnotes',  'smart-footnotes' ),
            ];
            if ( isset( $map[ $numbering_type ] ) && $this->get( 'label', '' ) === '' ) {
                return $map[ $numbering_type ];
            }
        }

        return $this->get_label();
    }

    // ── Setters ───────────────────────────────────────────────────────────────

    public function update( array $new_values ): bool {
        $this->settings = array_merge( $this->settings, $new_values );
        return update_option( self::OPTION_KEY, $this->settings );
    }

    // ── Admin page ────────────────────────────────────────────────────────────

    public function register_admin_menu(): void {
        add_options_page(
            __( 'Smart Footnotes', 'smart-footnotes' ),
            __( 'Smart Footnotes', 'smart-footnotes' ),
            'manage_options',
            'smart-footnotes',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_settings_page(): void {
        require_once SFN_PLUGIN_DIR . 'admin/settings-page.php';
    }

    public function register_settings(): void {
        register_setting(
            'sfn_settings_group',
            self::OPTION_KEY,
            [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
        );

        // ── General section ───────────────────────────────────────────────────
        add_settings_section( 'sfn_general', __( 'General', 'smart-footnotes' ), '__return_false', 'sfn_settings' );

        add_settings_field(
            'display_mode', __( 'Default display mode', 'smart-footnotes' ),
            [ $this, 'field_display_mode' ], 'sfn_settings', 'sfn_general'
        );
        add_settings_field(
            'numbering_type', __( 'Numbering type', 'smart-footnotes' ),
            [ $this, 'field_numbering_type' ], 'sfn_settings', 'sfn_general'
        );
        add_settings_field(
            'label', __( 'Footnotes label', 'smart-footnotes' ),
            [ $this, 'field_label' ], 'sfn_settings', 'sfn_general'
        );
        add_settings_field(
            'show_label', __( 'Display label', 'smart-footnotes' ),
            [ $this, 'field_show_label' ], 'sfn_settings', 'sfn_general'
        );
        add_settings_field(
            'combine_duplicates', __( 'Combine duplicates', 'smart-footnotes' ),
            [ $this, 'field_combine_duplicates' ], 'sfn_settings', 'sfn_general'
        );

        // ── Design section ────────────────────────────────────────────────────
        add_settings_section( 'sfn_design', __( 'Design', 'smart-footnotes' ), '__return_false', 'sfn_settings' );

        add_settings_field(
            'theme', __( 'Theme preset', 'smart-footnotes' ),
            [ $this, 'field_theme' ], 'sfn_settings', 'sfn_design'
        );
    }

    public function sanitize_settings( mixed $input ): array {
        if ( ! is_array( $input ) ) {
            return $this->get_defaults();
        }

        $clean = [];
        $clean['display_mode']   = sfn_sanitize_display_mode( $input['display_mode'] ?? 'classic' );
        $clean['numbering_type'] = sfn_sanitize_numbering_type( $input['numbering_type'] ?? 'numeric' );
        $clean['label']          = sanitize_text_field( $input['label'] ?? '' );
        $clean['show_label']     = ! empty( $input['show_label'] );
        $clean['combine_duplicates'] = ! empty( $input['combine_duplicates'] );
        // PRO label styling
        $clean['label_font_size'] = sanitize_text_field( $input['label_font_size'] ?? '' );
        $clean['label_color']     = sanitize_hex_color( $input['label_color'] ?? '' ) ?? '';
        $clean['label_align']     = in_array( $input['label_align'] ?? 'left', [ 'left', 'center', 'right' ], true )
                                    ? $input['label_align'] : 'left';
        $clean['label_uppercase'] = ! empty( $input['label_uppercase'] );
        $clean['theme']     = sanitize_text_field( $input['theme'] ?? 'minimal' );
        $clean['custom_theme_enabled'] = ! empty( $input['custom_theme_enabled'] );
        $raw_styles = is_array( $input['theme_styles'] ?? null ) ? $input['theme_styles'] : [];
        $clean['theme_styles'] = [
            'background_color'  => sanitize_hex_color( $raw_styles['background_color']  ?? '#ffffff' )  ?? '#ffffff',
            'text_color'        => sanitize_hex_color( $raw_styles['text_color']        ?? '#1a1a2e' )  ?? '#1a1a2e',
            'text_muted_color'  => sanitize_hex_color( $raw_styles['text_muted_color']  ?? '#6b6b8a' )  ?? '#6b6b8a',
            'primary_color'     => sanitize_hex_color( $raw_styles['primary_color']     ?? '#534ab7' )  ?? '#534ab7',
            'link_color'        => sanitize_hex_color( $raw_styles['link_color']        ?? '#534ab7' )  ?? '#534ab7',
            'border_color'      => sanitize_hex_color( $raw_styles['border_color']      ?? '#e0ddf8' )  ?? '#e0ddf8',
            'border_radius'     => sfn_sanitize_css_unit( $raw_styles['border_radius']   ?? '8px' ),
            'border_width'      => sfn_sanitize_css_unit( $raw_styles['border_width']    ?? '1px' ),
            'divider_style'     => in_array( $raw_styles['divider_style'] ?? 'solid', [ 'solid', 'dashed', 'none' ], true )
                                    ? $raw_styles['divider_style'] : 'solid',
            'padding'           => sfn_sanitize_css_unit( $raw_styles['padding']         ?? '16px' ),
            'margin_top'        => sfn_sanitize_css_unit( $raw_styles['margin_top']      ?? '2.5rem' ),
            'list_spacing'      => sfn_sanitize_css_unit( $raw_styles['list_spacing']    ?? '6px' ),
            'font_size'         => sfn_sanitize_css_unit( $raw_styles['font_size']       ?? '14px' ),
            'line_height'       => sfn_sanitize_css_unit( $raw_styles['line_height']     ?? '1.6', true ),
            'font_family'       => sanitize_text_field( $raw_styles['font_family']       ?? '' ),
            'padding_mobile'    => sfn_sanitize_css_unit( $raw_styles['padding_mobile']  ?? '12px' ),
        ];
        $clean['enable_analytics']      = ! empty( $input['enable_analytics'] );
        $clean['enable_reusable_notes'] = ! empty( $input['enable_reusable_notes'] );

        return $clean;
    }

    // ── Field renderers ───────────────────────────────────────────────────────

    public function field_display_mode(): void {
        $val = $this->get_display_mode();
        $options = [
            'classic'   => __( 'Classic (bottom list)', 'smart-footnotes' ),
            'tooltip'   => __( 'Tooltip',               'smart-footnotes' ),
            'inline'    => __( 'Inline expand',         'smart-footnotes' ),
            'sidepanel' => __( 'Side panel',            'smart-footnotes' ),
        ];
        echo '<select name="sfn_settings[display_mode]" class="sfn-select">';
        foreach ( $options as $key => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $key ),
                selected( $val, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }

    public function field_numbering_type(): void {
        $val = $this->get_numbering_type();
        $options = [
            'numeric' => __( 'Numeric (1, 2, 3)',    'smart-footnotes' ),
            'roman'   => __( 'Roman (i, ii, iii)',   'smart-footnotes' ),
            'alpha'   => __( 'Alphabetic (a, b, c)', 'smart-footnotes' ),
            'symbol'  => __( 'Symbol (†, ‡, §)',      'smart-footnotes' ),
        ];
        echo '<select name="sfn_settings[numbering_type]" class="sfn-select">';
        foreach ( $options as $key => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $key ),
                selected( $val, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }

    public function field_label(): void {
        $val = esc_attr( $this->get( 'label', '' ) );
        printf(
            '<input type="text" name="sfn_settings[label]" value="%s" placeholder="%s" class="sfn-input">',
            esc_attr( $val ),
            esc_attr__( 'Footnotes', 'smart-footnotes' )
        );
        echo '<div class="sfn-field__hint">'
            . esc_html__( 'Heading displayed above the footnotes list. Leave blank to use the default.', 'smart-footnotes' )
            . '</div>';
    }

    public function field_show_label(): void {
        $checked = $this->get_show_label();
        printf(
            '<label class="sfn-toggle"><input type="checkbox" name="sfn_settings[show_label]" value="1"%s>'
            . '<div><div class="sfn-toggle__text">%s</div></div></label>',
            checked( $checked, true, false ),
            esc_html__( 'Display label above footnotes', 'smart-footnotes' )
        );
    }

    public function field_combine_duplicates(): void {
        $checked = $this->get_combine_duplicates();
        printf(
            '<label class="sfn-toggle"><input type="checkbox" name="sfn_settings[combine_duplicates]" value="1"%s>'
            . '<div><div class="sfn-toggle__text">%s</div><div class="sfn-toggle__sub">%s</div></div></label>',
            checked( $checked, true, false ),
            esc_html__( 'Combine duplicate footnotes', 'smart-footnotes' ),
            esc_html__( 'Identical footnotes will share the same number.', 'smart-footnotes' )
        );
    }

    public function field_theme(): void {
        $val     = $this->get_theme();
        $options = [ 'minimal', 'modern', 'academic' ];
        echo '<select name="sfn_settings[theme]" class="sfn-select">';
        foreach ( $options as $theme ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $theme ),
                selected( $val, $theme, false ),
                esc_html( ucfirst( $theme ) )
            );
        }
        echo '</select>';
    }
}
