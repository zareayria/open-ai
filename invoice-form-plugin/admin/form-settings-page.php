<?php
// admin/form-settings-page.php

function display_form_settings_page() {
    global $wpdb;
    $fields_table_name = $wpdb->prefix . 'invoice_form_fields';

    // Handle form submissions for adding/editing a field
    if (isset($_POST['submit_field'])) {
        $field_label = sanitize_text_field($_POST['field_label']);
        $field_name = sanitize_key($_POST['field_name']); // Sanitize as a key
        $field_type = sanitize_text_field($_POST['field_type']);
        $placeholder = sanitize_text_field($_POST['placeholder']);
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $field_id = isset($_POST['field_id']) ? absint($_POST['field_id']) : 0;

        // Simple validation
        if (empty($field_label) || empty($field_name) || empty($field_type)) {
            echo '<div class="error"><p>لطفاً تمام فیلدهای اجباری (برچسب، نام، نوع) را پر کنید.</p></div>';
        } else {
            $data = [
                'field_label' => $field_label,
                'field_name' => $field_name,
                'field_type' => $field_type,
                'placeholder' => $placeholder,
                'is_required' => $is_required,
            ];

            if ($field_id > 0) {
                // Update existing field
                $wpdb->update($fields_table_name, $data, ['id' => $field_id]);
                echo '<div class="updated"><p>فیلد با موفقیت به‌روزرسانی شد.</p></div>';
            } else {
                // Add new field - get max order and add 1
                $max_order = $wpdb->get_var("SELECT MAX(field_order) FROM $fields_table_name");
                $data['field_order'] = $max_order + 1;
                $wpdb->insert($fields_table_name, $data);
                echo '<div class="updated"><p>فیلد جدید با موفقیت اضافه شد.</p></div>';
            }
            // Performance optimization: Invalidate form fields cache
            delete_transient('invoice_form_fields');
        }
    }

    // Handle field deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['field_id'])) {
        $field_id = absint($_GET['field_id']);
        // Add nonce check for security
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_field_' . $field_id)) {
            $wpdb->delete($fields_table_name, ['id' => $field_id]);
            // Performance optimization: Invalidate form fields cache
            delete_transient('invoice_form_fields');
            echo '<div class="updated"><p>فیلد با موفقیت حذف شد.</p></div>';
        }
    }

    // Get field to edit if in edit mode
    $field_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['field_id'])) {
        $field_id = absint($_GET['field_id']);
        $field_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fields_table_name WHERE id = %d", $field_id));
    }

    $fields = $wpdb->get_results("SELECT * FROM $fields_table_name ORDER BY field_order ASC");

    // Enqueue jQuery UI Sortable
    wp_enqueue_script('jquery-ui-sortable');
    ?>
    <div class="wrap">
        <h1>تنظیمات فرم</h1>

        <!-- Form for adding/editing a field -->
        <h2><?php echo $field_to_edit ? 'ویرایش فیلد' : 'افزودن فیلد جدید'; ?></h2>
        <form method="post" action="?page=invoice-form-settings">
            <input type="hidden" name="field_id" value="<?php echo $field_to_edit ? $field_to_edit->id : ''; ?>">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="field_label">برچسب فیلد</label></th>
                    <td><input type="text" id="field_label" name="field_label" value="<?php echo $field_to_edit ? esc_attr($field_to_edit->field_label) : ''; ?>" required></td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="field_name">نام فیلد (کلید)</label></th>
                    <td>
                        <input type="text" id="field_name" name="field_name" value="<?php echo $field_to_edit ? esc_attr($field_to_edit->field_name) : ''; ?>" required <?php echo $field_to_edit ? 'readonly' : ''; ?>>
                        <p class="description">این نام در دیتابیس ذخیره می‌شود و باید به انگلیسی و بدون فاصله باشد (مثال: customer_address). پس از ایجاد، قابل تغییر نیست.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="field_type">نوع فیلد</label></th>
                    <td>
                        <select id="field_type" name="field_type" required>
                            <option value="text" <?php selected($field_to_edit ? $field_to_edit->field_type : '', 'text'); ?>>متن (Text)</option>
                            <option value="email" <?php selected($field_to_edit ? $field_to_edit->field_type : '', 'email'); ?>>ایمیل (Email)</option>
                            <option value="textarea" <?php selected($field_to_edit ? $field_to_edit->field_type : '', 'textarea'); ?>>ناحیه متنی (Textarea)</option>
                            <option value="file" <?php selected($field_to_edit ? $field_to_edit->field_type : '', 'file'); ?>>فایل (File)</option>
                        </select>
                        <p class="description">برای فیلدهای فایل، اعتبارسنجی ابعاد تصویر (۲۵۰×۲۵۰ پیکسل) به صورت خودکار اعمال می‌شود.</p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="placeholder">متن جایگزین (Placeholder)</label></th>
                    <td><input type="text" id="placeholder" name="placeholder" value="<?php echo $field_to_edit ? esc_attr($field_to_edit->placeholder) : ''; ?>"></td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="is_required">اجباری</label></th>
                    <td><input type="checkbox" id="is_required" name="is_required" value="1" <?php checked($field_to_edit ? $field_to_edit->is_required : 0, 1); ?>></td>
                </tr>
            </table>
            <?php submit_button($field_to_edit ? 'ذخیره تغییرات' : 'افزودن فیلد'); ?>
        </form>

        <hr>

        <!-- List of existing fields -->
        <h2>فیلدهای موجود</h2>
        <p>برای مرتب‌سازی، فیلدها را بکشید و رها کنید.</p>
        <table id="form-fields-table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">ترتیب</th>
                    <th>برچسب</th>
                    <th>نام (کلید)</th>
                    <th>نوع</th>
                    <th>اجباری</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody id="sortable-list">
                <?php if ($fields) : ?>
                    <?php foreach ($fields as $field) : ?>
                        <tr data-id="<?php echo $field->id; ?>">
                            <td class="order-handle" style="cursor: move; text-align: center;"><span class="dashicons dashicons-menu"></span></td>
                            <td><?php echo esc_html($field->field_label); ?></td>
                            <td><?php echo esc_html($field->field_name); ?></td>
                            <td><?php echo esc_html($field->field_type); ?></td>
                            <td><?php echo $field->is_required ? 'بله' : 'خیر'; ?></td>
                            <td>
                                <a href="?page=invoice-form-settings&action=edit&field_id=<?php echo $field->id; ?>">ویرایش</a> |
                                <a href="<?php echo wp_nonce_url('?page=invoice-form-settings&action=delete&field_id=' . $field->id, 'delete_field_' . $field->id); ?>" onclick="return confirm('آیا از حذف این فیلد مطمئن هستید؟')">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6">هیچ فیلدی یافت نشد.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Make the table rows sortable
        $("#sortable-list").sortable({
            handle: '.order-handle',
            update: function(event, ui) {
                var newOrder = [];
                $('#sortable-list tr').each(function() {
                    newOrder.push($(this).data('id'));
                });

                // AJAX request to save the new order
                $.ajax({
                    url: ajaxurl, // WordPress AJAX URL
                    type: 'POST',
                    data: {
                        action: 'update_field_order', // Custom action name
                        order: newOrder,
                        nonce: '<?php echo wp_create_nonce("field_order_nonce"); ?>'
                    },
                    success: function(response) {
                        // Optional: show a success message or handle errors
                        console.log(response);
                    }
                });
            }
        }).disableSelection();

        // Auto-generate field name from label (and sanitize it)
        $('#field_label').on('keyup', function() {
            // Only do this if we are NOT in edit mode
            if ($('input[name="field_id"]').val() === '') {
                var label = $(this).val();
                // Replace spaces with underscores, remove special chars, and convert to lowercase
                var name = label.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
                $('#field_name').val(name);
            }
        });
    });
    </script>
    <?php
}

// AJAX handler for updating field order
function update_field_order_callback() {
    // Verify nonce
    check_ajax_referer('field_order_nonce', 'nonce');

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

        // Performance optimization: Invalidate form fields cache
        delete_transient('invoice_form_fields');

        wp_send_json_success('Order updated.');
    } else {
        wp_send_json_error('Invalid data.');
    }
}
add_action('wp_ajax_update_field_order', 'update_field_order_callback');
