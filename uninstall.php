<?php
/**
 * Uninstall script for Smart Footnotes (free).
 *
 * @package SmartFootnotes
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sfn_settings' );
