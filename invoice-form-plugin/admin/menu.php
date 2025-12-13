<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'ifp_admin_menu');

function ifp_admin_menu() {
    add_menu_page('مدیریت فرم', 'مدیریت فرم', 'manage_options', 'ifp-main-menu', null, 'dashicons-forms', 20);
    add_submenu_page('ifp-main-menu', 'لیست درخواست ها', 'لیست درخواست ها', 'manage_options', 'ifp-requests-list', 'ifp_requests_list_page');
    add_submenu_page('ifp-main-menu', 'مدیریت فروشندگان', 'مدیریت فروشندگان', 'manage_options', 'ifp-seller-management', 'ifp_seller_management_page');
    add_submenu_page('ifp-main-menu', 'تنظیمات فرم', 'تنظیمات فرم', 'manage_options', 'ifp-form-settings', 'ifp_form_settings_page');
}

// Seller Management Page
function ifp_seller_management_page() {
    global $wpdb;
    $sellers_table = $wpdb->prefix . 'ifp_sellers';
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Handle form submission for add/edit
    if (isset($_POST['submit_seller'])) {
        $name = sanitize_text_field($_POST['seller_name']);
        $phone = sanitize_text_field($_POST['seller_phone']);
        $photo_path = isset($_POST['existing_photo']) ? sanitize_text_field($_POST['existing_photo']) : '';

        if (isset($_FILES['seller_photo']) && $_FILES['seller_photo']['error'] == UPLOAD_ERR_OK) {
             $validation_result = ifp_validate_image_size($_FILES['seller_photo']);
            if (is_wp_error($validation_result)) {
                wp_die($validation_result->get_error_message());
            }
            $photo_path = ifp_handle_upload('seller_photo');
        }

        $data = ['name' => $name, 'phone' => $phone, 'photo_path' => $photo_path];

        if ($id > 0) {
            $wpdb->update($sellers_table, $data, ['id' => $id]);
        } else {
            $wpdb->insert($sellers_table, $data);
        }
        echo '<div class="updated"><p>فروشنده با موفقیت ذخیره شد.</p></div>';
        $action = 'list'; // Switch back to list view
    }

    // Handle delete
    if ($action == 'delete' && $id > 0) {
        $wpdb->delete($sellers_table, ['id' => $id]);
        echo '<div class="updated"><p>فروشنده حذف شد.</p></div>';
        $action = 'list';
    }

    echo '<div class="wrap">';
    if ($action == 'add' || $action == 'edit') {
        $seller = ($id > 0) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $sellers_table WHERE id = %d", $id)) : null;
        ?>
        <h1><?php echo $id > 0 ? 'ویرایش فروشنده' : 'افزودن فروشنده جدید'; ?></h1>
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th><label for="seller_name">نام</label></th>
                    <td><input type="text" name="seller_name" id="seller_name" value="<?php echo $seller ? esc_attr($seller->name) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="seller_phone">تلفن</label></th>
                    <td><input type="text" name="seller_phone" id="seller_phone" value="<?php echo $seller ? esc_attr($seller->phone) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="seller_photo">عکس (250x250)</label></th>
                    <td>
                        <input type="file" name="seller_photo" id="seller_photo">
                        <?php if ($seller && $seller->photo_path) :
                            $upload_dir = wp_upload_dir();
                        ?>
                            <p>عکس فعلی: <img src="<?php echo esc_url($upload_dir['baseurl'] . $seller->photo_path); ?>" width="50"></p>
                            <input type="hidden" name="existing_photo" value="<?php echo esc_attr($seller->photo_path); ?>">
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit_seller" class="button-primary" value="ذخیره فروشنده">
                <a href="?page=ifp-seller-management" class="button">انصراف</a>
            </p>
        </form>
        <?php
    } else {
        $sellers = $wpdb->get_results("SELECT * FROM $sellers_table ORDER BY id DESC");
        ?>
        <h1>مدیریت فروشندگان</h1>
        <a href="?page=ifp-seller-management&action=add" class="page-title-action">افزودن جدید</a>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>نام</th><th>تلفن</th><th>عکس</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if ($sellers) : foreach ($sellers as $seller) :
                $photo_url = $seller->photo_path ? esc_url(wp_upload_dir()['baseurl'] . $seller->photo_path) : '';
            ?>
                <tr>
                    <td><?php echo esc_html($seller->name); ?></td>
                    <td><?php echo esc_html($seller->phone); ?></td>
                    <td><?php if($photo_url) echo '<img src="'.$photo_url.'" width="50">'; ?></td>
                    <td>
                        <a href="?page=ifp-seller-management&action=edit&id=<?php echo $seller->id; ?>">ویرایش</a> |
                        <a href="?page=ifp-seller-management&action=delete&id=<?php echo $seller->id; ?>" onclick="return confirm('آیا مطمئن هستید؟')">حذف</a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4">هیچ فروشنده‌ای یافت نشد.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    echo '</div>';
}

// Form Settings Page
function ifp_form_settings_page() {
    global $wpdb;
    $fields_table = $wpdb->prefix . 'ifp_form_fields';

    // Handle reordering
    if (isset($_POST['save_order'])) {
        parse_str($_POST['order'], $order);
        foreach ($order['field'] as $position => $id) {
            $wpdb->update($fields_table, ['field_order' => $position + 1], ['id' => intval($id)]);
        }
        echo '<div class="updated"><p>ترتیب فیلدها ذخیره شد.</p></div>';
    }

    // Handle add/edit
    if (isset($_POST['submit_field'])) {
        $label = sanitize_text_field($_POST['field_label']);
        $type = sanitize_text_field($_POST['field_type']);
        $name = 'custom_' . sanitize_key(str_replace(' ', '_', $label)); // Generate name
        $wpdb->insert($fields_table, ['field_label' => $label, 'field_type' => $type, 'field_name' => $name, 'field_order' => 99]);
         echo '<div class="updated"><p>فیلد جدید اضافه شد.</p></div>';
    }

    // Handle delete
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($fields_table, ['id' => $id, 'is_default' => 0]); // Can't delete default fields
         echo '<div class="updated"><p>فیلد حذف شد.</p></div>';
    }

    $fields = $wpdb->get_results("SELECT * FROM $fields_table ORDER BY field_order ASC");
    ?>
    <div class="wrap">
        <h1>تنظیمات فرم</h1>
        <h2>فیلدهای فرم مشتری</h2>
        <p>فیلدها را بکشید و رها کنید تا ترتیب نمایش آن‌ها در فرم تغییر کند.</p>
        <form method="post">
             <ul id="sortable-fields">
                <?php foreach ($fields as $field) : ?>
                    <li id="field_<?php echo $field->id; ?>" class="ui-state-default">
                        <span class="dashicons dashicons-move"></span>
                        <?php echo esc_html($field->field_label); ?> (نوع: <?php echo esc_html($field->field_type); ?>)
                        <?php if (!$field->is_default) : ?>
                            <a href="?page=ifp-form-settings&action=delete&id=<?php echo $field->id; ?>" class="delete-field">حذف</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <input type="hidden" name="order" id="field-order" />
            <p class="submit">
                <input type="submit" name="save_order" class="button-primary" value="ذخیره ترتیب">
            </p>
        </form>

        <hr>

        <h2>افزودن فیلد جدید</h2>
        <form method="post">
            <table class="form-table">
                 <tr>
                    <th><label for="field_label">عنوان فیلد</label></th>
                    <td><input type="text" name="field_label" id="field_label" required></td>
                </tr>
                 <tr>
                    <th><label for="field_type">نوع فیلد</label></th>
                    <td>
                        <select name="field_type" id="field_type">
                            <option value="text">متن</option>
                            <option value="textarea">متن بلند</option>
                            <option value="file">فایل</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="submit_field" class="button-primary" value="افزودن فیلد"></p>
        </form>
    </div>
    <script>
    jQuery(function($) {
        $("#sortable-fields").sortable({
            update: function(event, ui) {
                var order = $(this).sortable('serialize');
                $('#field-order').val(order);
            }
        }).disableSelection();
    });
    </script>
    <?php
}

