<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_get_seller_info', 'ifp_get_seller_info');
add_action('wp_ajax_nopriv_get_seller_info', 'ifp_get_seller_info');

function ifp_get_seller_info() {
    global $wpdb;
    $sellers_table = $wpdb->prefix . 'ifp_sellers';

    $seller_id = isset($_POST['seller_id']) ? intval($_POST['seller_id']) : 0;

    if ($seller_id === 0) {
        wp_send_json_error(['message' => 'Invalid seller ID.']);
    }

    $seller = $wpdb->get_row($wpdb->prepare("SELECT phone, photo_path FROM $sellers_table WHERE id = %d", $seller_id));

    if (!$seller) {
        wp_send_json_error(['message' => 'Seller not found.']);
    }

    $upload_dir = wp_upload_dir();
    $seller->photo_url = $seller->photo_path ? $upload_dir['baseurl'] . $seller->photo_path : '';

    wp_send_json_success($seller);
}
