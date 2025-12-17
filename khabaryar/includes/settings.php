<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Add admin menu page for Khabaryar.
 */
function khabaryar_add_admin_menu() {
    add_menu_page(
        __( 'Khabaryar Settings', 'khabaryar' ),
        __( 'Khabaryar', 'khabaryar' ),
        'manage_options',
        'khabaryar',
        'khabaryar_settings_page_html',
        'dashicons-rss',
        20
    );
}
add_action( 'admin_menu', 'khabaryar_add_admin_menu' );

/**
 * Register settings using the Settings API.
 */
function khabaryar_register_settings() {
    register_setting( 'khabaryar_settings_group', 'khabaryar_options', 'khabaryar_options_sanitize' );

    add_settings_section(
        'khabaryar_ai_settings_section',
        __( 'Artificial Intelligence Settings', 'khabaryar' ),
        'khabaryar_ai_settings_section_callback',
        'khabaryar'
    );

    add_settings_field(
        'khabaryar_enable_ai',
        __( 'Enable AI Processing', 'khabaryar' ),
        'khabaryar_enable_ai_callback',
        'khabaryar',
        'khabaryar_ai_settings_section'
    );

    add_settings_field(
        'khabaryar_api_key',
        __( 'API Key', 'khabaryar' ),
        'khabaryar_api_key_callback',
        'khabaryar',
        'khabaryar_ai_settings_section'
    );

    add_settings_field(
        'khabaryar_source_replacement',
        __( 'Source Replacement Name', 'khabaryar' ),
        'khabaryar_source_replacement_callback',
        'khabaryar',
        'khabaryar_ai_settings_section'
    );

    add_settings_field(
        'khabaryar_ai_model',
        __( 'AI Model', 'khabaryar' ),
        'khabaryar_ai_model_callback',
        'khabaryar',
        'khabaryar_ai_settings_section'
    );
}
add_action( 'admin_init', 'khabaryar_register_settings' );

/**
 * Sanitize the options array.
 */
function khabaryar_options_sanitize( $input ) {
    $new_input = array();
    if ( isset( $input['enable_ai'] ) ) {
        $new_input['enable_ai'] = 1;
    }
    if ( isset( $input['api_key'] ) ) {
        $new_input['api_key'] = sanitize_text_field( $input['api_key'] );
    }
    if ( isset( $input['source_replacement'] ) ) {
        $new_input['source_replacement'] = sanitize_text_field( $input['source_replacement'] );
    }
    if ( isset( $input['ai_model'] ) ) {
        $new_input['ai_model'] = sanitize_text_field( $input['ai_model'] );
    }
    return $new_input;
}

/**
 * Renders the section description.
 */
function khabaryar_ai_settings_section_callback() {
    echo '<p>' . esc_html__( 'Enter your API settings below to enable automatic content processing.', 'khabaryar' ) . '</p>';
}

/**
 * Renders the checkbox for enabling AI.
 */
function khabaryar_enable_ai_callback() {
    $options = get_option( 'khabaryar_options' );
    $checked = isset( $options['enable_ai'] ) ? 'checked' : '';
    echo '<input type="checkbox" name="khabaryar_options[enable_ai]" value="1" ' . $checked . ' />';
}

/**
 * Renders the input for the API key.
 */
function khabaryar_api_key_callback() {
    $options = get_option( 'khabaryar_options' );
    $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
    echo '<input type="password" name="khabaryar_options[api_key]" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
}

/**
 * Renders the input for the source replacement.
 */
function khabaryar_source_replacement_callback() {
    $options = get_option( 'khabaryar_options' );
    $source_replacement = isset( $options['source_replacement'] ) ? $options['source_replacement'] : '';
    echo '<input type="text" name="khabaryar_options[source_replacement]" value="' . esc_attr( $source_replacement ) . '" class="regular-text" />';
}

/**
 * Renders the select for the AI model.
 */
function khabaryar_ai_model_callback() {
    $options = get_option( 'khabaryar_options' );
    $model = isset( $options['ai_model'] ) ? $options['ai_model'] : 'gpt-3.5-turbo';
    $models = ['gpt-3.5-turbo' => 'GPT-3.5 Turbo', 'gpt-4' => 'GPT-4'];
    echo '<select name="khabaryar_options[ai_model]">';
    foreach ( $models as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '" ' . selected( $model, $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
}

/**
 * Render the HTML for the settings page.
 */
function khabaryar_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['khabaryar_manual_fetch_nonce'] ) && wp_verify_nonce( $_POST['khabaryar_manual_fetch_nonce'], 'khabaryar_manual_fetch' ) ) {
        $feed_sources = get_posts( ['post_type' => 'khabaryar_feed', 'post_status' => 'publish', 'posts_per_page' => -1] );
        foreach ( $feed_sources as $source ) {
            khabaryar_process_single_feed( $source->ID );
        }
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( 'Feeds processed successfully.', 'khabaryar' ); ?></p>
        </div>
        <?php
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <form action="options.php" method="post">
            <?php
            settings_fields( 'khabaryar_settings_group' );
            do_settings_sections( 'khabaryar' );
            submit_button();
            ?>
        </form>

        <hr>

        <h2><?php _e( 'Manual Fetch', 'khabaryar' ); ?></h2>
        <p><?php _e( 'Use the button below to manually fetch the latest news from all your feed sources.', 'khabaryar' ); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field( 'khabaryar_manual_fetch', 'khabaryar_manual_fetch_nonce' ); ?>
            <?php submit_button( __( 'Fetch All Feeds Manually', 'khabaryar' ), 'primary', 'khabaryar_manual_fetch_submit' ); ?>
        </form>
    </div>
    <?php
}
