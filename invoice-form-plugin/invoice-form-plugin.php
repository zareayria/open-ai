<?php
/**
 * Plugin Name: Invoice Form Plugin
 * Description: A plugin to create a proforma invoice request form.
 * Version: 1.0
 * Author: Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function invoice_form_shortcode() {
    ob_start();

    if ( class_exists( 'WooCommerce' ) ) {
        // Enqueue scripts and localize data
        wp_enqueue_script('invoice-form-js', plugin_dir_url( __FILE__ ) . 'assets/js/form-handler.js', array('jquery'), '1.0', true);
        wp_localize_script('invoice-form-js', 'invoice_form_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'invoice-form-nonce' )
        ));
    }

    // Display success message
    if ( isset( $_GET['invoice_submitted'] ) && $_GET['invoice_submitted'] == 'true' ) {
        echo '<p style="color: green;">درخواست شما با موفقیت ثبت شد. با تشکر!</p>';
    }

    ?>
    <form id="invoice-form" method="post">
        <h2>اطلاعات تماس</h2>
        <p>
            <label for="customer_name">نام و نام خانوادگی (اجباری)</label>
            <input type="text" id="customer_name" name="customer_name" required>
        </p>
        <p>
            <label for="customer_email">ایمیل</label>
            <input type="email" id="customer_email" name="customer_email">
        </p>
        <p>
            <label for="customer_phone">شماره همراه (اجباری)</label>
            <input type="text" id="customer_phone" name="customer_phone" required>
        </p>
        <p>
            <label for="customer_company">شرکت</label>
            <input type="text" id="customer_company" name="customer_company">
        </p>
         <p>
            <label for="customer_notes">توضیحات</label>
            <textarea id="customer_notes" name="customer_notes"></textarea>
        </p>

        <h2>اقلام پیش فاکتور</h2>

        <?php if ( class_exists( 'WooCommerce' ) ) : ?>
            <p>
                <label for="product-search">جستجوی محصول</label>
                <input type="text" id="product-search" placeholder="نام محصول را تایپ کنید...">
                <div id="product-search-results"></div>
            </p>
        <?php endif; ?>

        <div id="invoice-items-wrapper">
        </div>
        <button type="button" id="add-invoice-item">افزودن محصول به صورت دستی</button>

        <p>
            <input type="submit" name="submit_invoice_request" value="ثبت درخواست پیش فاکتور">
        </p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('invoice_form', 'invoice_form_shortcode');

function search_products_callback() {
    check_ajax_referer( 'invoice-form-nonce', 'nonce' );

    $search_term = sanitize_text_field( $_POST['search_term'] );
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 10,
        's'              => $search_term,
    );
    $products = new WP_Query( $args );

    $results = array();
    if ( $products->have_posts() ) {
        while ( $products->have_posts() ) {
            $products->the_post();
            $results[] = array(
                'id'    => get_the_ID(),
                'title' => get_the_title(),
            );
        }
    }
    wp_reset_postdata();

    wp_send_json_success( $results );
}
add_action( 'wp_ajax_search_products', 'search_products_callback' );
add_action( 'wp_ajax_nopriv_search_products', 'search_products_callback' );


function create_invoice_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $requests_table_name = $wpdb->prefix . 'invoice_requests';
    $items_table_name = $wpdb->prefix . 'invoice_request_items';

    $sql = "CREATE TABLE $requests_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        customer_name tinytext NOT NULL,
        customer_email tinytext,
        customer_phone tinytext NOT NULL,
        customer_company tinytext,
        customer_notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;

    CREATE TABLE $items_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        request_id mediumint(9) NOT NULL,
        title tinytext NOT NULL,
        quantity mediumint(9) NOT NULL,
        description text,
        PRIMARY KEY  (id),
        KEY request_id (request_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'create_invoice_tables' );

function handle_invoice_form_submission() {
    if ( isset( $_POST['submit_invoice_request'] ) ) {
        global $wpdb;
        $requests_table_name = $wpdb->prefix . 'invoice_requests';
        $items_table_name = $wpdb->prefix . 'invoice_request_items';

        // Sanitize and prepare customer data
        $customer_name = sanitize_text_field( $_POST['customer_name'] );
        $customer_email = sanitize_email( $_POST['customer_email'] );
        $customer_phone = sanitize_text_field( $_POST['customer_phone'] );
        $customer_company = sanitize_text_field( $_POST['customer_company'] );
        $customer_notes = sanitize_textarea_field( $_POST['customer_notes'] );

        // Insert the main request
        $wpdb->insert(
            $requests_table_name,
            array(
                'customer_name'    => $customer_name,
                'customer_email'   => $customer_email,
                'customer_phone'   => $customer_phone,
                'customer_company' => $customer_company,
                'customer_notes'   => $customer_notes,
            )
        );

        $request_id = $wpdb->insert_id;

        // Insert invoice items
        if ( $request_id > 0 && isset( $_POST['invoice_items'] ) && is_array( $_POST['invoice_items'] ) ) {
            foreach ( $_POST['invoice_items'] as $item ) {
                if ( ! empty( $item['title'] ) ) {
                    $wpdb->insert(
                        $items_table_name,
                        array(
                            'request_id'  => $request_id,
                            'title'       => sanitize_text_field( $item['title'] ),
                            'quantity'    => absint( $item['quantity'] ),
                            'description' => sanitize_textarea_field( $item['description'] ),
                        )
                    );
                }
            }
        }

        // Redirect to the same page with a success query arg
        $redirect_url = add_query_arg( 'invoice_submitted', 'true', wp_get_referer() );
        wp_redirect( $redirect_url );
        exit;
    }
}
add_action( 'init', 'handle_invoice_form_submission' );

// Add admin menu for viewing requests
function add_invoice_admin_menu() {
    add_menu_page(
        'درخواست‌های پیش‌فاکتور',
        'درخواست‌های پیش‌فاکتور',
        'manage_options',
        'invoice-requests',
        'display_invoice_requests_page',
        'dashicons-text-page',
        25
    );
    add_submenu_page(
        null, // Hidden submenu
        'جزئیات درخواست',
        'جزئیات درخواست',
        'manage_options',
        'invoice-request-details',
        'display_invoice_request_details_page'
    );
}
add_action( 'admin_menu', 'add_invoice_admin_menu' );

// Display the list of invoice requests
function display_invoice_requests_page() {
    global $wpdb;
    $requests_table_name = $wpdb->prefix . 'invoice_requests';

    $requests = $wpdb->get_results( "SELECT id, customer_name, customer_phone, created_at FROM $requests_table_name ORDER BY created_at DESC" );
    ?>
    <div class="wrap">
        <h1>درخواست‌های پیش‌فاکتور</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>نام مشتری</th>
                    <th>شماره همراه</th>
                    <th>تاریخ ثبت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $requests ) : ?>
                    <?php foreach ( $requests as $request ) : ?>
                        <tr>
                            <td><?php echo esc_html( $request->customer_name ); ?></td>
                            <td><?php echo esc_html( $request->customer_phone ); ?></td>
                            <td><?php echo esc_html( $request->created_at ); ?></td>
                            <td>
                                <a href="<?php echo admin_url( 'admin.php?page=invoice-request-details&request_id=' . $request->id ); ?>">
                                    مشاهده جزئیات
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">هیچ درخواستی یافت نشد.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Display the details of a single invoice request
function display_invoice_request_details_page() {
    if ( ! isset( $_GET['request_id'] ) ) {
        wp_die( 'شناسه درخواست نامعتبر است.' );
    }

    $request_id = absint( $_GET['request_id'] );

    global $wpdb;
    $requests_table_name = $wpdb->prefix . 'invoice_requests';
    $items_table_name = $wpdb->prefix . 'invoice_request_items';

    $request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $requests_table_name WHERE id = %d", $request_id ) );
    $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $items_table_name WHERE request_id = %d", $request_id ) );

    if ( ! $request ) {
        wp_die( 'درخواست یافت نشد.' );
    }
    ?>
    <div class="wrap">
        <h1>جزئیات درخواست پیش‌فاکتور</h1>
        <h2>اطلاعات تماس</h2>
        <table class="form-table">
            <tr>
                <th scope="row">نام مشتری</th>
                <td><?php echo esc_html( $request->customer_name ); ?></td>
            </tr>
            <tr>
                <th scope="row">ایمیل</th>
                <td><?php echo esc_html( $request->customer_email ); ?></td>
            </tr>
             <tr>
                <th scope="row">شماره همراه</th>
                <td><?php echo esc_html( $request->customer_phone ); ?></td>
            </tr>
             <tr>
                <th scope="row">شرکت</th>
                <td><?php echo esc_html( $request->customer_company ); ?></td>
            </tr>
             <tr>
                <th scope="row">توضیحات</th>
                <td><?php echo nl2br( esc_html( $request->customer_notes ) ); ?></td>
            </tr>
             <tr>
                <th scope="row">تاریخ ثبت</th>
                <td><?php echo esc_html( $request->created_at ); ?></td>
            </tr>
        </table>

        <h2>اقلام درخواستی</h2>
        <table class="wp-list-table widefat fixed striped">
             <thead>
                <tr>
                    <th>عنوان محصول</th>
                    <th>تعداد</th>
                    <th>توضیحات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $items ) : ?>
                    <?php foreach ( $items as $item ) : ?>
                        <tr>
                            <td><?php echo esc_html( $item->title ); ?></td>
                            <td><?php echo esc_html( $item->quantity ); ?></td>
                            <td><?php echo nl2br( esc_html( $item->description ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3">هیچ محصولی برای این درخواست ثبت نشده است.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
