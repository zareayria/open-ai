<?php
/**
 * Plugin Name: Invoice Form Plugin
 * Description: A plugin for creating a dynamic pro-forma invoice request form with seller management.
 * Version: 2.0
 * Author: Jules
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Activation hook
register_activation_hook(__FILE__, 'ifp_activate');

function ifp_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table for sellers
    $table_sellers = $wpdb->prefix . 'ifp_sellers';
    $sql_sellers = "CREATE TABLE $table_sellers (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        phone varchar(50) NOT NULL,
        photo_path varchar(255),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Table for dynamic form fields
    $table_fields = $wpdb->prefix . 'ifp_form_fields';
    $sql_fields = "CREATE TABLE $table_fields (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        field_label varchar(255) NOT NULL,
        field_name varchar(255) NOT NULL,
        field_type varchar(50) NOT NULL,
        field_order mediumint(9) DEFAULT 0,
        is_default BOOLEAN NOT NULL DEFAULT FALSE,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Table for invoice requests
    $table_requests = $wpdb->prefix . 'ifp_requests';
    $sql_requests = "CREATE TABLE $table_requests (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED,
        seller_id mediumint(9),
        form_data longtext,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Table for invoice request items
    $table_items = $wpdb->prefix . 'ifp_request_items';
    $sql_items = "CREATE TABLE $table_items (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        request_id mediumint(9) NOT NULL,
        product_name varchar(255) NOT NULL,
        quantity varchar(50) NOT NULL,
        description text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_sellers);
    dbDelta($sql_fields);
    dbDelta($sql_requests);
    dbDelta($sql_items);

    // Add default fields
    $default_fields = [
        ['field_label' => 'نام و نام خانوادگی', 'field_name' => 'customer_name', 'field_type' => 'text', 'is_default' => 1],
        ['field_label' => 'موبایل', 'field_name' => 'customer_mobile', 'field_type' => 'text', 'is_default' => 1],
        ['field_label' => 'عکس', 'field_name' => 'customer_photo', 'field_type' => 'file', 'is_default' => 1]
    ];

    foreach ($default_fields as $index => $field) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_fields WHERE field_name = %s", $field['field_name']));
        if (!$exists) {
            $wpdb->insert($table_fields, [
                'field_label' => $field['field_label'],
                'field_name' => $field['field_name'],
                'field_type' => $field['field_type'],
                'field_order' => $index + 1,
                'is_default' => $field['is_default']
            ]);
        }
    }
}

// Include other files
include_once(plugin_dir_path(__FILE__) . 'admin/menu.php');
include_once(plugin_dir_path(__FILE__) . 'public/shortcode.php');
include_once(plugin_dir_path(__FILE__) . 'includes/form-handler.php');
include_once(plugin_dir_path(__FILE__) . 'includes/ajax-handler.php');

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'ifp_enqueue_public_assets');
function ifp_enqueue_public_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'invoice_form')) {
        wp_enqueue_style('ifp-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), '2.0');
        wp_enqueue_script('ifp-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '2.0', true);
        wp_localize_script('ifp-script', 'ifp_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }
}

add_action('admin_enqueue_scripts', 'ifp_enqueue_admin_assets');
function ifp_enqueue_admin_assets($hook) {
    if (strpos($hook, 'ifp-') !== false) {
        wp_enqueue_style('ifp-admin-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), '2.0');
        wp_enqueue_script('ifp-admin-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery', 'jquery-ui-sortable'), '2.0', true);
    }
}

// Function to handle image validation
function ifp_validate_image_size($file, $width = 250, $height = 250) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_error', 'خطا در آپلود فایل.');
    }
    $image_data = getimagesize($file['tmp_name']);
    if ($image_data === false) {
        return new WP_Error('invalid_image', 'فایل انتخاب شده تصویر معتبر نیست.');
    }
    if ($image_data[0] != $width || $image_data[1] != $height) {
        return new WP_Error('invalid_dimensions', "اندازه تصویر باید دقیقاً {$width}x{$height} پیکسل باشد.");
    }
    return true;
}

// Function to handle file uploads
function ifp_handle_upload($file_key, $validation_callback = null) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if ($validation_callback && is_callable($validation_callback)) {
        $validation_result = call_user_func($validation_callback, $_FILES[$file_key]);
        if (is_wp_error($validation_result)) {
            wp_die($validation_result->get_error_message());
        }
    }

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $upload_dir = wp_upload_dir();
    $custom_dir = $upload_dir['basedir'] . '/ifp_uploads';
    if (!file_exists($custom_dir)) {
        wp_mkdir_p($custom_dir);
    }

    $uploaded_file = $_FILES[$file_key];
    $upload_overrides = array('test_form' => false);

    $unique_filename = wp_unique_filename($custom_dir, sanitize_file_name($uploaded_file['name']));
    $uploaded_file['name'] = $unique_filename;

    $movefile = wp_handle_upload($uploaded_file, $upload_overrides, current_time('mysql'));

    if ($movefile && !isset($movefile['error'])) {
        // Return relative path from uploads directory
        return str_replace($upload_dir['basedir'], '', $movefile['file']);
    }

    return null;
}
