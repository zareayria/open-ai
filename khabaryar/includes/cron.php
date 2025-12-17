<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Adds custom cron schedules.
 */
function khabaryar_add_cron_schedule( $schedules ) {
    $schedules['six_hours'] = array(
        'interval' => 21600,
        'display'  => __( 'Every 6 Hours', 'khabaryar' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'khabaryar_add_cron_schedule' );

add_action( 'khabaryar_process_single_feed_hook', 'khabaryar_process_single_feed' );