// Requests List Page
function ifp_requests_list_page() {
    global $wpdb;
    $requests_table = $wpdb->prefix . 'ifp_requests';
    $items_table = $wpdb->prefix . 'ifp_request_items';
    $sellers_table = $wpdb->prefix . 'ifp_sellers';
    $fields_table = $wpdb->prefix . 'ifp_form_fields';

    $upload_dir = wp_upload_dir();

    // Get all sellers for filter dropdown
    $sellers = $wpdb->get_results("SELECT id, name FROM $sellers_table");

    // Handle filters
    $where_clauses = [];
    if (!empty($_GET['seller_filter'])) {
        $where_clauses[] = $wpdb->prepare("r.seller_id = %d", intval($_GET['seller_filter']));
    }
    if (!empty($_GET['customer_filter'])) {
        $customer_search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['customer_filter'])) . '%';
        $where_clauses[] = $wpdb->prepare("r.form_data LIKE %s", $customer_search);
    }
    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

    // Handle CSV export
    if (isset($_GET['export']) && $_GET['export'] == 'csv') {
        $requests = $wpdb->get_results("SELECT r.*, s.name as seller_name FROM $requests_table r LEFT JOIN $sellers_table s ON r.seller_id = s.id $where_sql ORDER BY r.created_at DESC");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=requests.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'فروشنده', 'تاریخ', 'نام مشتری', 'موبایل مشتری', 'سایر اطلاعات', 'اقلام'));

        foreach ($requests as $request) {
            $form_data = json_decode($request->form_data, true);
            $customer_name = isset($form_data['customer_name']) ? $form_data['customer_name'] : '';
            $customer_mobile = isset($form_data['customer_mobile']) ? $form_data['customer_mobile'] : '';
            unset($form_data['customer_name'], $form_data['customer_mobile'], $form_data['customer_photo'], $form_data['seller_id']); // Remove main fields for "other"
            $other_data_str = json_encode($form_data, JSON_UNESCAPED_UNICODE);

            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $items_table WHERE request_id = %d", $request->id));
            $items_str = '';
            foreach($items as $item) {
                $items_str .= sprintf("محصول: %s, تعداد: %s, توضیحات: %s | ", $item->product_name, $item->quantity, $item->description);
            }

            fputcsv($output, [$request->id, $request->seller_name, $request->created_at, $customer_name, $customer_mobile, $other_data_str, rtrim($items_str, ' | ')]);
        }
        fclose($output);
        exit;
    }

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($items_table, array('request_id' => $id));
        $wpdb->delete($requests_table, array('id' => $id));
        echo '<div class="updated"><p>درخواست حذف شد.</p></div>';
    }

    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    echo '<div class="wrap">';
    if ($action == 'view' && $id > 0) {
        // View single request
        $request = $wpdb->get_row($wpdb->prepare("SELECT r.*, s.name as seller_name FROM $requests_table r LEFT JOIN $sellers_table s ON r.seller_id = s.id WHERE r.id = %d", $id));
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $items_table WHERE request_id = %d", $id));
        $form_fields = $wpdb->get_results("SELECT field_label, field_name FROM $fields_table");

        echo '<h1>جزئیات درخواست</h1>';
        if ($request) {
            echo '<p><strong>فروشنده:</strong> ' . esc_html($request->seller_name) . '</p>';
            $data = json_decode($request->form_data, true);
            foreach ($form_fields as $field) {
                if (isset($data[$field->field_name])) {
                     $value = $data[$field->field_name];
                     if (is_string($value) && strpos($value, '/ifp_uploads/') === 0) { // Check if it's a file path
                         echo '<p><strong>' . esc_html($field->field_label) . ':</strong> <a href="' . esc_url($upload_dir['baseurl'] . $value) . '" target="_blank">مشاهده فایل</a></p>';
                     } else {
                         echo '<p><strong>' . esc_html($field->field_label) . ':</strong> ' . esc_html($value) . '</p>';
                     }
                }
            }

            echo '<h2>اقلام درخواستی</h2>';
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>نام محصول</th><th>تعداد</th><th>توضیحات</th></tr></thead><tbody>';
            foreach ($items as $item) {
                echo '<tr><td>' . esc_html($item->product_name) . '</td><td>' . esc_html($item->quantity) . '</td><td>' . esc_html($item->description) . '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>درخواست یافت نشد.</p>';
        }
         echo '<a href="?page=ifp-requests-list" class="button">بازگشت به لیست</a>';
    } else {
        // List all requests
        $requests = $wpdb->get_results("SELECT r.id, r.created_at, s.name as seller_name, r.form_data FROM $requests_table r LEFT JOIN $sellers_table s ON r.seller_id = s.id $where_sql ORDER BY r.created_at DESC");

        // Stats
        $total_requests = $wpdb->get_var("SELECT COUNT(*) FROM $requests_table");

        ?>
        <h1>لیست درخواست ها</h1>

        <div class="summary-stats">
            <strong>تعداد کل درخواست ها: </strong> <?php echo $total_requests; ?>
        </div>

        <form method="get">
            <input type="hidden" name="page" value="ifp-requests-list">
            <select name="seller_filter">
                <option value="">همه فروشندگان</option>
                <?php foreach ($sellers as $seller) : ?>
                    <option value="<?php echo $seller->id; ?>" <?php selected(isset($_GET['seller_filter']), $seller->id); ?>><?php echo esc_html($seller->name); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="customer_filter" placeholder="جستجوی نام/موبایل مشتری..." value="<?php echo isset($_GET['customer_filter']) ? esc_attr($_GET['customer_filter']) : ''; ?>">
            <input type="submit" class="button" value="فیلتر">
             <a href="?page=ifp-requests-list" class="button">پاک کردن فیلترها</a>
        </form>

        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo add_query_arg('export', 'csv'); ?>" class="button">خروجی CSV</a>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>ID</th><th>فروشنده</th><th>مشتری</th><th>موبایل</th><th>تاریخ</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if ($requests) : foreach ($requests as $request) :
                $data = json_decode($request->form_data, true);
                $customer_name = isset($data['customer_name']) ? esc_html($data['customer_name']) : 'N/A';
                $customer_mobile = isset($data['customer_mobile']) ? esc_html($data['customer_mobile']) : 'N/A';
            ?>
                <tr>
                    <td><?php echo $request->id; ?></td>
                    <td><?php echo esc_html($request->seller_name); ?></td>
                    <td><?php echo $customer_name; ?></td>
                    <td><?php echo $customer_mobile; ?></td>
                    <td><?php echo $request->created_at; ?></td>
                    <td>
                        <a href="?page=ifp-requests-list&action=view&id=<?php echo $request->id; ?>">مشاهده</a> |
                        <a href="?page=ifp-requests-list&action=delete&id=<?php echo $request->id; ?>" onclick="return confirm('آیا مطمئن هستید؟')">حذف</a>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6">هیچ درخواستی یافت نشد.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    echo '</div>';
}
