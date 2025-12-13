<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

add_action( 'admin_menu', 'crwp_add_admin_menu' );
function crwp_add_admin_menu() {
    add_menu_page( 'Content Rewriter', 'Content Rewriter', 'manage_options', 'content-rewriter-auto-publisher', 'crwp_render_settings_page', 'dashicons-edit-page', 100 );
    add_submenu_page( 'content-rewriter-auto-publisher', 'Logs', 'Logs', 'manage_options', 'crwp-logs', 'crwp_render_logs_page' );
}

add_action( 'admin_init', 'crwp_register_settings' );
function crwp_register_settings() {
    register_setting( 'crwp_settings_group', 'crwp_options', 'crwp_sanitize_options' );

    add_settings_section( 'crwp_api_section', 'AI & Rewriting Settings', 'crwp_api_section_callback', 'content-rewriter-auto-publisher' );
    add_settings_field( 'crwp_api_key', 'API Key', 'crwp_api_key_render', 'content-rewriter-auto-publisher', 'crwp_api_section' );
    add_settings_field( 'crwp_prompt_template', 'Prompt Template', 'crwp_prompt_template_render', 'content-rewriter-auto-publisher', 'crwp_api_section' );
    add_settings_field( 'crwp_rewriting_intensity', 'Rewriting Intensity', 'crwp_rewriting_intensity_render', 'content-rewriter-auto-publisher', 'crwp_api_section' );
    add_settings_field( 'crwp_focus_keyword', 'Focus Keyword', 'crwp_focus_keyword_render', 'content-rewriter-auto-publisher', 'crwp_api_section' );

    add_settings_section( 'crwp_publishing_section', 'Publishing Settings', 'crwp_publishing_section_callback', 'content-rewriter-auto-publisher' );
    add_settings_field( 'crwp_post_status', 'Post Status', 'crwp_post_status_render', 'content-rewriter-auto-publisher', 'crwp_publishing_section' );
    add_settings_field( 'crwp_default_category', 'Default Category', 'crwp_default_category_render', 'content-rewriter-auto-publisher', 'crwp_publishing_section' );
    add_settings_field( 'crwp_default_tags', 'Default Tags', 'crwp_default_tags_render', 'content-rewriter-auto-publisher', 'crwp_publishing_section' );
    add_settings_field( 'crwp_add_source_attribution', 'Source Attribution', 'crwp_add_source_attribution_render', 'content-rewriter-auto-publisher', 'crwp_publishing_section' );
    add_settings_field( 'crwp_fallback_image', 'Fallback Image URL', 'crwp_fallback_image_render', 'content-rewriter-auto-publisher', 'crwp_publishing_section' );

    add_settings_section( 'crwp_rules_section', 'Content Rules', 'crwp_rules_section_callback', 'content-rewriter-auto-publisher' );
    add_settings_field( 'crwp_min_content_length', 'Min Content Length', 'crwp_min_content_length_render', 'content-rewriter-auto-publisher', 'crwp_rules_section' );
    add_settings_field( 'crwp_max_content_length', 'Max Content Length', 'crwp_max_content_length_render', 'content-rewriter-auto-publisher', 'crwp_rules_section' );

    add_settings_section( 'crwp_scheduling_section', 'Scheduling Settings', 'crwp_scheduling_section_callback', 'content-rewriter-auto-publisher' );
    add_settings_field( 'crwp_schedule_interval', 'Processing Interval (minutes)', 'crwp_schedule_interval_render', 'content-rewriter-auto-publisher', 'crwp_scheduling_section' );
    add_settings_field( 'crwp_posts_per_run', 'Posts per Run', 'crwp_posts_per_run_render', 'content-rewriter-auto-publisher', 'crwp_scheduling_section' );

    add_settings_section( 'crwp_advanced_section', 'Advanced Features', 'crwp_advanced_section_callback', 'content-rewriter-auto-publisher' );
    add_settings_field( 'crwp_dry_run_mode', 'Dry Run Mode', 'crwp_dry_run_mode_render', 'content-rewriter-auto-publisher', 'crwp_advanced_section' );
}

function crwp_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Content Rewriter & Auto-Publisher</h1>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="crwp_process_urls">
            <?php wp_nonce_field( 'crwp_process_urls_action', 'crwp_process_nonce' ); ?>
            <h2>Add URLs to Queue</h2>
            <p>Enter URLs to add to the processing queue, one per line.</p>
            <textarea name="crwp_urls_to_queue" rows="10" cols="80"></textarea>
            <?php submit_button( 'Add URLs to Queue' ); ?>
        </form>
        <hr>
        <h2>Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'crwp_settings_group' );
            do_settings_sections( 'content-rewriter-auto-publisher' );
            submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}

