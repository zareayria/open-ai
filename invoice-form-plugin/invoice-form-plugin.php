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
    wp_enqueue_script('invoice-form-js', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.2', true);
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
                    <h3>اقلام پیش فاکتور</h3>
                    <div class="tabs">
                        <button type="button" class="tab-link active" data-tab="manual-entry">نوشتن دستی اقلام</button>
                        <button type="button" class="tab-link" data-tab="file-upload">آپلود فایل</button>
                    </div>

                    <div id="manual-entry" class="tab-content active">
                        <?php if (class_exists('WooCommerce')) : ?>
                        <div class="form-field">
                            <label for="product-search">جستجوی محصول</label>
                            <input type="text" id="product-search" placeholder="جست جوی محصول مورد نظر درسایت">
                            <div id="product-search-results"></div>
                        </div>
                        <?php endif; ?>
                         <div class="invoice-items-header">
                            <div class="header-col">ردیف</div>
                            <div class="header-col">نام محصول</div>
                            <div class="header-col">تعداد</div>
                            <div class="header-col">توضیحات</div>
                            <div class="header-col"></div>
                        </div>
                        <div id="invoice-items-wrapper">
                            <!-- Items will be added here via JS -->
                        </div>
                        <button type="button" id="add-invoice-item" class="add-item-btn">+ افزودن محصول</button>
                    </div>

                    <div id="file-upload" class="tab-content">
                        <p>می‌توانید لیست محصولات خود را در قالب فایل‌های مجاز بارگذاری کنید.</p>
                        <input type="file" name="invoice_file" id="invoice_file">
                    </div>
                </div>
            </div>

            <div class="form-sidebar">
                <div id="seller-info-box" class="summary-box seller-info-box" style="display: none;">
                    <img id="seller-image" src="" alt="Seller Image">
                    <h5 id="seller-name-display"></h5>
                    <p id="seller-phone-display"></p>
                </div>
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

function get_seller_info_callback() {
    check_ajax_referer('invoice-form-nonce', 'nonce');

    $seller_id = isset($_POST['seller_id']) ? absint($_POST['seller_id']) : 0;
    if ($seller_id === 0) {
        wp_send_json_error('Invalid seller ID.');
    }

    global $wpdb;
    $sellers_table = $wpdb->prefix . 'invoice_sellers';
    $seller = $wpdb->get_row($wpdb->prepare("SELECT name, mobile_number, image_url FROM $sellers_table WHERE id = %d", $seller_id));

    if ($seller) {
        wp_send_json_success($seller);
    } else {
        wp_send_json_error('Seller not found.');
    }
}
add_action('wp_ajax_get_seller_info', 'get_seller_info_callback');
add_action('wp_ajax_nopriv_get_seller_info', 'get_seller_info_callback');


function search_products_callback() {
    check_ajax_referer( 'invoice-form-nonce', 'nonce' );

    $search_term = sanitize_text_field( $_POST['search_term'] );
    $args = array(
        'post_type'               => 'product',
        'posts_per_page'          => 10,
        's'                       => $search_term,
        'no_found_rows'           => true, // Optimization: skip total row count
        'update_post_meta_cache'  => false, // Optimization: skip meta cache update
        'update_post_term_cache'  => false, // Optimization: skip term cache update
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

    // --- Handle list file upload ---
    if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] == 0) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($_FILES['invoice_file'], $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            // Store file path in the main request table
            $wpdb->update(
                $requests_table_name,
                ['file_path' => $movefile['url']],
                ['id' => $request_id]
            );
        } else {
             error_log('Invoice List File Upload Error: ' . $movefile['error']);
        }
    }


    // --- Handle Dynamic File Fields ---
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    $upload_overrides = array('test_form' => false);

    // Get all 'file' type fields from DB to check against $_FILES
    $file_fields = $wpdb->get_results("SELECT field_name FROM $fields_table_name WHERE field_type = 'file'");

    foreach ($file_fields as $file_field) {
        $file_input_name = 'form_file_' . $file_field->field_name;

        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
            $uploaded_file = $_FILES[$file_input_name];

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
        $image_url = '';

        // Handle image upload
        if (isset($_FILES['seller_image']) && $_FILES['seller_image']['error'] == 0) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php'); // Required for wp_generate_attachment_metadata

            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['seller_image'], $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                 // Check image dimensions
                $file_path = $movefile['file'];
                list($width, $height) = getimagesize($file_path);

                if ($width != 250 || $height != 250) {
                    // If dimensions are wrong, delete the uploaded file and show an error
                    unlink($file_path);
                    wp_die('خطا: ابعاد تصویر باید دقیقاً ۲۵۰×۲۵۰ پیکسل باشد. لطفاً بازگردید و تصویر دیگری را آپلود کنید.');
                }

                $image_url = $movefile['url'];

            } else {
                 wp_die('خطا در آپلود تصویر: ' . $movefile['error']);
            }
        } elseif (!empty($_POST['existing_image_url'])) {
            // If no new file is uploaded, use the existing URL
             $image_url = esc_url_raw($_POST['existing_image_url']);
        }


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
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'manage_seller_nonce', 'seller_nonce_field' ); ?>
            <input type="hidden" name="seller_id" value="<?php echo $seller_to_edit ? esc_attr($seller_to_edit->id) : ''; ?>">
            <input type="hidden" name="existing_image_url" value="<?php echo $seller_to_edit ? esc_attr($seller_to_edit->image_url) : ''; ?>">
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
                    <th scope="row"><label for="seller_image">تصویر</label></th>
                    <td>
                        <input type="file" id="seller_image" name="seller_image" accept="image/*">
                        <p class="description">برای تغییر، تصویر جدیدی را آپلود کنید. ابعاد تصویر باید ۲۵۰×۲۵۰ پیکسل باشد.</p>
                        <?php if ($seller_to_edit && $seller_to_edit->image_url) : ?>
                            <p><strong>تصویر فعلی:</strong></p>
                            <img src="<?php echo esc_url($seller_to_edit->image_url); ?>" width="100" height="100" style="object-fit: cover; border: 1px solid #ddd;"/>
                        <?php endif; ?>
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
    <?php
}

