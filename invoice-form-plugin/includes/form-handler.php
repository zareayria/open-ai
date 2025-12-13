<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'ifp_handle_form_submission');

function ifp_handle_form_submission() {
    if (isset($_POST['submit_invoice']) && isset($_POST['ifp_form_nonce'])) {
        if (!wp_verify_nonce($_POST['ifp_form_nonce'], 'ifp_form_action')) {
            wp_die('Nonce verification failed!');
        }

        global $wpdb;
        $requests_table = $wpdb->prefix . 'ifp_requests';
        $items_table = $wpdb->prefix . 'ifp_request_items';
        $fields_table = $wpdb->prefix . 'ifp_form_fields';

        // Validate customer photo if present
        if (isset($_FILES['customer_photo']) && $_FILES['customer_photo']['error'] === UPLOAD_ERR_OK) {
            $validation_result = ifp_validate_image_size($_FILES['customer_photo']);
            if (is_wp_error($validation_result)) {
                // You can redirect back with an error or display it
                wp_die($validation_result->get_error_message());
            }
        }

        $form_data = [];
        $form_fields = $wpdb->get_results("SELECT field_name, field_type FROM $fields_table");

        foreach ($form_fields as $field) {
            $field_name = $field->field_name;
            if ($field->field_type === 'file') {
                if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                    $form_data[$field_name] = ifp_handle_upload($field_name);
                }
            } else {
                if (isset($_POST[$field_name])) {
                    $form_data[$field_name] = sanitize_text_field($_POST[$field_name]);
                }
            }
        }

        // Insert the main request
        $request_data = [
            'user_id' => get_current_user_id(),
            'seller_id' => isset($_POST['seller_id']) ? intval($_POST['seller_id']) : null,
            'form_data' => json_encode($form_data, JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($requests_table, $request_data);
        $request_id = $wpdb->insert_id;

        // Insert items
        if ($request_id > 0 && isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['product_name']) && !empty($item['quantity'])) {
                    $wpdb->insert($items_table, [
                        'request_id' => $request_id,
                        'product_name' => sanitize_text_field($item['product_name']),
                        'quantity' => sanitize_text_field($item['quantity']),
                        'description' => sanitize_text_field($item['description'])
                    ]);
                }
            }
        }

        // Redirect after submission
        $redirect_url = add_query_arg('ifp_submitted', '1', wp_get_referer());
        wp_redirect($redirect_url);
        exit;
    }
}

// You might want to display a success message on the form page
add_action('the_content', 'ifp_show_success_message');
function ifp_show_success_message($content) {
    if (strpos($content, '[invoice_form]') !== false && isset($_GET['ifp_submitted']) && $_GET['ifp_submitted'] == '1') {
        $success_message = '<div class="ifp-success-message">درخواست شما با موفقیت ثبت شد. متشکریم!</div>';
        // Remove the query arg using JS to prevent it showing on refresh
        $script = '<script>window.history.replaceState({}, document.title, window.location.pathname);</script>';
        return $success_message . $content . $script;
    }
    return $content;
}
