<?php
/**
 * Plugin Name: Khabaryar
 * Plugin URI:  https://example.com/
 * Description: An advanced news aggregator and content processing plugin using AI.
 * Version:     1.0.0
 * Author:      Jules
 * Author URI:  https://example.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: khabaryar
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'KHABARYAR_VERSION', '1.1.0' );
define( 'KHABARYAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Load plugin textdomain for translation.
 */
function khabaryar_load_textdomain() {
    load_plugin_textdomain( 'khabaryar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'khabaryar_load_textdomain' );

// Include the files
require_once KHABARYAR_PLUGIN_DIR . 'includes/cpt.php';
require_once KHABARYAR_PLUGIN_DIR . 'includes/settings.php';
require_once KHABARYAR_PLUGIN_DIR . 'includes/cron.php';
require_once KHABARYAR_PLUGIN_DIR . 'includes/logging.php';
require_once KHABARYAR_PLUGIN_DIR . 'includes/processing.php';
