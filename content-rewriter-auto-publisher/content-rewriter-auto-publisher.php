<?php
/**
 * Plugin Name: Content Rewriter & Auto-Publisher
 * Plugin URI: https://example.com/
 * Description: An advanced WordPress plugin that uses AI to extract, rewrite, and publish content from source links.
 * Version: 1.1.0
 * Author: Jules
 * Author URI: https://example.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: content-rewriter-auto-publisher
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'CRWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once CRWP_PLUGIN_DIR . 'includes/settings.php';
require_once CRWP_PLUGIN_DIR . 'includes/processing.php';
require_once CRWP_PLUGIN_DIR . 'includes/logs.php';
require_once CRWP_PLUGIN_DIR . 'includes/scheduling.php';

register_activation_hook( __FILE__, 'crwp_activate' );
register_deactivation_hook( __FILE__, 'crwp_deactivate' );
