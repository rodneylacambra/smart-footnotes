<?php
/**
 * Global helper functions for Smart Footnotes Pro.
 *
 * @package SmartFootnotesPro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns true when the PRO addon is active and licensed.
 */
function sfn_is_pro_active(): bool {
    return apply_filters( 'sfn_is_pro_active', false );
}

/**
 * Sanitize a CSS unit value (e.g. 8px, 1.6em, 2.5rem, 16px).
 * Allows unitless numbers when $allow_unitless is true (for line-height).
 * Returns the fallback if the value is invalid.
 *
 * @param mixed  $value          Raw input value.
 * @param bool   $allow_unitless Allow plain numbers with no unit (e.g. line-height: 1.6).
 * @param string $fallback       Returned on validation failure.
 * @return string
 */
function sfn_sanitize_css_unit( mixed $value, bool $allow_unitless = false, string $fallback = '' ): string {
    $value = trim( (string) $value );

    // Unitless numeric (e.g. line-height: 1.6).
    if ( $allow_unitless && preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
        return $value;
    }

    // Number + allowed unit.
    if ( preg_match( '/^\d+(\.\d+)?(px|em|rem|%|vh|vw)$/', $value ) ) {
        return $value;
    }

    return $fallback;
}

/**
 * Normalize footnote content for deduplication comparison.
 *
 * Strips tags, trims whitespace, and lowercases so that footnotes
 * with trivial differences (case, extra spaces) are treated as identical.
 *
 * @param string $content Raw footnote content (may contain HTML).
 * @return string         Normalized string safe for md5() hashing.
 */
function sfn_normalize_footnote_content( string $content ): string {
    $content = wp_strip_all_tags( $content );
    $content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $content = strtolower( $content );
    $content = preg_replace( '/\s+/', ' ', $content );
    return trim( $content );
}

/**
 * Generate a display label for a footnote index.
 *
 * @param int    $index  1-based footnote index.
 * @param string $type   'numeric' | 'roman' | 'alpha' | 'symbol'
 * @return string
 */
function sfn_generate_label( int $index, string $type = 'numeric' ): string {
    switch ( $type ) {
        case 'roman':
            return sfn_to_roman( $index );

        case 'alpha':
            // a–z, then aa–az, etc.
            $label = '';
            $n     = $index;
            while ( $n > 0 ) {
                $n--;
                $label = chr( 97 + ( $n % 26 ) ) . $label;
                $n     = (int) floor( $n / 26 );
            }
            return $label;

        case 'symbol':
            $symbols = [ '†', '‡', '§', '¶', '‖', '**', '††', '‡‡' ];
            $i       = ( $index - 1 ) % count( $symbols );
            return $symbols[ $i ];

        default: // numeric
            return (string) $index;
    }
}

/**
 * Convert an integer to a lowercase Roman numeral string.
 *
 * @param int $number Positive integer.
 * @return string
 */
function sfn_to_roman( int $number ): string {
    $map = [
        1000 => 'm', 900 => 'cm', 500 => 'd', 400 => 'cd',
        100  => 'c', 90  => 'xc',  50 => 'l',  40 => 'xl',
        10   => 'x',  9  => 'ix',   5 => 'v',   4 => 'iv',
        1    => 'i',
    ];
    $result = '';
    foreach ( $map as $value => $numeral ) {
        while ( $number >= $value ) {
            $result .= $numeral;
            $number -= $value;
        }
    }
    return $result;
}

/**
 * Sanitize a display-mode string against allowed values.
 *
 * @param string $mode    Raw mode value.
 * @param string $default Fallback mode.
 * @return string
 */
function sfn_sanitize_display_mode( string $mode, string $default = 'classic' ): string {
    $allowed = [ 'classic', 'tooltip', 'inline', 'sidepanel' ];
    return in_array( $mode, $allowed, true ) ? $mode : $default;
}

/**
 * Sanitize a numbering-type string against allowed values.
 *
 * @param string $type    Raw type value.
 * @param string $default Fallback type.
 * @return string
 */
function sfn_sanitize_numbering_type( string $type, string $default = 'numeric' ): string {
    $allowed = [ 'numeric', 'roman', 'alpha', 'symbol' ];
    return in_array( $type, $allowed, true ) ? $type : $default;
}
