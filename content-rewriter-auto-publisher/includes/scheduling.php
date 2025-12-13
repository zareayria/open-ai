<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

add_filter( 'cron_schedules', 'crwp_add_cron_interval' );
function crwp_add_cron_interval( $schedules ) {
    $options = get_option( 'crwp_options' );
    $interval = $options['schedule_interval'] ?? 60;
    $schedules['crwp_custom_interval'] = [
        'interval' => $interval * 60,
        'display'  => sprintf( 'Every %d minutes', $interval ),
    ];
    return $schedules;
}

function crwp_activate() {
    if ( ! wp_next_scheduled( 'crwp_process_queue_event' ) ) {
        wp_schedule_event( time(), 'crwp_custom_interval', 'crwp_process_queue_event' );
    }
}

function crwp_deactivate() {
    wp_clear_scheduled_hook( 'crwp_process_queue_event' );
}

add_action( 'crwp_process_queue_event', 'crwp_process_queue' );

add_action( 'update_option_crwp_options', 'crwp_update_schedule_on_settings_save', 10, 2 );
function crwp_update_schedule_on_settings_save( $old_value, $new_value ) {
    $old_interval = $old_value['schedule_interval'] ?? 60;
    $new_interval = $new_value['schedule_interval'] ?? 60;

    if ( $old_interval !== $new_interval ) {
        // First, clear the existing hook
        wp_clear_scheduled_hook( 'crwp_process_queue_event' );

        // Then, schedule a new one with the updated interval
        wp_schedule_event( time(), 'crwp_custom_interval', 'crwp_process_queue_event' );
    }
}
