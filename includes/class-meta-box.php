<?php
/**
 * Per-post meta box for Smart Footnotes Pro (PRO).
 *
 * Registers a sidebar meta box on post/page edit screens that lets
 * editors override the global theme, label, and display mode per post.
 *
 * Post meta keys:
 *   _sfn_label_override   — string, overrides global label for this post
 *   _sfn_theme_override   — string, overrides global theme for this post
 *   _sfn_mode_override    — string, overrides global display mode for this post
 *
 * @package SmartFootnotesPro
 */

defined( 'ABSPATH' ) || exit;

class SFN_Meta_Box {

    private static ?SFN_Meta_Box $instance = null;

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'add_meta_boxes',  [ $this, 'register' ] );
        add_action( 'save_post',       [ $this, 'save' ], 10, 2 );
        // Gutenberg: also handle REST saves via block editor.
        add_action( 'rest_after_insert_post', [ $this, 'save_rest' ], 10, 2 );
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function register(): void {
        $post_types = apply_filters( 'sfn_meta_box_post_types', [ 'post', 'page' ] );

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'sfn-post-settings',
                __( 'Smart Footnotes', 'smart-footnotes' ),
                [ $this, 'render' ],
                $pt,
                'side',
                'default'
            );
        }
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render( WP_Post $post ): void {
        wp_nonce_field( 'sfn_meta_box_save', 'sfn_meta_box_nonce' );

        $settings     = SFN_Settings::get_instance();
        $label_val    = get_post_meta( $post->ID, '_sfn_label_override',  true );
        $theme_val    = get_post_meta( $post->ID, '_sfn_theme_override',  true );
        $mode_val     = get_post_meta( $post->ID, '_sfn_mode_override',   true );

        $global_label = $settings->get_label();
        $global_theme = $settings->get_theme();
        $global_mode  = $settings->get_display_mode();

        $themes = [
            /* translators: %s: current global theme name */
            ''         => sprintf( __( '— Global (%s) —', 'smart-footnotes' ), ucfirst( $global_theme ) ),
            'minimal'  => __( 'Minimal',  'smart-footnotes' ),
            'modern'   => __( 'Modern',   'smart-footnotes' ),
            'academic' => __( 'Academic', 'smart-footnotes' ),
        ];

        $modes = [
            /* translators: %s: current global display mode name */
            ''          => sprintf( __( '— Global (%s) —', 'smart-footnotes' ), ucfirst( $global_mode ) ),
            'classic'   => __( 'Classic (bottom list)', 'smart-footnotes' ),
            'tooltip'   => __( 'Tooltip',               'smart-footnotes' ),
            'inline'    => __( 'Inline expand',          'smart-footnotes' ),
            'sidepanel' => __( 'Side panel',             'smart-footnotes' ),
        ];
        ?>
        <div class="sfn-meta-box">
            <style>
                .sfn-meta-box label { display:block; font-weight:600; margin:10px 0 3px; font-size:12px; }
                .sfn-meta-box input[type=text],
                .sfn-meta-box select { width:100%; }
                .sfn-meta-box .sfn-meta-hint { font-size:11px; color:#888; margin-top:3px; }
                .sfn-meta-box .sfn-meta-section { border-top:1px solid #eee; padding-top:10px; margin-top:10px; }
                .sfn-meta-box .sfn-meta-section:first-child { border-top:none; padding-top:0; margin-top:0; }
            </style>

            <div class="sfn-meta-section">
                <label for="sfn_label_override"><?php esc_html_e( 'Section label', 'smart-footnotes' ); ?></label>
                <input
                    type="text"
                    id="sfn_label_override"
                    name="sfn_label_override"
                    value="<?php echo esc_attr( $label_val ); ?>"
                    placeholder="<?php echo esc_attr( $global_label ); ?>"
                >
                <p class="sfn-meta-hint"><?php esc_html_e( 'Leave blank to use the global label.', 'smart-footnotes' ); ?></p>
            </div>

            <div class="sfn-meta-section">
                <label for="sfn_theme_override"><?php esc_html_e( 'Theme', 'smart-footnotes' ); ?></label>
                <select id="sfn_theme_override" name="sfn_theme_override">
                    <?php foreach ( $themes as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $theme_val, $val ); ?>>
                            <?php echo esc_html( $lbl ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sfn-meta-section">
                <label for="sfn_mode_override"><?php esc_html_e( 'Display mode', 'smart-footnotes' ); ?></label>
                <select id="sfn_mode_override" name="sfn_mode_override">
                    <?php foreach ( $modes as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $mode_val, $val ); ?>>
                            <?php echo esc_html( $lbl ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="sfn-meta-hint"><?php esc_html_e( 'Overrides block-level settings on this post.', 'smart-footnotes' ); ?></p>
            </div>
        </div>
        <?php
    }

    // ── Save (Classic editor + quick-edit) ────────────────────────────────────

    public function save( int $post_id, WP_Post $post ): void {
        // Verify nonce.
        if ( ! isset( $_POST['sfn_meta_box_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sfn_meta_box_nonce'] ) ), 'sfn_meta_box_save' )
        ) {
            return;
        }

        // Bail on autosave, revisions, and insufficient permissions.
        if ( wp_is_post_autosave( $post_id )
            || wp_is_post_revision( $post_id )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        $this->persist( $post_id );
    }

    // ── Save (Gutenberg REST) ─────────────────────────────────────────────────

    public function save_rest( WP_Post $post, WP_REST_Request $request ): void {
        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            return;
        }

        $meta = $request->get_param( 'meta' );
        if ( ! is_array( $meta ) ) {
            return;
        }

        foreach ( [ '_sfn_label_override', '_sfn_theme_override', '_sfn_mode_override' ] as $key ) {
            if ( array_key_exists( $key, $meta ) ) {
                update_post_meta( $post->ID, $key, sanitize_text_field( $meta[ $key ] ) );
            }
        }
    }

    // ── Persist helpers ───────────────────────────────────────────────────────

    private function persist( int $post_id ): void {
        // Nonce already verified in save() before calling this method.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $fields = [
            'sfn_label_override' => '_sfn_label_override',
            'sfn_theme_override' => '_sfn_theme_override',
            'sfn_mode_override'  => '_sfn_mode_override',
        ];

        foreach ( $fields as $post_key => $meta_key ) {
            $value = isset( $_POST[ $post_key ] )
                ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) )
                : '';

            if ( $value !== '' ) {
                update_post_meta( $post_id, $meta_key, $value );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
}