// All functions are now in this file, no need to include separate files.

// AJAX handler to save form fields order
function save_form_fields_order() {
    // Security check
    check_ajax_referer('invoice_form_settings_nonce', 'nonce');

    if (isset($_POST['order']) && is_array($_POST['order'])) {
        global $wpdb;
        $fields_table_name = $wpdb->prefix . 'invoice_form_fields';
        $order = $_POST['order'];

        foreach ($order as $index => $field_id) {
            $wpdb->update(
                $fields_table_name,
                ['field_order' => $index + 1],
                ['id' => absint($field_id)]
            );
        }
        wp_send_json_success('Order saved.');
    } else {
        wp_send_json_error('Invalid data.');
    }
}
add_action('wp_ajax_save_fields_order', 'save_form_fields_order');


// Display the form settings page
function display_form_settings_page() {
    global $wpdb;
    $fields_table_name = $wpdb->prefix . 'invoice_form_fields';

    // Handle Add/Edit Field
    if (isset($_POST['submit_field']) && check_admin_referer('manage_field_nonce')) {
        $field_id = isset($_POST['field_id']) ? absint($_POST['field_id']) : 0;
        $field_data = array(
            'field_name'    => sanitize_key($_POST['field_name']),
            'field_label'   => sanitize_text_field($_POST['field_label']),
            'field_type'    => sanitize_text_field($_POST['field_type']),
            'placeholder'   => sanitize_text_field($_POST['placeholder']),
            'is_required'   => isset($_POST['is_required']) ? 1 : 0,
        );

        if (empty($field_data['field_name']) || empty($field_data['field_label'])) {
             echo '<div class="error"><p>نام فیلد و برچسب فیلد اجباری هستند.</p></div>';
        } else {
            if ($field_id > 0) {
                $wpdb->update($fields_table_name, $field_data, array('id' => $field_id));
            } else {
                $wpdb->insert($fields_table_name, $field_data);
            }
             echo '<div class="updated"><p>فیلد با موفقیت ذخیره شد.</p></div>';
        }
    }

    // Handle Delete Field
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['field_id'])) {
        if (check_admin_referer('delete_field_' . absint($_GET['field_id']))) {
            $field_id = absint($_GET['field_id']);
            $wpdb->delete($fields_table_name, array('id' => $field_id));
            echo '<div class="updated"><p>فیلد با موفقیت حذف شد.</p></div>';
        }
    }

    $field_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['field_id'])) {
        $field_id = absint($_GET['field_id']);
        $field_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fields_table_name WHERE id = %d", $field_id));
    }

    $fields = $wpdb->get_results("SELECT * FROM $fields_table_name ORDER BY field_order ASC");
    ?>
    <div class="wrap">
        <h1>تنظیمات فرم</h1>

        <!-- Add/Edit Form -->
        <h2><?php echo $field_to_edit ? 'ویرایش فیلد' : 'افزودن فیلد جدید'; ?></h2>
        <form method="post">
            <input type="hidden" name="field_id" value="<?php echo $field_to_edit ? esc_attr($field_to_edit->id) : ''; ?>">
            <?php wp_nonce_field('manage_field_nonce'); ?>
            <table class="form-table">
                 <tr valign="top">
                    <th scope="row"><label for="field_label">برچسب فیلد</label></th>
                    <td><input type="text" id="field_label" name="field_label" value="<?php echo $field_to_edit ? esc_attr($field_to_edit->field_label) : ''; ?>" required></td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="field_name">نام فیلد (انگلیسی)</label></th>
                    <td><input type="text" id="field_name" name="field_name" value="<?php echo $field_to_edit ? esc_attr($field_to_edit->field_name) : ''; ?>" required pattern="[a-zA-Z0-9_]+">
                    <p class="description">فقط از حروف انگلیسی، اعداد و آندرلاین استفاده کنید (مثال: customer_address).</p></td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="field_type">نوع فیلد</label></th>
                    <td>
                        <select id="field_type" name="field_type">
                            <?php
                            $types = ['text' => 'متن', 'textarea' => 'متن بلند', 'email' => 'ایمیل', 'file' => 'فایل'];
                            foreach($types as $key => $value){
                                $selected = $field_to_edit && $field_to_edit->field_type == $key ? 'selected' : '';
                                echo "<option value='".esc_attr($key)."' $selected>".esc_html($value)."</option>";
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="placeholder">متن راهنما (Placeholder)</label></th>
                    <td><input type="text" id="placeholder" name="placeholder" value="<?php echo $field_to_edit ? esc_attr($field_to_edit->placeholder) : ''; ?>"></td>
                </tr>
                 <tr valign="top">
                    <th scope="row">اجباری باشد؟</th>
                    <td><label><input type="checkbox" name="is_required" value="1" <?php checked($field_to_edit ? $field_to_edit->is_required : 0, 1); ?>> بله</label></td>
                </tr>
            </table>
            <?php submit_button($field_to_edit ? 'ذخیره تغییرات' : 'افزودن فیلد'); ?>
        </form>
        <hr>

        <!-- Fields List -->
        <h2>لیست فیلدها</h2>
        <p>برای مرتب‌سازی، فیلدها را بکشید و رها کنید.</p>
        <ul id="sortable-fields">
            <?php foreach ($fields as $field) : ?>
                <li id="field_<?php echo esc_attr($field->id); ?>">
                    <strong><?php echo esc_html($field->field_label); ?></strong> (نوع: <?php echo esc_html($field->field_type); ?>)
                    <span>
                        <a href="?page=invoice-form-settings&action=edit&field_id=<?php echo $field->id; ?>">ویرایش</a> |
                        <a href="<?php echo wp_nonce_url('?page=invoice-form-settings&action=delete&field_id=' . $field->id, 'delete_field_' . $field->id); ?>" class="delete-field" onclick="return confirm('آیا از حذف این فیلد مطمئن هستید؟')">حذف</a>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $("#sortable-fields").sortable({
            update: function(event, ui) {
                var field_order = $(this).sortable('toArray').map(function(item) {
                    return item.replace('field_', '');
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_fields_order',
                        nonce: '<?php echo wp_create_nonce("invoice_form_settings_nonce"); ?>',
                        order: field_order
                    },
                    success: function(response) {
                        if(!response.success) {
                            alert('خطایی در ذخیره ترتیب رخ داد.');
                        }
                    }
                });
            }
        });
    });
    </script>
    <?php
     // Enqueue jQuery UI Sortable
    wp_enqueue_script('jquery-ui-sortable');
}

