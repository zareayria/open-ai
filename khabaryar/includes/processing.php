<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Processes a single feed source and creates draft posts for new items.
 */
function khabaryar_process_single_feed( $source_id ) {
    $source = get_post( $source_id );

    if ( ! $source || 'khabaryar_feed' !== $source->post_type ) {
        return;
    }

    $feed_url = get_post_meta( $source_id, '_khabaryar_feed_url', true );
    $post_category = get_post_meta( $source_id, '_khabaryar_post_category', true );
    $post_author = get_post_meta( $source_id, '_khabaryar_post_author', true );
    $keywords = get_post_meta( $source_id, '_khabaryar_keywords', true );
    $skip_no_image = get_post_meta( $source_id, '_khabaryar_skip_no_image', true );

    if ( ! filter_var( $feed_url, FILTER_VALIDATE_URL ) ) {
        return;
    }

    $feed = khabaryar_fetch_rss_feed( $feed_url );

    if ( is_wp_error( $feed ) ) {
        return;
    }

    foreach ( $feed->get_items() as $item ) {
        $item_permalink = esc_url( $item->get_permalink() );

        if ( get_posts( ['post_type' => 'post', 'meta_key' => '_khabaryar_original_url', 'meta_value' => $item_permalink, 'posts_per_page' => 1] ) ) {
            continue;
        }

        $original_title = sanitize_text_field( $item->get_title() );
        $original_content = wp_kses_post( $item->get_content() );

        $image_url = khabaryar_extract_first_image_url( $original_content, $item );
        if ( $skip_no_image && ! $image_url ) {
            continue;
        }

        if ( ! empty( $keywords ) ) {
            $found_keyword = false;
            $exclude_keyword = false;
            $keyword_list = array_map( 'trim', explode( ',', $keywords ) );

            foreach ( $keyword_list as $keyword ) {
                if ( strpos( $keyword, '-' ) === 0 ) {
                    $exclude_term = substr( $keyword, 1 );
                    if ( stripos( $original_title, $exclude_term ) !== false || stripos( $original_content, $exclude_term ) !== false ) {
                        $exclude_keyword = true;
                        break;
                    }
                } else {
                    if ( stripos( $original_title, $keyword ) !== false || stripos( $original_content, $keyword ) !== false ) {
                        $found_keyword = true;
                    }
                }
            }

            $has_include_keywords = count( array_filter( $keyword_list, function($k) { return strpos($k, '-') !== 0; } ) ) > 0;
            if ( $exclude_keyword || ( $has_include_keywords && ! $found_keyword ) ) {
                continue;
            }
        }

        $processed_data = khabaryar_process_with_ai( $original_title, $original_content );

        $post_data = array(
            'post_title'   => $processed_data['title'],
            'post_content' => $processed_data['content'],
            'post_status'  => 'draft',
            'post_author'  => $post_author ? intval( $post_author ) : $source->post_author,
        );

        if ( ! empty( $post_category ) ) {
            $post_data['post_category'] = array( intval( $post_category ) );
        }

        $new_post_id = wp_insert_post( $post_data );

        if ( $new_post_id && ! is_wp_error( $new_post_id ) ) {
            add_post_meta( $new_post_id, '_khabaryar_original_url', $item_permalink, true );
            if ( $image_url ) {
                khabaryar_set_featured_image_from_url( $new_post_id, $image_url );
            }
        }
    }
}

/**
 * Fetches and parses an RSS feed.
 */
function khabaryar_fetch_rss_feed( $feed_url ) {
    if ( ! function_exists( 'fetch_feed' ) ) {
        include_once ABSPATH . WPINC . '/feed.php';
    }
    $feed = fetch_feed( $feed_url );
    if ( is_wp_error( $feed ) ) {
        return $feed;
    }
    if ( ! $feed->get_item_quantity() ) {
        $feed->init();
        return new WP_Error( 'feed_error', __( 'An error has occurred with the feed.', 'khabaryar' ) );
    }
    return $feed;
}

/**
 * Processes a given title and content using an AI API.
 */
function khabaryar_process_with_ai( $title, $content ) {
    $options = get_option( 'khabaryar_options' );

    if ( ! isset( $options['enable_ai'] ) || empty( $options['api_key'] ) ) {
        return array( 'title' => $title, 'content' => $content );
    }

    $api_key = $options['api_key'];
    $source_replacement = isset( $options['source_replacement'] ) ? $options['source_replacement'] : get_bloginfo( 'name' );

    $api_url = 'https://api.openai.com/v1/chat/completions';
    $prompt = sprintf(
        "Rewrite the title to be more engaging. Rewrite the content in a formal tone. Summarize the content. Replace any mention of the original source with '%s'. Return the result strictly in JSON format: {\"new_title\": \"...\", \"new_content\": \"...\"}\n\nOriginal Title: %s\nOriginal Content: %s",
        esc_html( $source_replacement ), $title, strip_tags( $content )
    );

    $ai_model = isset( $options['ai_model'] ) ? $options['ai_model'] : 'gpt-3.5-turbo';

    $body = array(
        'model'    => $ai_model,
        'messages' => array( ['role' => 'user', 'content' => $prompt] ),
        'temperature' => 0.7,
    );

    $response = wp_remote_post( $api_url, [
        'method'  => 'POST',
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
        'body'    => json_encode( $body ),
        'timeout' => 60,
    ] );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return array( 'title' => $title, 'content' => $content );
    }

    $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
    $ai_response_text = $response_body['choices'][0]['message']['content'] ?? '';
    $processed_data = json_decode( $ai_response_text, true );

    if ( json_last_error() === JSON_ERROR_NONE && isset( $processed_data['new_title'] ) && isset( $processed_data['new_content'] ) ) {
        return array(
            'title'   => sanitize_text_field( $processed_data['new_title'] ),
            'content' => wp_kses_post( $processed_data['new_content'] ),
        );
    }

    return array( 'title' => $title, 'content' => $content );
}

/**
 * Extracts the first image URL from post content.
 */
function khabaryar_extract_first_image_url( $content, $item ) {
    if ( preg_match( '/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $content, $matches ) ) {
        return $matches[1];
    }
    $enclosure = $item->get_enclosure();
    if ( $enclosure && strpos( $enclosure->get_type(), 'image' ) === 0 ) {
        return $enclosure->get_link();
    }
    if ( $thumbnail = $item->get_item_tags( SIMPLEPIE_NAMESPACE_MEDIARSS, 'thumbnail' ) ) {
        if ( isset( $thumbnail[0]['attribs']['']['url'] ) ) {
            return $thumbnail[0]['attribs']['']['url'];
        }
    }
    return false;
}

/**
 * Downloads an image from a URL and sets it as the post thumbnail.
 */
function khabaryar_set_featured_image_from_url( $post_id, $image_url ) {
    if ( ! function_exists( 'media_sideload_image' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    $attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );
    if ( is_wp_error( $attachment_id ) ) {
        return $attachment_id;
    }
    set_post_thumbnail( $post_id, $attachment_id );
    return $attachment_id;
}
