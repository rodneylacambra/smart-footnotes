<?php
/**
 * Plugin Name: Smart Footnotes
 * Plugin URI:  https://wordpress.org/plugins/smart-footnotes/
 * Description: A powerful and modern WordPress footnotes plugin with tooltip footnotes, inline expandable notes, duplicate footnote detection, custom numbering styles, and customizable themes.
 * Version:     1.0.0
 * Author:      Rodney Lacambra
 * Author URI:  https://profiles.wordpress.org/rodneylacambra/
 * License:     GPL-2.0-or-later
 * Text Domain: smart-footnotes
 *
 * @package SmartFootnotes
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ─────────────────────────────────────────────────────────────────
define( 'SFN_VERSION',     '1.0.0' );
define( 'SFN_PLUGIN_FILE', __FILE__ );
define( 'SFN_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SFN_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SFN_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// ── Core includes ─────────────────────────────────────────────────────────────
require_once SFN_PLUGIN_DIR . 'includes/helpers.php';
require_once SFN_PLUGIN_DIR . 'includes/class-settings.php';
require_once SFN_PLUGIN_DIR . 'includes/class-renderer.php';
require_once SFN_PLUGIN_DIR . 'includes/class-license.php';
require_once SFN_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once SFN_PLUGIN_DIR . 'includes/class-loader.php';

// ── Boot ──────────────────────────────────────────────────────────────────────
function sfn_init() {
    SFN_Loader::get_instance()->init();
}
add_action( 'plugins_loaded', 'sfn_init' );

// ── Activation / deactivation ─────────────────────────────────────────────────
register_activation_hook( __FILE__, 'sfn_activate' );
register_deactivation_hook( __FILE__, 'sfn_deactivate' );

function sfn_activate() {
    SFN_Settings::get_instance()->set_defaults();
    flush_rewrite_rules();
}

function sfn_deactivate() {
    flush_rewrite_rules();
}
