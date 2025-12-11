<?php
/**
 * Plugin Name: Invoice Form Plugin
 * Description: A plugin to create a proforma invoice request form.
 * Version: 1.0
 * Author: محمد زارع
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function invoice_form_shortcode() {
    global $wpdb;
    $sellers_table = $wpdb->prefix . 'invoice_sellers';
    $fields_table = $wpdb->prefix . 'invoice_form_fields';

    $sellers = $wpdb->get_results("SELECT id, name FROM $sellers_table ORDER BY name ASC");
    $fields = $wpdb->get_results("SELECT * FROM $fields_table ORDER BY field_order ASC");

    ob_start();

    // Enqueue Styles & Scripts
    wp_enqueue_style('invoice-form-css', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.2');
    wp_enqueue_script('invoice-form-js', plugin_dir_url(__FILE__) . 'assets/js/form-handler.js', array('jquery'), '1.2', true);
    wp_localize_script('invoice-form-js', 'invoice_form_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('invoice-form-nonce')
    ));

    // Display success message
    if (isset($_GET['invoice_submitted']) && $_GET['invoice_submitted'] == 'true') {
        echo '<p class="invoice-success-message">درخواست شما با موفقیت ثبت شد. با تشکر!</p>';
    }
    ?>
    <div class="invoice-form-container">
        <form id="invoice-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
             <input type="hidden" name="action" value="handle_invoice_form_submission">
             <?php wp_nonce_field( 'submit_invoice_form_nonce' ); ?>

            <div class="form-main-content">
                <div class="form-section">
                    <h3>اطلاعات تماس</h3>
                    <p class="section-description">اطلاعات تماس و لیست اجناس را در فرم زیر پر کنید</p>
                    <div class="form-grid">
                        <?php if (!empty($sellers)) : ?>
                        <div class="form-field full-width">
                             <label for="seller_id">انتخاب فروشنده</label>
                             <select id="seller_id" name="seller_id" required>
                                 <option value="">یک فروشنده را انتخاب کنید...</option>
                                 <?php foreach ($sellers as $seller) : ?>
                                     <option value="<?php echo esc_attr($seller->id); ?>"><?php echo esc_html($seller->name); ?></option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                        <?php endif; ?>

                        <?php
                        foreach ($fields as $field) {
                            $required_attr = $field->is_required ? 'required' : '';
                            $required_label = $field->is_required ? ' (اجباری)' : '';
                            $field_id = esc_attr($field->field_name);
                            $field_name = 'form_fields[' . esc_attr($field->field_name) . ']';

                            echo '<div class="form-field ' . ($field->field_type === 'textarea' ? 'full-width' : '') . '">';
                            echo '<label for="' . $field_id . '">' . esc_html($field->field_label) . $required_label . '</label>';

                            if ($field->field_type === 'textarea') {
                                echo '<textarea id="' . $field_id . '" name="' . $field_name . '" placeholder="' . esc_attr($field->placeholder) . '" ' . $required_attr . '></textarea>';
                            } else if ($field->field_type === 'file') {
                                // For file inputs, the name attribute needs to be unique and not in an array format for $_FILES to work correctly.
                                $file_field_name = 'form_file_' . esc_attr($field->field_name);
                                echo '<input type="file" id="' . $field_id . '" name="' . $file_field_name . '" ' . $required_attr . '>';
                                echo '<span class="description">ابعاد تصویر باید ۲۵۰×۲۵۰ پیکسل باشد.</span>';
                            }
                            else {
                                echo '<input type="' . esc_attr($field->field_type) . '" id="' . $field_id . '" name="' . $field_name . '" placeholder="' . esc_attr($field->placeholder) . '" ' . $required_attr . '>';
                            }

                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3>افزودن محصول</h3>
                    <div id="manual-entry">
                        <?php if (class_exists('WooCommerce')) : ?>
                        <div class="form-field">
                            <label for="product-search">جستجوی محصول</label>
                            <input type="text" id="product-search" placeholder="جست جوی محصول مورد نظر درسایت">
                            <div id="product-search-results"></div>
                        </div>
                        <?php endif; ?>
                        <div id="invoice-items-wrapper">
                            <!-- Items will be added here via JS -->
                        </div>
                        <button type="button" id="add-invoice-item" class="add-item-btn">+ افزودن محصول</button>
                    </div>
                </div>
            </div>

            <div class="form-sidebar">
                <div class="summary-box">
                    <h4>درخواست پیش فاکتور</h4>
                    <p class="summary-description">پیش فاکتور از طریق واتساپ ارسال خواهد شد؛ در صورت نبود دسترسی، از سایر راه‌های ارتباطی فعال شما ارسال می‌گردد.</p>
                    <button type="submit" name="submit_invoice_request" class="submit-btn">ثبت درخواست پیش فاکتور</button>
                </div>
            </div>
        </form>
    </div>
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
    $sellers_table_name = $wpdb->prefix . 'invoice_sellers';
    $fields_table_name = $wpdb->prefix . 'invoice_form_fields';
    $data_table_name = $wpdb->prefix . 'invoice_request_data';

    $sql = "CREATE TABLE $requests_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        seller_id mediumint(9),
        file_path varchar(255) DEFAULT '' NOT NULL,
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
    ) $charset_collate;

    CREATE TABLE $sellers_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        mobile_number varchar(20),
        image_url varchar(255),
        PRIMARY KEY  (id)
    ) $charset_collate;

    CREATE TABLE $fields_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        field_name varchar(50) NOT NULL,
        field_label tinytext NOT NULL,
        field_type varchar(20) NOT NULL,
        placeholder tinytext,
        is_required tinyint(1) NOT NULL DEFAULT 0,
        field_order mediumint(9) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY field_name (field_name)
    ) $charset_collate;

    CREATE TABLE $data_table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        request_id mediumint(9) NOT NULL,
        field_name varchar(50) NOT NULL,
        field_value text,
        PRIMARY KEY  (id),
        KEY request_id (request_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Insert default fields if they don't exist
    if (get_option('invoice_form_default_fields_inserted') !== 'true') {
        insert_default_form_fields();
        update_option('invoice_form_default_fields_inserted', 'true');
    }
}
register_activation_hook( __FILE__, 'create_invoice_tables' );

function insert_default_form_fields() {
    global $wpdb;
    $fields_table_name = $wpdb->prefix . 'invoice_form_fields';

    // Clear existing default fields to ensure a clean slate, only on first activation
    $wpdb->query("TRUNCATE TABLE $fields_table_name");

    $default_fields = array(
        array('field_name' => 'customer_name', 'field_label' => 'نام و نام خانوادگی', 'field_type' => 'text', 'placeholder' => 'نام خود را بنویسید...', 'is_required' => 1, 'field_order' => 1),
        array('field_name' => 'customer_phone', 'field_label' => 'شماره موبایل', 'field_type' => 'text', 'placeholder' => 'مثلا: ۰۹۱۲۳۴۵۶۷۸۹', 'is_required' => 1, 'field_order' => 2),
        array('field_name' => 'customer_photo', 'field_label' => 'آپلود عکس', 'field_type' => 'file', 'placeholder' => '', 'is_required' => 1, 'field_order' => 3),
    );

    foreach ($default_fields as $field) {
        $wpdb->insert($fields_table_name, $field);
    }
}

function handle_invoice_form_submission() {
    // Verify nonce
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'submit_invoice_form_nonce' ) ) {
        wp_die( 'خطای امنیتی. لطفاً دوباره تلاش کنید.' );
    }

    global $wpdb;
    $requests_table_name = $wpdb->prefix . 'invoice_requests';
    $items_table_name = $wpdb->prefix . 'invoice_request_items';
    $data_table_name = $wpdb->prefix . 'invoice_request_data';
    $fields_table_name = $wpdb->prefix . 'invoice_form_fields';

    // Sanitize and prepare data
    $seller_id = isset($_POST['seller_id']) ? absint($_POST['seller_id']) : 0;

    // Create the main request record first to get an ID
    $wpdb->insert($requests_table_name, ['seller_id' => $seller_id]);
    $request_id = $wpdb->insert_id;

    if ($request_id === 0) {
        wp_die('خطایی در ایجاد درخواست رخ داد.');
        return;
    }

    // --- Handle File Uploads (No Validation needed here anymore) ---
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    $upload_overrides = array('test_form' => false);

    // Get all 'file' type fields from DB to check against $_FILES
    $file_fields = $wpdb->get_results("SELECT field_name FROM $fields_table_name WHERE field_type = 'file'");

    foreach ($file_fields as $file_field) {
        $file_input_name = 'form_file_' . $file_field->field_name;

        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
            $uploaded_file = $_FILES[$file_input_name];

            // ** Image Dimension Validation **
            $image_size = getimagesize($uploaded_file['tmp_name']);
            if ($image_size !== false) { // It's an image
                $width = $image_size[0];
                $height = $image_size[1];

                if ($width != 250 || $height != 250) {
                     $wpdb->delete($requests_table_name, ['id' => $request_id]); // Clean up
                     wp_die('ابعاد تصویر باید دقیقاً 250x250 پیکسل باشد. لطفاً تصویر خود را ویرایش کرده و مجدداً آپلود کنید.');
                }
            }
            // ** End Validation **

            $movefile = wp_handle_upload($uploaded_file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                // Save file URL to the data table
                 $wpdb->insert(
                    $data_table_name,
                    array(
                        'request_id'  => $request_id,
                        'field_name'  => sanitize_key($file_field->field_name),
                        'field_value' => $movefile['url'], // Store the URL
                    )
                );
            } else {
                error_log('File Upload Error: ' . $movefile['error']);
            }
        }
    }

    // --- Handle Standard Form Fields ---
    if (isset($_POST['form_fields']) && is_array($_POST['form_fields'])) {
        foreach ($_POST['form_fields'] as $field_name => $field_value) {
            $wpdb->insert(
                $data_table_name,
                array(
                    'request_id'  => $request_id,
                    'field_name'  => sanitize_key($field_name),
                    'field_value' => sanitize_textarea_field($field_value),
                )
            );
        }
    }

    // --- Handle Invoice Items (Manual Entry) ---
    if (isset($_POST['invoice_items']) && is_array($_POST['invoice_items'])) {
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

    // Redirect on success
    $redirect_url = add_query_arg('invoice_submitted', 'true', wp_get_referer());
    wp_redirect($redirect_url);
    exit;
}
add_action('admin_post_nopriv_handle_invoice_form_submission', 'handle_invoice_form_submission');
add_action('admin_post_handle_invoice_form_submission', 'handle_invoice_form_submission');

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
    add_submenu_page(
        'invoice-requests', // Parent slug
        'مدیریت فروشندگان',
        'مدیریت فروشندگان',
        'manage_options',
        'invoice-sellers',
        'display_sellers_page'
    );
    add_submenu_page(
        'invoice-requests', // Parent slug
        'تنظیمات فرم',
        'تنظیمات فرم',
        'manage_options',
        'invoice-form-settings',
        'display_form_settings_page'
    );
}
add_action( 'admin_menu', 'add_invoice_admin_menu' );

// Display the sellers management page
function display_sellers_page() {
    global $wpdb;
    $sellers_table_name = $wpdb->prefix . 'invoice_sellers';

    // Handle form submission for adding/editing a seller
    if (isset($_POST['submit_seller'])) {
        // Verify nonce
        if (!isset($_POST['seller_nonce_field']) || !wp_verify_nonce($_POST['seller_nonce_field'], 'manage_seller_nonce')) {
            wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
        }

        $name = sanitize_text_field($_POST['seller_name']);
        $mobile_number = sanitize_text_field($_POST['seller_mobile_number']);
        $image_url = esc_url_raw($_POST['seller_image_url']);
        $seller_id = isset($_POST['seller_id']) ? absint($_POST['seller_id']) : 0;

        if ($seller_id > 0) {
            // Update existing seller
            $wpdb->update(
                $sellers_table_name,
                ['name' => $name, 'mobile_number' => $mobile_number, 'image_url' => $image_url],
                ['id' => $seller_id]
            );
        } else {
            // Add new seller
            $wpdb->insert(
                $sellers_table_name,
                ['name' => $name, 'mobile_number' => $mobile_number, 'image_url' => $image_url]
            );
        }
        echo '<div class="updated"><p>فروشنده با موفقیت ذخیره شد.</p></div>';
    }

    // Handle seller deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['seller_id'])) {
        $seller_id = absint($_GET['seller_id']);
        // Verify nonce for deletion
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_seller_' . $seller_id)) {
            $wpdb->delete($sellers_table_name, ['id' => $seller_id]);
            echo '<div class="updated"><p>فروشنده با موفقیت حذف شد.</p></div>';
        } else {
            wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
        }
    }

    // Get seller to edit if in edit mode
    $seller_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['seller_id'])) {
        $seller_id = absint($_GET['seller_id']);
        $seller_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sellers_table_name WHERE id = %d", $seller_id));
    }

    $sellers = $wpdb->get_results("SELECT * FROM $sellers_table_name ORDER BY name ASC");
    ?>
    <div class="wrap">
        <h1>مدیریت فروشندگان</h1>

        <!-- Form for adding/editing a seller -->
        <h2><?php echo $seller_to_edit ? 'ویرایش فروشنده' : 'افزودن فروشنده جدید'; ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'manage_seller_nonce', 'seller_nonce_field' ); ?>
            <input type="hidden" name="seller_id" value="<?php echo $seller_to_edit ? $seller_to_edit->id : ''; ?>">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="seller_name">نام و نام خانوادگی</label></th>
                    <td><input type="text" id="seller_name" name="seller_name" value="<?php echo $seller_to_edit ? esc_attr($seller_to_edit->name) : ''; ?>" required></td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="seller_mobile_number">شماره موبایل</label></th>
                    <td><input type="text" id="seller_mobile_number" name="seller_mobile_number" value="<?php echo $seller_to_edit ? esc_attr($seller_to_edit->mobile_number) : ''; ?>" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="seller_image_url">آدرس تصویر</label></th>
                    <td>
                        <input type="text" id="seller_image_url" name="seller_image_url" value="<?php echo $seller_to_edit ? esc_attr($seller_to_edit->image_url) : ''; ?>" class="regular-text">
                        <input type="button" class="button" id="upload_image_button" value="آپلود تصویر">
                        <p class="description">ابعاد تصویر باید ۲۵۰×۲۵۰ پیکسل باشد. آدرس تصویر را وارد کنید یا یک تصویر جدید آپلود کنید.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button($seller_to_edit ? 'ذخیره تغییرات' : 'افزودن فروشنده', 'primary', 'submit_seller'); ?>
        </form>

        <hr>

        <!-- List of existing sellers -->
        <h2>لیست فروشندگان</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>نام و نام خانوادگی</th>
                    <th>شماره موبایل</th>
                    <th>تصویر</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sellers) : ?>
                    <?php foreach ($sellers as $seller) : ?>
                        <tr>
                            <td><?php echo esc_html($seller->name); ?></td>
                            <td><?php echo esc_html($seller->mobile_number); ?></td>
                            <td>
                                <?php if ($seller->image_url) : ?>
                                    <img src="<?php echo esc_url($seller->image_url); ?>" width="50" height="50" style="object-fit: cover;">
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=invoice-sellers&action=edit&seller_id=<?php echo $seller->id; ?>">ویرایش</a> |
                                <a href="<?php echo wp_nonce_url('?page=invoice-sellers&action=delete&seller_id=' . $seller->id, 'delete_seller_' . $seller->id); ?>" onclick="return confirm('آیا از حذف این فروشنده مطمئن هستید؟')">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">هیچ فروشنده‌ای یافت نشد.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
    jQuery(document).ready(function($) {
        var mediaUploader;
        $('#upload_image_button').click(function(e) {
            e.preventDefault();
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'انتخاب تصویر',
                button: {
                    text: 'انتخاب تصویر'
                },
                library: {
                    type: 'image'
                },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();

                // ** Dimension Validation **
                if (attachment.width !== 250 || attachment.height !== 250) {
                    alert('خطا: ابعاد تصویر باید دقیقاً ۲۵۰×۲۵۰ پیکسل باشد. لطفاً تصویر دیگری انتخاب کنید.');
                    // Clear the input field if the dimensions are wrong
                    $('#seller_image_url').val('');
                } else {
                    $('#seller_image_url').val(attachment.url);
                }
            });
            mediaUploader.open();
        });
    });
    </script>
    <?php
}

require_once plugin_dir_path(__FILE__) . 'admin/form-settings-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/export-csv.php';

function load_media_files() {
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'load_media_files');

// Display the list of invoice requests
function display_invoice_requests_page() {
    global $wpdb;
    $requests_table = $wpdb->prefix . 'invoice_requests';
    $sellers_table = $wpdb->prefix . 'invoice_sellers';
    $data_table = $wpdb->prefix . 'invoice_request_data';

    // In a real-world scenario with many requests, you'd want pagination.
    $current_seller = isset($_GET['seller_id']) ? absint($_GET['seller_id']) : '';
    $current_customer = isset($_GET['customer_name']) ? sanitize_text_field($_GET['customer_name']) : '';

    $where_clauses = [];
    $params = [];

    if (!empty($current_seller)) {
        $where_clauses[] = "r.seller_id = %d";
        $params[] = $current_seller;
    }

    if (!empty($current_customer)) {
        // This subquery finds request_ids that have a matching customer_name
        $where_clauses[] = "r.id IN (SELECT request_id FROM $data_table WHERE field_name = 'customer_name' AND field_value LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($current_customer) . '%';
    }

    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = "WHERE " . implode(' AND ', $where_clauses);
    }

    $query = "
        SELECT
            r.id,
            r.created_at,
            s.name as seller_name,
            (SELECT d.field_value FROM $data_table d WHERE d.request_id = r.id AND d.field_name = 'customer_name' LIMIT 1) as customer_name
        FROM $requests_table r
        LEFT JOIN $sellers_table s ON r.seller_id = s.id
        $where_sql
        ORDER BY r.created_at DESC
    ";

    if (!empty($params)) {
        $requests = $wpdb->get_results($wpdb->prepare($query, $params));
    } else {
        $requests = $wpdb->get_results($query);
    }

    // Get counts for display
    $customer_counts = $wpdb->get_results("SELECT field_value, COUNT(*) as count FROM $data_table WHERE field_name = 'customer_name' GROUP BY field_value ORDER BY count DESC");
    $seller_counts = $wpdb->get_results("SELECT s.name, COUNT(r.id) as count FROM $requests_table r JOIN $sellers_table s ON r.seller_id = s.id GROUP BY r.seller_id ORDER BY count DESC");


    $sellers = $wpdb->get_results("SELECT id, name FROM $sellers_table ORDER BY name ASC");
    ?>
    <div class="wrap">
        <h1>درخواست‌های پیش‌فاکتور</h1>

        <div id="request-stats" style="display:flex; gap: 40px; margin-bottom: 20px;">
            <div>
                <h2>تعداد پیش‌فاکتور بر اساس مشتری</h2>
                <ul>
                    <?php foreach($customer_counts as $customer) : ?>
                        <li><?php echo esc_html($customer->field_value); ?>: <?php echo esc_html($customer->count); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h2>تعداد پیش‌فاکتور بر اساس فروشنده</h2>
                <ul>
                     <?php foreach($seller_counts as $seller) : ?>
                        <li><?php echo esc_html($seller->name); ?>: <?php echo esc_html($seller->count); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="get">
            <input type="hidden" name="page" value="invoice-requests">
            <select name="seller_id">
                <option value="">همه فروشندگان</option>
                <?php foreach ($sellers as $seller): ?>
                    <option value="<?php echo esc_attr($seller->id); ?>" <?php selected($current_seller, $seller->id); ?>>
                        <?php echo esc_html($seller->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="customer_name" placeholder="جستجوی نام مشتری..." value="<?php echo esc_attr($current_customer); ?>">
            <input type="submit" class="button" value="فیلتر">
        </form>

        <!-- Export Button -->
        <a href="<?php echo add_query_arg(['action' => 'export_requests', 'seller_id' => $current_seller, 'customer_name' => $current_customer]); ?>" class="button" style="margin-top: 10px;">خروجی اکسل (CSV)</a>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>نام مشتری</th>
                    <th>فروشنده</th>
                    <th>تاریخ ثبت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $requests ) : ?>
                    <?php foreach ( $requests as $request ) : ?>
                        <tr>
                            <td><?php echo esc_html( $request->customer_name ? $request->customer_name : '—' ); ?></td>
                            <td><?php echo esc_html( $request->seller_name ? $request->seller_name : 'نامشخص' ); ?></td>
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
    $requests_table = $wpdb->prefix . 'invoice_requests';
    $items_table = $wpdb->prefix . 'invoice_request_items';
    $sellers_table = $wpdb->prefix . 'invoice_sellers';
    $data_table = $wpdb->prefix . 'invoice_request_data';
    $fields_table = $wpdb->prefix . 'invoice_form_fields';

    $request = $wpdb->get_row( $wpdb->prepare( "
        SELECT r.*, s.name as seller_name
        FROM $requests_table r
        LEFT JOIN $sellers_table s ON r.seller_id = s.id
        WHERE r.id = %d
    ", $request_id ) );

    $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $items_table WHERE request_id = %d", $request_id ) );

    $form_data = $wpdb->get_results( $wpdb->prepare("
        SELECT d.field_value, f.field_label
        FROM $data_table d
        JOIN $fields_table f ON d.field_name = f.field_name
        WHERE d.request_id = %d
        ORDER BY f.field_order ASC
    ", $request_id));

    if ( ! $request ) {
        wp_die( 'درخواست یافت نشد.' );
    }
    ?>
    <div class="wrap">
        <h1>جزئیات درخواست پیش‌فاکتور</h1>

        <h2>اطلاعات درخواست</h2>
        <table class="form-table">
            <tr>
                <th scope="row">فروشنده</th>
                <td><?php echo esc_html( $request->seller_name ? $request->seller_name : 'نامشخص' ); ?></td>
            </tr>
             <tr>
                <th scope="row">تاریخ ثبت</th>
                <td><?php echo esc_html( $request->created_at ); ?></td>
            </tr>
             <?php if ( ! empty( $request->file_path ) ) : ?>
                <tr>
                    <th scope="row">فایل آپلود شده</th>
                    <td><a href="<?php echo esc_url( $request->file_path ); ?>" target="_blank">مشاهده و دانلود فایل</a></td>
                </tr>
            <?php endif; ?>
        </table>

        <h2>اطلاعات ارسالی کاربر</h2>
        <table class="form-table">
            <?php foreach($form_data as $data): ?>
                 <tr>
                    <th scope="row"><?php echo esc_html($data->field_label); ?></th>
                    <td>
                        <?php
                        // Check if the value is a URL and looks like an image
                        if (filter_var($data->field_value, FILTER_VALIDATE_URL) && preg_match('/\.(jpg|jpeg|png|gif)$/i', $data->field_value)) {
                            echo '<a href="' . esc_url($data->field_value) . '" target="_blank">';
                            echo '<img src="' . esc_url($data->field_value) . '" style="max-width: 150px; height: auto;" />';
                            echo '</a>';
                        } else {
                            echo nl2br(esc_html($data->field_value));
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
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
