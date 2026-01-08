<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'KHABARYAR_LOG_DIR', KHABARYAR_PLUGIN_DIR . 'logs/' );
define( 'KHABARYAR_LOG_FILE', KHABARYAR_LOG_DIR . 'khabaryar.log' );

/**
 * Logs a message to the Khabaryar log file.
 */
function khabaryar_log( $message ) {
    if ( ! is_dir( KHABARYAR_LOG_DIR ) ) {
        wp_mkdir_p( KHABARYAR_LOG_DIR );
    }

    $timestamp = date_i18n( 'Y-m-d H:i:s' );
    $log_entry = "[" . $timestamp . "] " . $message . "\n";

    file_put_contents( KHABARYAR_LOG_FILE, $log_entry, FILE_APPEND );
}

/**
 * Adds a "Logs" tab to the settings page.
 */
function khabaryar_add_logs_tab( $tabs ) {
    $tabs['logs'] = __( 'Logs', 'khabaryar' );
    return $tabs;
}
// This is a placeholder for a future tabbed interface. For now, we'll add it to the main page.

/**
 * Renders the log viewer on the settings page.
 */
function khabaryar_render_log_viewer() {
    echo '<h2>' . __( 'Activity Log', 'khabaryar' ) . '</h2>';
    if ( file_exists( KHABARYAR_LOG_FILE ) ) {
        $log_content = file_get_contents( KHABARYAR_LOG_FILE );
        echo '<textarea readonly="readonly" style="width: 100%; height: 300px; background: #f1f1f1;">' . esc_textarea( $log_content ) . '</textarea>';
    } else {
        echo '<p>' . __( 'No log entries yet.', 'khabaryar' ) . '</p>';
    }
}