// All functionality is now included in this single file.


// Function to handle CSV export
function handle_csv_export() {
    if (isset($_GET['export']) && $_GET['export'] == 'csv' && current_user_can('manage_options')) {
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'invoice_export_nonce')) {
            wp_die('خطای امنیتی.');
        }

        global $wpdb;
        $requests_table = $wpdb->prefix . 'invoice_requests';
        $sellers_table = $wpdb->prefix . 'invoice_sellers';
        $data_table = $wpdb->prefix . 'invoice_request_data';
        $items_table = $wpdb->prefix . 'invoice_request_items';
        $fields_table = $wpdb->prefix . 'invoice_form_fields';

        // Base query
        $base_sql = "
            SELECT r.id, r.created_at, s.name as seller_name
            FROM $requests_table r
            LEFT JOIN $sellers_table s ON r.seller_id = s.id
        ";

        // Filtering logic
        $where_clauses = [];
        $params = [];
        if (!empty($_GET['filter_seller'])) {
            $where_clauses[] = "r.seller_id = %d";
            $params[] = absint($_GET['filter_seller']);
        }
        if (!empty($_GET['filter_start_date'])) {
            $where_clauses[] = "r.created_at >= %s";
            $params[] = sanitize_text_field($_GET['filter_start_date']) . ' 00:00:00';
        }
        if (!empty($_GET['filter_end_date'])) {
            $where_clauses[] = "r.created_at <= %s";
            $params[] = sanitize_text_field($_GET['filter_end_date']) . ' 23:59:59';
        }

        if (!empty($where_clauses)) {
            $base_sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $sql = $wpdb->prepare($base_sql, $params);
        $requests = $wpdb->get_results($sql);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=invoice-requests-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');

        // Get all possible form fields for header
        $form_fields = $wpdb->get_results("SELECT field_label, field_name FROM $fields_table ORDER BY field_order ASC");
        $header = ['ID', 'تاریخ ثبت', 'فروشنده'];
        foreach($form_fields as $field){
            $header[] = $field->field_label;
        }
        $header[] = 'اقلام درخواستی';
        fputcsv($output, $header);

        foreach ($requests as $request) {
            $row = [
                $request->id,
                $request->created_at,
                $request->seller_name,
            ];

            // Get form data for this request
            $request_data = $wpdb->get_results($wpdb->prepare("SELECT field_name, field_value FROM $data_table WHERE request_id = %d", $request->id), OBJECT_K);
            foreach($form_fields as $field){
                 $row[] = isset($request_data[$field->field_name]) ? $request_data[$field->field_name]->field_value : '';
            }

            // Get items for this request
            $items = $wpdb->get_results($wpdb->prepare("SELECT title, quantity, description FROM $items_table WHERE request_id = %d", $request->id));
            $items_str = '';
            foreach($items as $item){
                 $items_str .= sprintf("محصول: %s, تعداد: %d, توضیحات: %s | ", $item->title, $item->quantity, $item->description);
            }
            $row[] = rtrim($items_str, ' | ');

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
add_action('admin_init', 'handle_csv_export');


// Display the list of invoice requests
function display_invoice_requests_page() {
    global $wpdb;
    $requests_table = $wpdb->prefix . 'invoice_requests';
    $sellers_table = $wpdb->prefix . 'invoice_sellers';
    $data_table = $wpdb->prefix . 'invoice_request_data';

    // --- Filtering ---
    $filter_seller = isset($_GET['filter_seller']) ? absint($_GET['filter_seller']) : '';
    $filter_start_date = isset($_GET['filter_start_date']) ? sanitize_text_field($_GET['filter_start_date']) : '';
    $filter_end_date = isset($_GET['filter_end_date']) ? sanitize_text_field($_GET['filter_end_date']) : '';

    $where_clauses = [];
    $params = [];

    if ($filter_seller) {
        $where_clauses[] = "r.seller_id = %d";
        $params[] = $filter_seller;
    }
    if ($filter_start_date) {
        $where_clauses[] = "r.created_at >= %s";
        $params[] = $filter_start_date . ' 00:00:00';
    }
    if ($filter_end_date) {
        $where_clauses[] = "r.created_at <= %s";
        $params[] = $filter_end_date . ' 23:59:59';
    }

    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
    }

    // --- Get Data ---
    $sql = "
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

    $prepared_sql = !empty($params) ? $wpdb->prepare($sql, $params) : $sql;
    $requests = $wpdb->get_results($prepared_sql);

    // --- Stats ---
    $total_requests_sql = "SELECT COUNT(id) FROM $requests_table $where_sql";
    $prepared_total_sql = !empty($params) ? $wpdb->prepare($total_requests_sql, $params) : $total_requests_sql;
    $total_requests = $wpdb->get_var($prepared_total_sql);

    $all_sellers = $wpdb->get_results("SELECT id, name FROM $sellers_table ORDER BY name ASC");
    ?>
    <div class="wrap">
        <h1>درخواست‌های پیش‌فاکتور</h1>

        <!-- Stats Box -->
        <div id="dashboard-widgets-wrap">
            <div id="dashboard-widgets" class="metabox-holder">
                <div id="postbox-container-1" class="postbox-container">
                    <div class="meta-box-sortables">
                        <div class="postbox">
                            <h2 class="hndle"><span>آمار کلی</span></h2>
                            <div class="inside">
                                <p><strong>تعداد کل درخواست‌ها (بر اساس فیلتر):</strong> <?php echo esc_html($total_requests); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="get">
            <input type="hidden" name="page" value="invoice-requests">
            <p>
                <select name="filter_seller">
                    <option value="">همه فروشندگان</option>
                    <?php foreach ($all_sellers as $seller) : ?>
                        <option value="<?php echo esc_attr($seller->id); ?>" <?php selected($filter_seller, $seller->id); ?>>
                            <?php echo esc_html($seller->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="filter_start_date" value="<?php echo esc_attr($filter_start_date); ?>">
                <input type="date" name="filter_end_date" value="<?php echo esc_attr($filter_end_date); ?>">
                <input type="submit" class="button" value="فیلتر">
            </p>
        </form>

         <!-- Export Button -->
        <p>
            <?php
            $export_url_params = [
                'page' => 'invoice-requests',
                'export' => 'csv',
                'filter_seller' => $filter_seller,
                'filter_start_date' => $filter_start_date,
                'filter_end_date' => $filter_end_date,
            ];
            $export_url = add_query_arg($export_url_params, admin_url('admin.php'));
            ?>
            <a href="<?php echo wp_nonce_url($export_url, 'invoice_export_nonce'); ?>" class="button button-primary">خروجی CSV</a>
        </p>

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
                        <td colspan="4">هیچ درخواستی با این فیلترها یافت نشد.</td>
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
                    <th scope="row">فایل لیست محصولات</th>
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
                        // Check if the value is a URL and looks like an image or a link to a file
                        if (filter_var($data->field_value, FILTER_VALIDATE_URL)) {
                             if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $data->field_value)) {
                                echo '<a href="' . esc_url($data->field_value) . '" target="_blank">';
                                echo '<img src="' . esc_url($data->field_value) . '" style="max-width: 150px; height: auto;" />';
                                echo '</a>';
                            } else {
                                echo '<a href="' . esc_url($data->field_value) . '" target="_blank">مشاهده فایل</a>';
                            }
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
                        <td colspan="3">هیچ محصولی به صورت دستی برای این درخواست ثبت نشده است.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
