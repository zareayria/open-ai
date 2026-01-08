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
    $feed_type = get_post_meta( $post->ID, '_khabaryar_feed_type', true ) ?: 'rss';

    wp_nonce_field( 'khabaryar_save_feed_meta', 'khabaryar_feed_meta_nonce' );
    ?>
    <table class="form-table">
        <tbody>
            <tr>
                <th><label for="khabaryar_feed_type"><?php _e( 'Feed Type', 'khabaryar' ); ?></label></th>
                <td>
                    <select name="khabaryar_feed_type" id="khabaryar_feed_type">
                        <option value="rss" <?php selected( $feed_type, 'rss' ); ?>><?php _e( 'RSS Feed', 'khabaryar' ); ?></option>
                        <option value="sitemap" <?php selected( $feed_type, 'sitemap' ); ?>><?php _e( 'XML Sitemap', 'khabaryar' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="khabaryar_feed_url_field"><?php _e( 'Feed URL', 'khabaryar' ); ?></label></th>
                <td>
                    <input type="url" id="khabaryar_feed_url_field" name="khabaryar_feed_url_field" value="<?php echo esc_attr( $feed_url ); ?>" class="widefat" placeholder="https://example.com/feed or /sitemap.xml">
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
            <tr>
                <th><label for="khabaryar_enable_translation"><?php _e( 'Translation', 'khabaryar' ); ?></label></th>
                <td>
                    <input type="checkbox" id="khabaryar_enable_translation" name="khabaryar_enable_translation" value="1" <?php checked( get_post_meta( $post->ID, '_khabaryar_enable_translation', true ), 1 ); ?>>
                    <label for="khabaryar_enable_translation"><?php _e( 'Translate content to Persian before processing', 'khabaryar' ); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="khabaryar_max_items"><?php _e( 'Limit Items', 'khabaryar' ); ?></label></th>
                <td>
                    <input type="number" id="khabaryar_max_items" name="khabaryar_max_items" value="<?php echo esc_attr( get_post_meta( $post->ID, '_khabaryar_max_items', true ) ?: 5 ); ?>" min="1" max="50">
                    <p class="description"><?php _e( 'Maximum number of news items to import per fetch.', 'khabaryar' ); ?></p>
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
        'khabaryar_feed_url_field' => '_khabaryar_feed_url',
        'khabaryar_post_category' => '_khabaryar_post_category',
        'khabaryar_post_author' => '_khabaryar_post_author',
        'khabaryar_keywords' => '_khabaryar_keywords',
        'khabaryar_cron_schedule' => '_khabaryar_cron_schedule',
        'khabaryar_max_items' => '_khabaryar_max_items',
        'khabaryar_feed_type' => '_khabaryar_feed_type',
    ];

    foreach ( $fields as $post_key => $meta_key ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
        }
    }

    // Handle checkboxes separately
    update_post_meta( $post_id, '_khabaryar_skip_no_image', isset( $_POST['khabaryar_skip_no_image'] ) ? 1 : 0 );
    update_post_meta( $post_id, '_khabaryar_enable_translation', isset( $_POST['khabaryar_enable_translation'] ) ? 1 : 0 );

    // Handle the cron schedule
    $new_schedule = sanitize_text_field( $_POST['khabaryar_cron_schedule'] );
    wp_clear_scheduled_hook( 'khabaryar_process_single_feed_hook', array( $post_id ) );
    if ( 'none' !== $new_schedule ) {
        wp_schedule_event( time(), $new_schedule, 'khabaryar_process_single_feed_hook', array( $post_id ) );
    }
}
add_action( 'save_post_khabaryar_feed', 'khabaryar_save_feed_meta_data' );

/**
 * Add custom columns to the feed source list table.
 */
function khabaryar_add_feed_source_columns( $columns ) {
    $columns['next_run'] = __( 'Next Scheduled Run', 'khabaryar' );
    return $columns;
}
add_filter( 'manage_khabaryar_feed_posts_columns', 'khabaryar_add_feed_source_columns' );

/**
 * Display content for custom columns.
 */
function khabaryar_feed_source_custom_column( $column, $post_id ) {
    if ( 'next_run' === $column ) {
        $timestamp = wp_next_scheduled( 'khabaryar_process_single_feed_hook', array( $post_id ) );
        if ( $timestamp ) {
            // Use date_i18n to respect the site's date/time format and timezone
            echo esc_html( date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $timestamp ) );
        } else {
            echo '—';
        }
    }
}
add_action( 'manage_khabaryar_feed_posts_custom_column', 'khabaryar_feed_source_custom_column', 10, 2 );

/**
 * Add a "Fetch Now" link to the row actions.
 */
function khabaryar_add_fetch_now_link( $actions, $post ) {
    if ( $post->post_type === 'khabaryar_feed' ) {
        $url = add_query_arg( [
            'action' => 'khabaryar_fetch_single',
            'post_id' => $post->ID,
            '_wpnonce' => wp_create_nonce( 'khabaryar_fetch_single_nonce' ),
        ], admin_url( 'edit.php?post_type=khabaryar_feed' ) );
        $actions['fetch_now'] = '<a href="' . esc_url( $url ) . '">' . __( 'Fetch Now', 'khabaryar' ) . '</a>';
    }
    return $actions;
}
add_filter( 'post_row_actions', 'khabaryar_add_fetch_now_link', 10, 2 );

/**
 * Handle the "Fetch Now" action.
 */
function khabaryar_handle_fetch_now() {
    if ( isset( $_GET['action'], $_GET['post_id'], $_GET['_wpnonce'] ) && $_GET['action'] === 'khabaryar_fetch_single' ) {
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'khabaryar_fetch_single_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'khabaryar' ) );
        }
        $post_id = intval( $_GET['post_id'] );
        khabaryar_process_single_feed( $post_id );
        wp_redirect( admin_url( 'edit.php?post_type=khabaryar_feed&khabaryar_fetched=' . $post_id ) );
        exit;
    }
}
add_action( 'admin_init', 'khabaryar_handle_fetch_now' );

/**
 * Display a notice after fetching.
 */
function khabaryar_fetch_notice() {
    if ( isset( $_GET['khabaryar_fetched'] ) ) {
        $post_id = intval( $_GET['khabaryar_fetched'] );
        $post = get_post( $post_id );
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Successfully fetched feed: %s', 'khabaryar' ), esc_html( $post->post_title ) ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'khabaryar_fetch_notice' );
