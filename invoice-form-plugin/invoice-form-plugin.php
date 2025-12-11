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
        <form id="invoice-form" method="post" enctype="multipart/form-data">
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
                            } else {
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
        details text,
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

    $default_fields = array(
        array('field_name' => 'customer_name', 'field_label' => 'نام و نام خانوادگی', 'field_type' => 'text', 'placeholder' => 'نام خود را بنویسید...', 'is_required' => 1, 'field_order' => 1),
        array('field_name' => 'customer_company', 'field_label' => 'شرکت', 'field_type' => 'text', 'placeholder' => 'نام شرکت خود را بنویسید...', 'is_required' => 0, 'field_order' => 2),
        array('field_name' => 'customer_phone', 'field_label' => 'شماره همراه', 'field_type' => 'text', 'placeholder' => 'مثلا: ۰۹۱۲۳۴۵۶۷۸۹', 'is_required' => 1, 'field_order' => 3),
        array('field_name' => 'customer_email', 'field_label' => 'ایمیل', 'field_type' => 'email', 'placeholder' => 'ایمیل خود را بنویسید...', 'is_required' => 0, 'field_order' => 4),
        array('field_name' => 'customer_notes', 'field_label' => 'توضیحات', 'field_type' => 'textarea', 'placeholder' => 'توضیحات خود را بنویسید...', 'is_required' => 0, 'field_order' => 5),
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

    // Handle file upload first
    $uploaded_file_url = '';
    if ( isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] == 0 ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $upload_overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $_FILES['invoice_file'], $upload_overrides );

        if ( $movefile && !isset( $movefile['error'] ) ) {
            $uploaded_file_url = $movefile['url'];
        } else {
            error_log('File Upload Error: ' . $movefile['error']);
        }
    }

    // Sanitize and prepare data
    $seller_id = isset($_POST['seller_id']) ? absint($_POST['seller_id']) : 0;

    // Insert the main request record
    $wpdb->insert(
        $requests_table_name,
        array(
            'seller_id' => $seller_id,
            'file_path' => $uploaded_file_url,
        )
    );

    $request_id = $wpdb->insert_id;

    // Insert dynamic form fields data
    if ( $request_id > 0 && isset($_POST['form_fields']) && is_array($_POST['form_fields']) ) {
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

    // Insert invoice items from manual entry
    if ( $request_id > 0 && isset($_POST['invoice_items']) && is_array($_POST['invoice_items']) ) {
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
        $name = sanitize_text_field($_POST['seller_name']);
        $details = sanitize_textarea_field($_POST['seller_details']);
        $image_url = esc_url_raw($_POST['seller_image_url']);
        $seller_id = isset($_POST['seller_id']) ? absint($_POST['seller_id']) : 0;

        if ($seller_id > 0) {
            // Update existing seller
            $wpdb->update(
                $sellers_table_name,
                ['name' => $name, 'details' => $details, 'image_url' => $image_url],
                ['id' => $seller_id]
            );
        } else {
            // Add new seller
            $wpdb->insert(
                $sellers_table_name,
                ['name' => $name, 'details' => $details, 'image_url' => $image_url]
            );
        }
        echo '<div class="updated"><p>فروشنده با موفقیت ذخیره شد.</p></div>';
    }

    // Handle seller deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['seller_id'])) {
        $seller_id = absint($_GET['seller_id']);
        $wpdb->delete($sellers_table_name, ['id' => $seller_id]);
        echo '<div class="updated"><p>فروشنده با موفقیت حذف شد.</p></div>';
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
            <input type="hidden" name="seller_id" value="<?php echo $seller_to_edit ? $seller_to_edit->id : ''; ?>">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="seller_name">نام فروشنده</label></th>
                    <td><input type="text" id="seller_name" name="seller_name" value="<?php echo $seller_to_edit ? esc_attr($seller_to_edit->name) : ''; ?>" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="seller_details">مشخصات</label></th>
                    <td><textarea id="seller_details" name="seller_details" rows="5" cols="50"><?php echo $seller_to_edit ? esc_textarea($seller_to_edit->details) : ''; ?></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="seller_image_url">آدرس تصویر</label></th>
                    <td>
                        <input type="text" id="seller_image_url" name="seller_image_url" value="<?php echo $seller_to_edit ? esc_attr($seller_to_edit->image_url) : ''; ?>" class="regular-text">
                        <input type="button" class="button" id="upload_image_button" value="آپلود تصویر">
                        <p class="description">آدرس تصویر فروشنده را وارد کنید یا یک تصویر جدید آپلود کنید.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button($seller_to_edit ? 'ذخیره تغییرات' : 'افزودن فروشنده'); ?>
        </form>

        <hr>

        <!-- List of existing sellers -->
        <h2>لیست فروشندگان</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>نام</th>
                    <th>مشخصات</th>
                    <th>تصویر</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sellers) : ?>
                    <?php foreach ($sellers as $seller) : ?>
                        <tr>
                            <td><?php echo esc_html($seller->name); ?></td>
                            <td><?php echo esc_html($seller->details); ?></td>
                            <td>
                                <?php if ($seller->image_url) : ?>
                                    <img src="<?php echo esc_url($seller->image_url); ?>" width="50" height="50" style="object-fit: cover;">
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=invoice-sellers&action=edit&seller_id=<?php echo $seller->id; ?>">ویرایش</a> |
                                <a href="?page=invoice-sellers&action=delete&seller_id=<?php echo $seller->id; ?>" onclick="return confirm('آیا از حذف این فروشنده مطمئن هستید؟')">حذف</a>
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
                    text: 'انتخاب'
                },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#seller_image_url').val(attachment.url);
            });
            mediaUploader.open();
        });
    });
    </script>
    <?php
}

require_once plugin_dir_path(__FILE__) . 'admin/form-settings-page.php';

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
    $requests = $wpdb->get_results( "
        SELECT
            r.id,
            r.created_at,
            s.name as seller_name,
            (SELECT d.field_value FROM $data_table d WHERE d.request_id = r.id AND d.field_name = 'customer_name' LIMIT 1) as customer_name
        FROM $requests_table r
        LEFT JOIN $sellers_table s ON r.seller_id = s.id
        ORDER BY r.created_at DESC
    " );
    ?>
    <div class="wrap">
        <h1>درخواست‌های پیش‌فاکتور</h1>
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
        SELECT r.*, s.name as seller_name, s.details as seller_details
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
                    <td><?php echo nl2br( esc_html( $data->field_value ) ); ?></td>
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