function crwp_sanitize_options( $input ) {
    $new_input = [];
    $new_input['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
    $new_input['prompt_template'] = sanitize_textarea_field( $input['prompt_template'] ?? '' );
    $new_input['rewriting_intensity'] = sanitize_text_field( $input['rewriting_intensity'] ?? 'medium' );
    $new_input['focus_keyword'] = sanitize_text_field( $input['focus_keyword'] ?? '' );
    $new_input['post_status'] = sanitize_text_field( $input['post_status'] ?? 'draft' );
    $new_input['default_category'] = absint( $input['default_category'] ?? 0 );
    $new_input['default_tags'] = sanitize_text_field( $input['default_tags'] ?? '' );
    $new_input['fallback_image'] = esc_url_raw( $input['fallback_image'] ?? '' );
    $new_input['min_content_length'] = absint( $input['min_content_length'] ?? 100 );
    $new_input['max_content_length'] = absint( $input['max_content_length'] ?? 2000 );
    $new_input['schedule_interval'] = absint( $input['schedule_interval'] ?? 60 );
    $new_input['posts_per_run'] = absint( $input['posts_per_run'] ?? 1 );
    $new_input['add_source_attribution'] = isset( $input['add_source_attribution'] ) ? 1 : 0;
    $new_input['dry_run_mode'] = isset( $input['dry_run_mode'] ) ? 1 : 0;
    return $new_input;
}

// Callbacks
function crwp_api_section_callback() { echo 'Configure the AI service.'; }
function crwp_publishing_section_callback() { echo 'Default settings for new posts.'; }
function crwp_rules_section_callback() { echo 'Rules for content processing.'; }
function crwp_scheduling_section_callback() { echo 'Configure the automatic processing schedule.'; }
function crwp_advanced_section_callback() { echo 'Advanced features.'; }

// Renderers
function crwp_api_key_render() { $options = get_option('crwp_options'); echo '<input type="password" name="crwp_options[api_key]" value="'.esc_attr($options['api_key'] ?? '').'" class="regular-text">'; }
function crwp_prompt_template_render() { $options = get_option('crwp_options'); echo '<textarea name="crwp_options[prompt_template]" rows="5" cols="80">'.esc_textarea($options['prompt_template'] ?? 'Rewrite: [CONTENT]').'</textarea>'; }
function crwp_rewriting_intensity_render() { $options = get_option('crwp_options'); $val = $options['rewriting_intensity'] ?? 'medium'; echo '<select name="crwp_options[rewriting_intensity]"><option value="low" '.selected($val, 'low', false).'>Low</option><option value="medium" '.selected($val, 'medium', false).'>Medium</option><option value="high" '.selected($val, 'high', false).'>High</option></select>'; }
function crwp_focus_keyword_render() { $options = get_option('crwp_options'); echo '<input type="text" name="crwp_options[focus_keyword]" value="'.esc_attr($options['focus_keyword'] ?? '').'" class="regular-text">'; }
function crwp_post_status_render() { $options = get_option('crwp_options'); $val = $options['post_status'] ?? 'draft'; echo '<select name="crwp_options[post_status]"><option value="draft" '.selected($val, 'draft', false).'>Draft</option><option value="pending" '.selected($val, 'pending', false).'>Pending</option><option value="publish" '.selected($val, 'publish', false).'>Publish</option></select>'; }
function crwp_default_category_render() { $options = get_option('crwp_options'); wp_dropdown_categories(['name' => 'crwp_options[default_category]', 'selected' => $options['default_category'] ?? 0, 'show_option_none' => 'Select Category', 'hierarchical' => true]); }
function crwp_default_tags_render() { $options = get_option('crwp_options'); echo '<input type="text" name="crwp_options[default_tags]" value="'.esc_attr($options['default_tags'] ?? '').'" class="regular-text">'; }
function crwp_fallback_image_render() { $options = get_option('crwp_options'); echo '<input type="text" name="crwp_options[fallback_image]" value="'.esc_attr($options['fallback_image'] ?? '').'" class="regular-text">'; }
function crwp_min_content_length_render() { $options = get_option('crwp_options'); echo '<input type="number" name="crwp_options[min_content_length]" value="'.esc_attr($options['min_content_length'] ?? 100).'" class="small-text">'; }
function crwp_max_content_length_render() { $options = get_option('crwp_options'); echo '<input type="number" name="crwp_options[max_content_length]" value="'.esc_attr($options['max_content_length'] ?? 2000).'" class="small-text">'; }
function crwp_schedule_interval_render() { $options = get_option('crwp_options'); echo '<input type="number" name="crwp_options[schedule_interval]" value="'.esc_attr($options['schedule_interval'] ?? 60).'" class="small-text">'; }
function crwp_posts_per_run_render() { $options = get_option('crwp_options'); echo '<input type="number" name="crwp_options[posts_per_run]" value="'.esc_attr($options['posts_per_run'] ?? 1).'" class="small-text">'; }
function crwp_add_source_attribution_render() { $options = get_option('crwp_options'); echo '<input type="checkbox" name="crwp_options[add_source_attribution]" value="1" '.checked($options['add_source_attribution'] ?? 0, 1, false).'>'; }
function crwp_dry_run_mode_render() { $options = get_option('crwp_options'); echo '<input type="checkbox" name="crwp_options[dry_run_mode]" value="1" '.checked($options['dry_run_mode'] ?? 0, 1, false).'>'; }
