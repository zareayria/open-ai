<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register a custom post type for "Feed Source".
 */
function khabaryar_register_feed_source_cpt() {
    $labels = array(
        'name'                  => _x( 'Feed Sources', 'Post type general name', 'khabaryar' ),
        'singular_name'         => _x( 'Feed Source', 'Post type singular name', 'khabaryar' ),
        'menu_name'             => _x( 'Feed Sources', 'Admin Menu text', 'khabaryar' ),
        'name_admin_bar'        => _x( 'Feed Source', 'Add New on Toolbar', 'khabaryar' ),
        'add_new'               => __( 'Add New', 'khabaryar' ),
        'add_new_item'          => __( 'Add New Feed Source', 'khabaryar' ),
        'new_item'              => __( 'New Feed Source', 'khabaryar' ),
        'edit_item'             => __( 'Edit Feed Source', 'khabaryar' ),
        'view_item'             => __( 'View Feed Source', 'khabaryar' ),
        'all_items'             => __( 'All Feed Sources', 'khabaryar' ),
        'search_items'          => __( 'Search Feed Sources', 'khabaryar' ),
        'parent_item_colon'     => __( 'Parent Feed Sources:', 'khabaryar' ),
        'not_found'             => __( 'No feed sources found.', 'khabaryar' ),
        'not_found_in_trash'    => __( 'No feed sources found in Trash.', 'khabaryar' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => 'khabaryar', // Show under Khabaryar menu
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'feed-source' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title' ),
        'menu_icon'          => 'dashicons-database-add',
    );

    register_post_type( 'khabaryar_feed', $args );
}
add_action( 'init', 'khabaryar_register_feed_source_cpt' );

/**
 * Adds a meta box to the "Feed Source" CPT edit screen.
 */
function khabaryar_add_feed_url_meta_box() {
    add_meta_box(
        'khabaryar_feed_url_meta_box',
        __( 'Feed Source Settings', 'khabaryar' ),
        'khabaryar_feed_url_meta_box_html',
        'khabaryar_feed',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'khabaryar_add_feed_url_meta_box' );

/**
 * Renders the HTML for the feed URL meta box.
 */
function khabaryar_feed_url_meta_box_html( $post ) {
    // Get saved meta values
    $feed_url = get_post_meta( $post->ID, '_khabaryar_feed_url', true );
    $post_category = get_post_meta( $post->ID, '_khabaryar_post_category', true );
    $post_author = get_post_meta( $post->ID, '_khabaryar_post_author', true );
    $keywords = get_post_meta( $post->ID, '_khabaryar_keywords', true );
    $skip_no_image = get_post_meta( $post->ID, '_khabaryar_skip_no_image', true );
    $cron_schedule = get_post_meta( $post->ID, '_khabaryar_cron_schedule', true );

    wp_nonce_field( 'khabaryar_save_feed_meta', 'khabaryar_feed_meta_nonce' );
    ?>
    <table class="form-table">
        <tbody>
            <tr>
                <th><label for="khabaryar_feed_url_field"><?php _e( 'Feed RSS URL', 'khabaryar' ); ?></label></th>
                <td>
                    <input type="url" id="khabaryar_feed_url_field" name="khabaryar_feed_url_field" value="<?php echo esc_attr( $feed_url ); ?>" class="widefat" placeholder="https://example.com/feed">
                </td>
            </tr>
            <tr>
                <th><label for="khabaryar_post_category"><?php _e( 'Post Category', 'khabaryar' ); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_categories( array(
                        'show_option_none' => __( 'Select a category', 'khabaryar' ),
                        'name'             => 'khabaryar_post_category',
                        'id'               => 'khabaryar_post_category',
                        'selected'         => $post_category,
                        'hierarchical'     => true,
                        'class'            => 'widefat',
                    ) );
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="khabaryar_post_author"><?php _e( 'Post Author', 'khabaryar' ); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_users( array(
                        'name'              => 'khabaryar_post_author',
                        'id'                => 'khabaryar_post_author',
                        'selected'          => $post_author,
                        'show_option_none'  => __( 'Select an author', 'khabaryar' ),
                        'class'             => 'widefat',
                    ) );
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="khabaryar_keywords"><?php _e( 'Keyword Filter', 'khabaryar' ); ?></label></th>
                <td>
                    <input type="text" id="khabaryar_keywords" name="khabaryar_keywords" value="<?php echo esc_attr( $keywords ); ?>" class="widefat">
                    <p class="description"><?php _e( 'Separate keywords with commas. Prefix with a minus (-) to exclude posts with that keyword. E.g., `apple, -juice`', 'khabaryar' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="khabaryar_skip_no_image"><?php _e( 'Content Options', 'khabaryar' ); ?></label></th>
                <td>
                    <input type="checkbox" id="khabaryar_skip_no_image" name="khabaryar_skip_no_image" value="1" <?php checked( $skip_no_image, 1 ); ?>>
                    <label for="khabaryar_skip_no_image"><?php _e( 'Skip posts without a featured image', 'khabaryar' ); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="khabaryar_cron_schedule"><?php _e( 'Update Schedule', 'khabaryar' ); ?></label></th>
                <td>
                    <select name="khabaryar_cron_schedule" id="khabaryar_cron_schedule">
                        <option value="none" <?php selected( $cron_schedule, 'none' ); ?>><?php _e( 'Manual Only', 'khabaryar' ); ?></option>
                        <?php
                        $schedules = wp_get_schedules();
                        foreach ( $schedules as $name => $schedule ) {
                            echo '<option value="' . esc_attr( $name ) . '" ' . selected( $cron_schedule, $name, false ) . '>' . esc_html( $schedule['display'] ) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

/**
 * Saves the feed source meta box data.
 */
function khabaryar_save_feed_meta_data( $post_id ) {
    if ( ! isset( $_POST['khabaryar_feed_meta_nonce'] ) || ! wp_verify_nonce( $_POST['khabaryar_feed_meta_nonce'], 'khabaryar_save_feed_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = [
        '_khabaryar_feed_url' => 'sanitize_text_field',
        '_khabaryar_post_category' => 'intval',
        '_khabaryar_post_author' => 'intval',
        '_khabaryar_keywords' => 'sanitize_text_field',
        '_khabaryar_skip_no_image' => 'intval',
        '_khabaryar_cron_schedule' => 'sanitize_text_field',
    ];

    foreach ( $fields as $key => $sanitize_callback ) {
        if ( isset( $_POST[ substr( $key, 1 ) ] ) ) {
            $value = call_user_func( $sanitize_callback, $_POST[ substr( $key, 1 ) ] );
            update_post_meta( $post_id, $key, $value );
        } else if ( $key === '_khabaryar_skip_no_image' ) {
            update_post_meta( $post_id, $key, 0 );
        }
    }

    // Handle the cron schedule
    $new_schedule = sanitize_text_field( $_POST['khabaryar_cron_schedule'] );
    wp_clear_scheduled_hook( 'khabaryar_process_single_feed_hook', array( $post_id ) );
    if ( 'none' !== $new_schedule ) {
        wp_schedule_event( time(), $new_schedule, 'khabaryar_process_single_feed_hook', array( $post_id ) );
    }
}
add_action( 'save_post_khabaryar_feed', 'khabaryar_save_feed_meta_data' );
