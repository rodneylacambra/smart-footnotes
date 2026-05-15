<?php
/**
 * Rendering engine for Smart Footnotes.
 *
 * Parses [sfn] shortcodes, assigns sequential numbering,
 * and injects the correct markup based on the active display mode.
 *
 * @package SmartFootnotes
 */

defined( 'ABSPATH' ) || exit;

class SFN_Renderer {

    private static ?SFN_Renderer $instance = null;

    /** Next available sequential index for this render pass. */
    private int $next_index = 1;

    /**
     * Deduplication map: md5( normalized content ) → assigned index.
     * Populated only when combine_duplicates is enabled.
     */
    private array $dedup_map = [];

    /** Collected footnotes for the current render pass. */
    private array $footnotes = [];

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Marker HTML ───────────────────────────────────────────────────────────

    private function build_marker(
        int    $index,
        string $note_id,
        string $label,
        string $mode,
        string $content,
        bool   $tooltip,
        string $ref_id = ''
    ): string {

        // Use provided ref_id (dedup path) or fall back to index-based id.
        $ref_id    = $ref_id ?: "sfn-ref-{$index}";
        $target_id = esc_attr( $note_id );
        $classes   = [ 'sfn-ref' ];

        if ( $tooltip ) {
            $classes[] = 'sfn-has-tooltip';
        }

        $class_attr = implode( ' ', $classes );
        $data_mode  = esc_attr( $mode );

        // Write data-content for any PRO display mode — tooltip.js reads it
        // for tooltip, inline-expand, mobile drawer, and side panel.
        // Also written when enableTooltip is explicitly on.
        $pro_modes    = [ 'tooltip', 'inline', 'sidepanel' ];
        $needs_content = $tooltip || in_array( $mode, $pro_modes, true );
        $data_content  = $needs_content
            ? ' data-content="' . esc_attr( wp_strip_all_tags( $content ) ) . '"'
            : '';

        return sprintf(
            '<sup id="%s" class="%s" data-mode="%s"%s>'
            . '<a href="#%s" aria-describedby="%s">%s</a>'
            . '</sup>',
            esc_attr( $ref_id ),
            $class_attr,
            $data_mode,
            $data_content,
            $target_id,
            $target_id,
            esc_html( $label )
        );
    }

    // ── Footnote list ─────────────────────────────────────────────────────────

    /**
     * Appends the footnote list to post content after all blocks are rendered.
     * Hooked to the_content with a late priority so all blocks run first.
     *
     * @param string $content The post content after block rendering.
     * @return string Modified content with footnote list appended.
     */
    public function append_footnote_list( string $content ): string {
        if ( empty( $this->footnotes ) || ! is_singular() ) {
            return $content;
        }

        $list_html = $this->build_footnote_list();
        $this->reset();

        $output = $content . $list_html;

        return apply_filters( 'sfn_render_output', $output, $list_html );
    }

    private function build_footnote_list(): string {
        $settings       = SFN_Settings::get_instance();
        $numbering_type = $settings->get_numbering_type();

        // Per-post overrides — now FREE.
        $post_id    = get_the_ID();
        $post_label = $post_id ? ( get_post_meta( $post_id, '_sfn_label_override', true ) ?: null ) : null;
        $post_theme = $post_id ? ( get_post_meta( $post_id, '_sfn_theme_override', true ) ?: null ) : null;
        $post_mode  = $post_id ? ( get_post_meta( $post_id, '_sfn_mode_override',  true ) ?: null ) : null;

        $label_text = $settings->get_effective_label( $numbering_type, $post_label );
        $show_label = $settings->get_show_label();

        // ── Theme classes ─────────────────────────────────────────────────────
        $active_theme = $post_theme ?? $settings->get_theme();
        if ( ! in_array( $active_theme, [ 'minimal', 'modern', 'academic' ], true ) ) {
            $active_theme = 'minimal';
        }

        $classes = [ 'sfn-footnotes', 'sfn-theme-' . $active_theme ];

        // ── Custom theme CSS variables (now FREE) ─────────────────────────────
        $inline_styles = $settings->get_inline_theme_styles( $post_theme );
        $style_attr    = $inline_styles ? ' style="' . $inline_styles . '"' : '';

        do_action( 'sfn_after_theme_applied', $settings->get_theme_styles() );

        // ── Label inline styles (now FREE) ────────────────────────────────────
        $label_style = '';
        $parts = [];
        $fs    = $settings->get( 'label_font_size', '' );
        $color = $settings->get( 'label_color', '' );
        $align = $settings->get( 'label_align', 'left' );
        $upper = $settings->get( 'label_uppercase', false );

        if ( $fs )                         $parts[] = 'font-size:'       . esc_attr( $fs );
        if ( $color )                      $parts[] = 'color:'           . esc_attr( $color );
        if ( $align && $align !== 'left' ) $parts[] = 'text-align:'     . esc_attr( $align );
        if ( $upper )                      $parts[] = 'text-transform:uppercase';

        $label_style = $parts ? ' style="' . implode( ';', $parts ) . '"' : '';

        $label_html = '';
        if ( $show_label ) {
            $label_html = sprintf(
                '<h3 class="sfn-label"%s>%s</h3>',
                $label_style,
                esc_html( $label_text )
            );
        }

        // ── Build list items ──────────────────────────────────────────────────
        $items = '';
        foreach ( $this->footnotes as $fn ) {
            $items .= $this->build_footnote_item( $fn );
            do_action( 'sfn_after_render', $fn['id'], $fn );
        }

        do_action( 'sfn_before_list', $this->footnotes );

        return sprintf(
            '<div class="%s"%s role="doc-endnotes" aria-label="%s" data-sfn-mode="%s">'
            . '<hr class="sfn-footnotes__divider">'
            . '%s'
            . '<ol class="sfn-footnotes__list">%s</ol>'
            . '</div>',
            esc_attr( implode( ' ', $classes ) ),
            $style_attr,
            esc_attr( $label_text ),
            esc_attr( $post_mode ?? $settings->get_display_mode() ),
            $label_html,
            $items
        );
    }

