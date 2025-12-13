<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('invoice_form', 'ifp_invoice_form_shortcode');

function ifp_invoice_form_shortcode() {
    global $wpdb;
    $sellers_table = $wpdb->prefix . 'ifp_sellers';
    $fields_table = $wpdb->prefix . 'ifp_form_fields';

    $sellers = $wpdb->get_results("SELECT id, name FROM $sellers_table");
    $form_fields = $wpdb->get_results("SELECT * FROM $fields_table ORDER BY field_order ASC");

    ob_start();
    ?>
    <form id="ifp-invoice-form" method="post" enctype="multipart/form-data">
        <div class="ifp-form-container">

            <div class="ifp-seller-section">
                <div class="ifp-form-group">
                    <label for="seller-select">انتخاب فروشنده</label>
                    <select id="seller-select" name="seller_id" required>
                        <option value="">-- یک فروشنده را انتخاب کنید --</option>
                        <?php foreach ($sellers as $seller) : ?>
                            <option value="<?php echo esc_attr($seller->id); ?>"><?php echo esc_html($seller->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="seller-info-container" style="display: none;">
                    <img id="seller-photo" src="" alt="Seller Photo">
                    <p id="seller-phone"></p>
                </div>
            </div>

            <div class="ifp-customer-section">
                <h3>اطلاعات شما</h3>
                <?php foreach ($form_fields as $field) : ?>
                    <div class="ifp-form-group">
                        <label for="field-<?php echo esc_attr($field->field_name); ?>"><?php echo esc_html($field->field_label); ?></label>
                        <?php
                        $attributes = 'id="field-' . esc_attr($field->field_name) . '" name="' . esc_attr($field->field_name) . '"';
                        if ($field->field_type == 'file' && $field->field_name == 'customer_photo') {
                             $attributes .= ' accept="image/*"'; // Add validation hint
                        }
                        if ($field->field_type === 'textarea') {
                            echo '<textarea ' . $attributes . '></textarea>';
                        } elseif ($field->field_type === 'file') {
                            echo '<input type="file" ' . $attributes . '>';
                        } else {
                            echo '<input type="text" ' . $attributes . '>';
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ifp-items-section">
                <h3>اقلام درخواستی</h3>
                <div id="invoice-items-container">
                    <div class="invoice-item">
                        <div class="ifp-form-group">
                            <label>عنوان محصول</label>
                            <input type="text" name="items[0][product_name]" placeholder="نام محصول">
                        </div>
                        <div class="ifp-form-group">
                            <label>تعداد</label>
                            <input type="text" name="items[0][quantity]" placeholder="تعداد">
                        </div>
                        <div class="ifp-form-group full-width">
                            <label>توضیحات</label>
                            <input type="text" name="items[0][description]" placeholder="توضیحات (اختیاری)">
                        </div>
                        <div class="item-actions">
                            <button type="button" class="remove-item-btn" style="display:none;">&times;</button>
                        </div>
                    </div>
                </div>
                <button type="button" id="add-item-btn">+ افزودن محصول</button>
            </div>

            <div class="ifp-form-submission">
                <?php wp_nonce_field('ifp_form_action', 'ifp_form_nonce'); ?>
                <button type="submit" name="submit_invoice">ثبت درخواست</button>
            </div>
            <div id="ifp-form-messages" style="display:none;"></div>
        </div>
    </form>
    <?php
    return ob_get_clean();
}
