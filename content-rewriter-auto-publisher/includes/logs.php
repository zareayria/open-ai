<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

add_action( 'admin_notices', 'crwp_show_admin_notices' );
function crwp_show_admin_notices() {
    if ( ! isset( $_GET['page'] ) || 'content-rewriter-auto-publisher' !== $_GET['page'] ) {
        return;
    }

    if ( isset( $_GET['message'] ) ) {
        $message = '';
        $type = 'success';
        switch ( $_GET['message'] ) {
            case 'urls_added':
                $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
                $message = sprintf( _n( '%d URL was added to the queue.', '%d URLs were added to the queue.', $count, 'content-rewriter-auto-publisher' ), $count );
                break;
            case 'empty_urls':
                $message = __( 'The URL list was empty.', 'content-rewriter-auto-publisher' );
                $type = 'error';
                break;
            case 'no_valid_urls':
                $message = __( 'No valid URLs were found in the list.', 'content-rewriter-auto-publisher' );
                $type = 'error';
                break;
        }

        if ( $message ) {
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
    }
}


/**
 * Renders the logs page.
 */
function crwp_render_logs_page() {
    ?>
    <div class="wrap">
        <h1><?php _e( 'Processing Logs', 'content-rewriter-auto-publisher' ); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e( 'Timestamp', 'content-rewriter-auto-publisher' ); ?></th>
                    <th scope="col"><?php _e( 'URL', 'content-rewriter-auto-publisher' ); ?></th>
                    <th scope="col"><?php _e( 'Status', 'content-rewriter-auto-publisher' ); ?></th>
                    <th scope="col"><?php _e( 'Message', 'content-rewriter-auto-publisher' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = get_option( 'crwp_logs', [] );
                if ( ! empty( $logs ) ) :
                    foreach ( array_reverse( $logs ) as $log ) :
                        ?>
                        <tr>
                            <td><?php echo esc_html( $log['timestamp'] ); ?></td>
                            <td><?php echo esc_url( $log['url'] ); ?></td>
                            <td>
                                <?php if ( 'success' === $log['status'] ) : ?>
                                    <span style="color: green;"><?php _e( 'Success', 'content-rewriter-auto-publisher' ); ?></span>
                                <?php else : ?>
                                    <span style="color: red;"><?php _e( 'Error', 'content-rewriter-auto-publisher' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $log['message'] ); ?></td>
                        </tr>
                        <?php
                    endforeach;
                else :
                    ?>
                    <tr>
                        <td colspan="4"><?php _e( 'No logs found.', 'content-rewriter-auto-publisher' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Adds a log entry.
 */
function crwp_add_log( $url, $status, $message ) {
    $logs = get_option( 'crwp_logs', [] );

    $logs[] = [
        'timestamp' => current_time( 'mysql' ),
        'url'       => $url,
        'status'    => $status,
        'message'   => $message,
    ];

    // Keep the log size manageable
    if ( count( $logs ) > 100 ) {
        $logs = array_slice( $logs, -100 );
    }

    update_option( 'crwp_logs', $logs );
}