    private function build_footnote_item( array $fn ): string {
        $note_id    = esc_attr( $fn['id'] );
        $mode_class = 'sfn-footnote--' . esc_attr( $fn['mode'] );

        // Always render a single backlink pointing to the first (primary) reference.
        // All refs are stored internally for future PRO extensibility, but only
        // the first is shown — spec §3: single ↩, no multiple arrows.
        $primary_ref = $fn['refs'][0] ?? null;
        $back_link   = '';
        if ( $primary_ref ) {
            $back_link = sprintf(
                '<a class="sfn-footnote__back" href="#%s" aria-label="%s">&#x21A9;&#xFE0E;</a>',
                esc_attr( $primary_ref ),
                esc_attr__( 'Return to text', 'smart-footnotes' )
            );
        }

        return sprintf(
            '<li id="%s" class="sfn-footnote %s" role="doc-endnote">'
            . '<span class="sfn-footnote__label" aria-hidden="true">%s.</span>'
            . '<span class="sfn-footnote__body">%s</span>'
            . '%s'
            . '</li>',
            $note_id,
            $mode_class,
            esc_html( $fn['label'] ),
            wp_kses_post( $fn['content'] ),
            $back_link
        );
    }

    // ── Shortcode (Classic Editor fallback) ───────────────────────────────────

    /**
     * [sfn]Footnote content here[/sfn]
     */
    public function shortcode( array $atts, string $content = '' ): string {
        $atts = shortcode_atts(
            [
                'mode'      => null,
                'numbering' => null,
            ],
            $atts,
            'sfn'
        );

        $settings           = SFN_Settings::get_instance();
        $type               = sfn_sanitize_numbering_type( $atts['numbering'] ?? $settings->get_numbering_type() );
        $mode               = sfn_sanitize_display_mode( $atts['mode'] ?? $settings->get_display_mode() );
        $note_content       = wp_kses_post( do_shortcode( $content ) );
        $combine_duplicates = $settings->get_combine_duplicates();
        $dedup_key          = md5( sfn_normalize_footnote_content( $note_content ) );

        if ( $combine_duplicates && isset( $this->dedup_map[ $dedup_key ] ) ) {
            $index = $this->dedup_map[ $dedup_key ];
        } else {
            $index = $this->next_index;
            $this->dedup_map[ $dedup_key ] = $index;
            $this->next_index++;

            $label = sfn_generate_label( $index, $type );

            $this->footnotes[ $index ] = [
                'id'      => 'sfn-sc-' . $index,
                'index'   => $index,
                'label'   => $label,
                'content' => $note_content,
                'mode'    => $mode,
                'tooltip' => false,
                'refs'    => [],
            ];
        }

        $ref_id = 'sfn-ref-' . uniqid( '', false );
        $this->footnotes[ $index ]['refs'][] = $ref_id;

        $fn = $this->footnotes[ $index ];
        return $this->build_marker( $index, $fn['id'], $fn['label'], $mode, $note_content, false, $ref_id );
    }

    /**
     * Append shortcode-based footnote list. Call this at end of content.
     * Hooked to the_content at priority 20.
     */
    public function append_shortcode_list( string $content ): string {
        // Only run when shortcodes were used (not Gutenberg blocks path).
        if ( empty( $this->footnotes ) || ! is_singular() ) {
            return $content;
        }
        return $this->append_footnote_list( $content );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function reset(): void {
        $this->next_index = 1;
        $this->dedup_map  = [];
        $this->footnotes  = [];
    }
}
