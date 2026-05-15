<?php
/**
 * Admin settings page for Smart Footnotes.
 *
 * @package SmartFootnotes
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-footnotes' ) );
}

$settings   = SFN_Settings::get_instance();
$is_pro     = sfn_is_pro_active();
// settings-updated is set by WordPress options.php after a successful save.
// It is a boolean flag, not user input — no nonce needed here; the form
// submission itself is nonce-verified by settings_fields() / options.php.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$saved = isset( $_GET['settings-updated'] ) && (bool) sanitize_key( wp_unslash( $_GET['settings-updated'] ) );
?>
<style>
/* ── Reset inside .sfn-wrap ───────────────────────────────── */
.sfn-wrap * { box-sizing: border-box; }
.sfn-wrap { max-width: 960px; margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

/* ── Page header ──────────────────────────────────────────── */
.sfn-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 24px 0 20px; border-bottom: 1px solid #e5e7eb; margin-bottom: 28px;
}
.sfn-header__left { display: flex; align-items: center; gap: 12px; }
.sfn-header__icon {
    width: 38px; height: 38px; background: #534ab7; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
}
.sfn-header__icon svg { width: 20px; height: 20px; fill: #fff; }
.sfn-header__title { font-size: 20px; font-weight: 700; color: #111827; margin: 0; line-height: 1.2; }
.sfn-header__version { font-size: 11px; color: #9ca3af; font-weight: 400; margin-left: 6px; }
.sfn-header__right { display: flex; gap: 10px; align-items: center; }

/* ── Pro badge / upgrade button ───────────────────────────── */
.sfn-badge-pro {
    display: inline-flex; align-items: center; gap: 5px;
    background: linear-gradient(135deg,#534ab7,#7c6ee0);
    color: #fff; font-size: 11px; font-weight: 600;
    padding: 4px 10px; border-radius: 20px; letter-spacing: .04em; text-transform: uppercase;
}
.sfn-upgrade-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: linear-gradient(135deg,#534ab7,#7c6ee0);
    color: #fff !important; font-size: 13px; font-weight: 500;
    padding: 8px 16px; border-radius: 8px; text-decoration: none;
    box-shadow: 0 1px 3px rgba(83,74,183,.3); transition: opacity .15s;
}
.sfn-upgrade-btn:hover { opacity: .9; color: #fff !important; }

/* ── Saved notice ─────────────────────────────────────────── */
.sfn-saved {
    display: flex; align-items: center; gap: 8px;
    background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 8px;
    padding: 10px 16px; margin-bottom: 24px; font-size: 13px; color: #065f46;
}
.sfn-saved svg { width: 16px; height: 16px; flex-shrink: 0; }

/* ── Grid ─────────────────────────────────────────────────── */
.sfn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.sfn-grid--full { grid-column: 1 / -1; }

/* ── Cards ────────────────────────────────────────────────── */
.sfn-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.sfn-card__header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid #f3f4f6; background: #fafafa;
}
.sfn-card__title {
    font-size: 13px; font-weight: 600; color: #111827; margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.sfn-card__title-icon {
    width: 24px; height: 24px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sfn-card__body { padding: 20px; }

/* ── Field rows ───────────────────────────────────────────── */
.sfn-field { margin-bottom: 18px; }
.sfn-field:last-child { margin-bottom: 0; }
.sfn-field__label {
    display: block; font-size: 12px; font-weight: 600; color: #374151;
    margin-bottom: 6px; letter-spacing: .01em;
}
.sfn-field__hint { font-size: 11px; color: #9ca3af; margin-top: 4px; line-height: 1.5; }

/* ── Inputs ───────────────────────────────────────────────── */
.sfn-input, .sfn-select {
    width: 100%; padding: 8px 10px; font-size: 13px;
    border: 1px solid #d1d5db; border-radius: 7px; color: #111827;
    background: #fff; transition: border-color .15s, box-shadow .15s;
    font-family: inherit;
}
.sfn-input:focus, .sfn-select:focus {
    outline: none; border-color: #534ab7;
    box-shadow: 0 0 0 3px rgba(83,74,183,.12);
}
.sfn-input--sm { width: 90px; }
.sfn-input--num { width: 75px; }

/* ── Toggle / checkbox ────────────────────────────────────── */
.sfn-toggle { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; }
.sfn-toggle input[type=checkbox] { margin-top: 1px; width: 16px; height: 16px; cursor: pointer; accent-color: #534ab7; flex-shrink: 0; }
.sfn-toggle__text { font-size: 13px; color: #374151; line-height: 1.5; }
.sfn-toggle__sub  { font-size: 11px; color: #9ca3af; margin-top: 2px; }

/* ── Color row ────────────────────────────────────────────── */
.sfn-color-row { display: flex; align-items: center; gap: 10px; }
.sfn-color-row input[type=color] {
    width: 36px; height: 36px; padding: 2px; border: 1px solid #d1d5db;
    border-radius: 7px; cursor: pointer; background: #fff;
}
.sfn-color-row__label { font-size: 13px; color: #374151; }

/* ── PRO lock overlay on free ─────────────────────────────── */
.sfn-pro-lock {
    display: flex; align-items: center; gap: 8px; padding: 10px 14px;
    background: #f5f3ff; border: 1px solid #ede9fe; border-radius: 8px;
    font-size: 12px; color: #5b21b6; margin-top: 2px;
}
.sfn-pro-lock a { color: #534ab7; font-weight: 600; }

/* ── Three-col inside card ────────────────────────────────── */
.sfn-cols { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; }
@media (max-width: 780px) { .sfn-cols { grid-template-columns: 1fr; } }

/* ── Divider ──────────────────────────────────────────────── */
.sfn-divider { border: none; border-top: 1px solid #f3f4f6; margin: 16px 0; }

/* ── Disabled layer ───────────────────────────────────────── */
.sfn-disabled { opacity: .45; pointer-events: none; }

/* ── Save bar ─────────────────────────────────────────────── */
.sfn-save-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 28px; padding: 16px 20px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.sfn-save-bar__note { font-size: 12px; color: #9ca3af; }
.sfn-save-bar__actions { display: flex; gap: 10px; align-items: center; }
.sfn-save-btn {
    background: linear-gradient(135deg,#534ab7,#7c6ee0);
    color: #fff; border: none; border-radius: 8px;
    padding: 10px 24px; font-size: 13px; font-weight: 600;
    cursor: pointer; font-family: inherit;
    box-shadow: 0 1px 3px rgba(83,74,183,.3); transition: opacity .15s;
}
.sfn-save-btn:hover { opacity: .9; }
.sfn-reset-btn {
    background: #fff; color: #6b7280; border: 1px solid #d1d5db;
    border-radius: 8px; padding: 10px 18px; font-size: 13px; font-weight: 500;
    cursor: pointer; font-family: inherit; transition: all .15s;
}
.sfn-reset-btn:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }
</style>

<div class="sfn-wrap">

    <!-- Header -->
    <div class="sfn-header">
        <div class="sfn-header__left">
            <div class="sfn-header__icon">
                <svg viewBox="0 0 20 20"><path d="M4 3h12a1 1 0 011 1v2H3V4a1 1 0 011-1zm-1 5h14v9a1 1 0 01-1 1H4a1 1 0 01-1-1V8zm4 3a.75.75 0 000 1.5h6a.75.75 0 000-1.5H7zm0 3a.75.75 0 000 1.5h4a.75.75 0 000-1.5H7z"/></svg>
            </div>
            <h1 class="sfn-header__title">
                Smart Footnotes
                <span class="sfn-header__version">v<?php echo esc_html( SFN_VERSION ); ?></span>
            </h1>
        </div>
        <div class="sfn-header__right">
            <?php if ( $is_pro ) : ?>
                <span class="sfn-badge-pro">
                    <svg width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    PRO Active
                </span>
            <?php else : ?>
                <a href="https://example.com/smart-footnotes-pro/upgrade" target="_blank" class="sfn-upgrade-btn">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    Upgrade to PRO
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $saved ) : ?>
    <div class="sfn-saved">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <?php esc_html_e( 'Settings saved successfully.', 'smart-footnotes' ); ?>
    </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'sfn_settings_group' ); wp_nonce_field( 'sfn_settings_action', 'sfn_nonce' ); ?>

        <!-- ── How to use ── -->
        <div class="sfn-card sfn-grid--full" style="margin-bottom:20px">
            <div class="sfn-card__header">
                <h2 class="sfn-card__title">
                    <span class="sfn-card__title-icon" style="background:#dcfce7">
                        <svg width="13" height="13" viewBox="0 0 20 20" fill="#16a34a"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    </span>
                    How to use
                </h2>
            </div>
            <div class="sfn-card__body">
                <p style="font-size:13px;color:#374151;margin:0 0 12px;line-height:1.6">Wrap your footnote content inline using the shortcode — works in the Classic Editor, page builders, and any text field that supports shortcodes.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px">
                    <div>
                        <div style="font-size:11px;color:#9ca3af;margin-bottom:4px">Basic</div>
                        <code style="display:block;background:#f3f4f6;border-radius:6px;padding:8px 12px;font-size:12px;color:#111827">[sfn]Your footnote here[/sfn]</code>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#9ca3af;margin-bottom:4px">With options</div>
                        <code style="display:block;background:#f3f4f6;border-radius:6px;padding:8px 12px;font-size:12px;color:#111827">[sfn mode="tooltip" numbering="roman"]Note[/sfn]</code>
                    </div>
                </div>
                <div style="font-size:12px;color:#9ca3af">
                    <strong style="font-weight:600;color:#374151">mode:</strong> classic · tooltip · inline · sidepanel &nbsp;|&nbsp;
                    <strong style="font-weight:600;color:#374151">numbering:</strong> numeric · roman · alpha · symbol
                </div>
            </div>
        </div>

        <div class="sfn-grid">

            <!-- ── General ── -->
            <div class="sfn-card">
                <div class="sfn-card__header">
                    <h2 class="sfn-card__title">
                        <span class="sfn-card__title-icon" style="background:#ede9fe">
                            <svg width="13" height="13" viewBox="0 0 20 20" fill="#534ab7"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                        </span>
                        General
                    </h2>
                </div>
                <div class="sfn-card__body">
                    <div class="sfn-field">
                        <label class="sfn-field__label">Default display mode</label>
                        <?php $settings->field_display_mode(); ?>
                    </div>
                    <div class="sfn-field">
                        <label class="sfn-field__label">Numbering type</label>
                        <?php $settings->field_numbering_type(); ?>
                    </div>
                    <hr class="sfn-divider">
                    <div class="sfn-field">
                        <label class="sfn-field__label">Footnotes label</label>
                        <?php $settings->field_label(); ?>
                    </div>
                    <div class="sfn-field">
                        <?php $settings->field_show_label(); ?>
                    </div>
                    <hr class="sfn-divider">
                    <div class="sfn-field">
                        <?php $settings->field_combine_duplicates(); ?>
                    </div>
                </div>
            </div>

            <!-- ── Design ── -->
            <?php
                $lfs = esc_attr( $settings->get('label_font_size','') );
                $lc  = esc_attr( $settings->get('label_color','') );
                $la  = $settings->get('label_align','left');
                $lu  = $settings->get('label_uppercase', false);
            ?>
            <div class="sfn-card">
                <div class="sfn-card__header">
                    <h2 class="sfn-card__title">
                        <span class="sfn-card__title-icon" style="background:#fef3c7">
                            <svg width="13" height="13" viewBox="0 0 20 20" fill="#d97706"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
                        </span>
                        Design
                    </h2>
                </div>
                <div class="sfn-card__body">
                    <div class="sfn-field">
                        <label class="sfn-field__label">Theme preset</label>
                        <?php $settings->field_theme(); ?>
                    </div>
                    <hr class="sfn-divider">
                    <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:12px">Label styling</div>
                    <div class="sfn-field">
                        <label class="sfn-field__label">Font size</label>
                        <input type="text" name="sfn_settings[label_font_size]" value="<?php echo esc_attr( $lfs ); ?>" placeholder="16px" class="sfn-input sfn-input--sm">
                        <div class="sfn-field__hint">e.g. 16px or 1.2em</div>
                    </div>
                    <div class="sfn-field">
                        <label class="sfn-field__label">Color</label>
                        <div class="sfn-color-row">
                            <input type="color" name="sfn_settings[label_color]" value="<?php echo esc_attr( $lc ?: '#111827' ); ?>">
                            <span class="sfn-color-row__label">Label text color</span>
                        </div>
                    </div>
                    <div class="sfn-field">
                        <label class="sfn-field__label">Alignment</label>
                        <select name="sfn_settings[label_align]" class="sfn-select">
                            <?php foreach ( ['left'=>'Left','center'=>'Center','right'=>'Right'] as $v=>$l ) : ?>
                            <option value="<?php echo esc_attr( $v ); ?>" <?php selected($la,$v); ?>><?php echo esc_html( $l ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sfn-field">
                        <label class="sfn-toggle">
                            <input type="checkbox" name="sfn_settings[label_uppercase]" value="1" <?php checked($lu); ?>>
                            <div><div class="sfn-toggle__text">Uppercase label</div></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- ── Theme customiser ── -->
            <?php $ts = $settings->get_theme_styles(); ?>
            <div class="sfn-card sfn-grid--full">
                <div class="sfn-card__header">
                    <h2 class="sfn-card__title">
                        <span class="sfn-card__title-icon" style="background:#fef3c7">
                            <svg width="13" height="13" viewBox="0 0 20 20" fill="#d97706"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                        </span>
                        Theme customiser
                    </h2>
                </div>
                <div class="sfn-card__body">
                    <div class="sfn-cols">
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:12px">Colors</div>
                            <?php
                            $color_fields = [
                                'background_color' => 'Background',
                                'text_color'       => 'Body text',
                                'text_muted_color' => 'Muted text',
                                'primary_color'    => 'Primary (markers, accents)',
                                'link_color'       => 'Links',
                                'border_color'     => 'Border / divider',
                            ];
                            foreach ( $color_fields as $key => $label ) : ?>
                            <div class="sfn-field">
                                <div class="sfn-color-row">
                                    <input type="color" name="sfn_settings[theme_styles][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($ts[$key] ?? '#000000'); ?>">
                                    <span class="sfn-color-row__label"><?php echo esc_html($label); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:12px">Typography</div>
                            <div class="sfn-field">
                                <label class="sfn-field__label">Font size</label>
                                <input type="text" name="sfn_settings[theme_styles][font_size]" value="<?php echo esc_attr($ts['font_size']); ?>" placeholder="14px" class="sfn-input sfn-input--sm">
                                <div class="sfn-field__hint">Default: 14px</div>
                            </div>
                            <div class="sfn-field">
                                <label class="sfn-field__label">Line height</label>
                                <input type="text" name="sfn_settings[theme_styles][line_height]" value="<?php echo esc_attr($ts['line_height']); ?>" placeholder="1.6" class="sfn-input sfn-input--sm">
                            </div>
                            <div class="sfn-field">
                                <label class="sfn-field__label">Font family</label>
                                <?php
                                $font_families = [
                                    ''                                          => 'Inherit from theme',
                                    'system-ui,-apple-system,sans-serif'        => 'System UI (sans-serif)',
                                    'Georgia,"Times New Roman",serif'           => 'Georgia (serif)',
                                    '"Palatino Linotype",Palatino,serif'        => 'Palatino (serif)',
                                    '"Times New Roman",Times,serif'             => 'Times New Roman (serif)',
                                    'Arial,Helvetica,sans-serif'                => 'Arial (sans-serif)',
                                    '"Helvetica Neue",Helvetica,sans-serif'     => 'Helvetica Neue (sans-serif)',
                                    '"Trebuchet MS",Trebuchet,sans-serif'       => 'Trebuchet MS (sans-serif)',
                                    'Verdana,Geneva,sans-serif'                 => 'Verdana (sans-serif)',
                                    '"Courier New",Courier,monospace'           => 'Courier New (monospace)',
                                    '"Lucida Console",Monaco,monospace'         => 'Lucida Console (monospace)',
                                ];
                                $current_ff = $ts['font_family'] ?? '';
                                ?>
                                <select name="sfn_settings[theme_styles][font_family]" class="sfn-select">
                                    <?php foreach ( $font_families as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($current_ff, $val); ?>><?php echo esc_html($lbl); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:12px">Spacing &amp; shape</div>
                            <?php
                            $unit_fields = [
                                ['label'=>'Padding',         'key'=>'padding',       'ph'=>'16px'],
                                ['label'=>'Padding (mobile)','key'=>'padding_mobile','ph'=>'12px'],
                                ['label'=>'Border radius',   'key'=>'border_radius', 'ph'=>'8px'],
                                ['label'=>'Border width',    'key'=>'border_width',  'ph'=>'1px'],
                                ['label'=>'List spacing',    'key'=>'list_spacing',  'ph'=>'6px'],
                            ];
                            foreach ( $unit_fields as $uf ) : ?>
                            <div class="sfn-field">
                                <label class="sfn-field__label"><?php echo esc_html($uf['label']); ?></label>
                                <input type="text" name="sfn_settings[theme_styles][<?php echo esc_attr( $uf['key'] ); ?>]" value="<?php echo esc_attr($ts[$uf['key']]); ?>" placeholder="<?php echo esc_attr( $uf['ph'] ); ?>" class="sfn-input sfn-input--sm">
                            </div>
                            <?php endforeach; ?>
                            <div class="sfn-field">
                                <label class="sfn-field__label">Divider style</label>
                                <select name="sfn_settings[theme_styles][divider_style]" class="sfn-select">
                                    <?php foreach (['solid'=>'Solid','dashed'=>'Dashed','none'=>'None'] as $v=>$l) : ?>
                                    <option value="<?php echo esc_attr( $v ); ?>" <?php selected($ts['divider_style'],$v); ?>><?php echo esc_html( $l ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Advanced — Analytics + Reusable Notes (PRO only) ── -->
            <?php if ( $is_pro ) : ?>
            <div class="sfn-card sfn-grid--full">
                <div class="sfn-card__header">
                    <h2 class="sfn-card__title">
                        <span class="sfn-card__title-icon" style="background:#dcfce7">
                            <svg width="13" height="13" viewBox="0 0 20 20" fill="#16a34a"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        </span>
                        Advanced
                        <span class="sfn-badge-pro" style="font-size:9px;padding:2px 7px">PRO</span>
                    </h2>
                </div>
                <div class="sfn-card__body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                        <div class="sfn-field">
                            <label class="sfn-toggle">
                                <input type="checkbox" name="sfn_settings[enable_analytics]" value="1" <?php checked($settings->get('enable_analytics')); ?>>
                                <div>
                                    <div class="sfn-toggle__text">Enable analytics</div>
                                    <div class="sfn-toggle__sub">Track footnote clicks in the database</div>
                                </div>
                            </label>
                        </div>
                        <div class="sfn-field">
                            <label class="sfn-toggle">
                                <input type="checkbox" name="sfn_settings[enable_reusable_notes]" value="1" <?php checked($settings->get('enable_reusable_notes')); ?>>
                                <div>
                                    <div class="sfn-toggle__text">Reusable footnotes</div>
                                    <div class="sfn-toggle__sub">Enable the footnote library CPT</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; // is_pro ?>

        </div><!-- .sfn-grid -->

        <!-- Save bar -->
        <div class="sfn-save-bar">
            <span class="sfn-save-bar__note">Changes apply to all posts unless overridden per-post.</span>
            <div class="sfn-save-bar__actions">
                <button type="button" class="sfn-reset-btn" id="sfn-reset-btn">Reset to defaults</button>
                <button type="submit" class="sfn-save-btn">Save changes</button>
            </div>
        </div>

    </form>

    <!-- Hidden reset form — submits to options.php with blank/default values -->
    <form method="post" action="options.php" id="sfn-reset-form">
        <?php settings_fields( 'sfn_settings_group' ); ?>
        <input type="hidden" name="sfn_settings[display_mode]"   value="classic">
        <input type="hidden" name="sfn_settings[numbering_type]" value="numeric">
        <input type="hidden" name="sfn_settings[label]"          value="">
        <input type="hidden" name="sfn_settings[show_label]"     value="1">
        <input type="hidden" name="sfn_settings[combine_duplicates]" value="1">
        <input type="hidden" name="sfn_settings[label_font_size]" value="">
        <input type="hidden" name="sfn_settings[label_color]"    value="">
        <input type="hidden" name="sfn_settings[label_align]"    value="left">
        <input type="hidden" name="sfn_settings[theme]"          value="minimal">
    </form>
</div>

<script>
(function(){
    // Reset button — confirm before submitting
    var resetBtn = document.getElementById('sfn-reset-btn');
    var resetForm = document.getElementById('sfn-reset-form');
    if ( resetBtn && resetForm ) {
        resetBtn.addEventListener('click', function(){
            if ( window.confirm('Reset all settings to their defaults? This cannot be undone.') ) {
                resetForm.submit();
            }
        });
    }

    // Auto-apply sfn CSS classes to bare inputs/selects
    document.querySelectorAll('.sfn-wrap select').forEach(function(el){
        if(!el.classList.contains('sfn-select')) el.classList.add('sfn-select');
    });
    document.querySelectorAll('.sfn-wrap input[type=text], .sfn-wrap input[type=number]').forEach(function(el){
        if(!el.classList.contains('sfn-input')) el.classList.add('sfn-input');
    });
})();
</script>
