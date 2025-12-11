<?php
// includes/export-csv.php

function handle_requests_export() {
    if (isset($_GET['action']) && $_GET['action'] == 'export_requests') {
        global $wpdb;

        // Table names
        $requests_table = $wpdb->prefix . 'invoice_requests';
        $sellers_table = $wpdb->prefix . 'invoice_sellers';
        $data_table = $wpdb->prefix . 'invoice_request_data';
        $items_table = $wpdb->prefix . 'invoice_request_items';
        $fields_table = $wpdb->prefix . 'invoice_form_fields';

        // --- Start Filtering Logic (same as in display_invoice_requests_page) ---
        $current_seller = isset($_GET['seller_id']) ? absint($_GET['seller_id']) : '';
        $current_customer = isset($_GET['customer_name']) ? sanitize_text_field($_GET['customer_name']) : '';

        $where_clauses = [];
        $params = [];

        if (!empty($current_seller)) {
            $where_clauses[] = "r.seller_id = %d";
            $params[] = $current_seller;
        }

        if (!empty($current_customer)) {
            $where_clauses[] = "r.id IN (SELECT request_id FROM $data_table WHERE field_name = 'customer_name' AND field_value LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($current_customer) . '%';
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = "WHERE " . implode(' AND ', $where_clauses);
        }

        $query = "SELECT r.id FROM $requests_table r $where_sql";

        if (!empty($params)) {
            $request_ids = $wpdb->get_col($wpdb->prepare($query, $params));
        } else {
            $request_ids = $wpdb->get_col($query);
        }
        // --- End Filtering Logic ---

        if (empty($request_ids)) {
            wp_die('هیچ درخواستی برای خروجی گرفتن یافت نشد.');
            return;
        }

        // --- Prepare Data for CSV ---
        $csv_data = [];

        // Get all possible dynamic field labels to use as headers
        $header_fields = $wpdb->get_results("SELECT field_name, field_label FROM $fields_table ORDER BY field_order ASC");
        $headers = ['ID درخواست', 'تاریخ ثبت', 'فروشنده'];
        foreach($header_fields as $field){
            $headers[] = $field->field_label;
        }
        $headers[] = 'اقلام دستی';
        $csv_data[] = $headers;

        // Process each request
        foreach ($request_ids as $request_id) {
            $row = [];
            $request = $wpdb->get_row($wpdb->prepare("SELECT r.*, s.name as seller_name FROM $requests_table r LEFT JOIN $sellers_table s ON r.seller_id = s.id WHERE r.id = %d", $request_id));

            $row['id'] = $request->id;
            $row['created_at'] = $request->created_at;
            $row['seller_name'] = $request->seller_name;

            // Get dynamic field data for this request
            $dynamic_data_results = $wpdb->get_results($wpdb->prepare("SELECT field_name, field_value FROM $data_table WHERE request_id = %d", $request_id));
            $dynamic_data = [];
            foreach($dynamic_data_results as $data){
                $dynamic_data[$data->field_name] = $data->field_value;
            }

            foreach($header_fields as $field){
                 $row[$field->field_name] = isset($dynamic_data[$field->field_name]) ? $dynamic_data[$field->field_name] : '';
            }

            // Get manual items
            $manual_items_results = $wpdb->get_results($wpdb->prepare("SELECT title, quantity, description FROM $items_table WHERE request_id = %d", $request_id));
            $manual_items_str = '';
            foreach ($manual_items_results as $item) {
                $manual_items_str .= sprintf("محصول: %s, تعداد: %d, توضیحات: %s | ", $item->title, $item->quantity, $item->description);
            }
            $row['manual_items'] = rtrim($manual_items_str, ' | ');

            $csv_data[] = $row;
        }

        // --- Generate and Output CSV ---
        $filename = 'invoice_requests_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM to fix Excel encoding issues
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}

add_action('admin_init', 'handle_requests_export');
