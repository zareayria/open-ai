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
        khabaryar_log( sprintf( "Processing failed: Invalid source ID %d.", $source_id ) );
        return;
    }

    khabaryar_log( sprintf( "Starting processing for feed: %s (%d)", $source->post_title, $source_id ) );

    $feed_url = get_post_meta( $source_id, '_khabaryar_feed_url', true );
    $post_category = get_post_meta( $source_id, '_khabaryar_post_category', true );
    $post_author = get_post_meta( $source_id, '_khabaryar_post_author', true );
    $keywords = get_post_meta( $source_id, '_khabaryar_keywords', true );
    $skip_no_image = get_post_meta( $source_id, '_khabaryar_skip_no_image', true );

    if ( ! filter_var( $feed_url, FILTER_VALIDATE_URL ) ) {
        return;
    }

    $feed_type = get_post_meta( $source_id, '_khabaryar_feed_type', true ) ?: 'rss';
    $feed = ( $feed_type === 'sitemap' ) ? khabaryar_fetch_from_sitemap( $feed_url ) : khabaryar_fetch_rss_feed( $feed_url );

    if ( is_wp_error( $feed ) ) {
        return;
    }

    $max_items = get_post_meta( $source_id, '_khabaryar_max_items', true ) ?: 5;
    $item_count = 0;

    foreach ( $feed->get_items( 0, $max_items ) as $item ) {
        if ( $item_count >= $max_items ) {
            break;
        }

        $item_permalink = esc_url( $item->get_permalink() );

        if ( get_posts( ['post_type' => 'post', 'meta_key' => '_khabaryar_original_url', 'meta_value' => $item_permalink, 'posts_per_page' => 1] ) ) {
            continue;
        }

        if ( $feed_type === 'sitemap' ) {
            $scraped_data = khabaryar_scrape_url_for_data( $item_permalink );
            $original_title = $scraped_data['title'];
            $original_content = $scraped_data['content'];
            if(empty($original_title)) continue;
        } else {
            $original_title = sanitize_text_field( $item->get_title() );
            $original_content = wp_kses_post( $item->get_content() );
        }

        // Translate if enabled
        $enable_translation = get_post_meta( $source_id, '_khabaryar_enable_translation', true );
        if ( $enable_translation ) {
            $original_title = khabaryar_translate_to_persian( $original_title );
            $original_content = khabaryar_translate_to_persian( strip_tags( $original_content ) );
        }

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
            khabaryar_log( sprintf( "Successfully created post %d ('%s') from feed %d.", $new_post_id, $processed_data['title'], $source_id ) );
        } else {
            khabaryar_log( sprintf( "Failed to create post from URL %s. Error: %s", $item_permalink, is_wp_error( $new_post_id ) ? $new_post_id->get_error_message() : 'Unknown' ) );
        }
        $item_count++;
    }
    khabaryar_log( sprintf( "Finished processing for feed %d. Imported %d new items.", $source_id, $item_count ) );
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
 * Main AI processing function that routes to the correct provider.
 */
function khabaryar_process_with_ai( $title, $content ) {
    $options = get_option( 'khabaryar_options' );

    if ( ! isset( $options['enable_ai'] ) || empty( $options['api_key'] ) ) {
        return array( 'title' => $title, 'content' => $content );
    }

    $provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';

    switch ( $provider ) {
        case 'gemini':
            return khabaryar_process_gemini( $title, $content, $options );
        case 'openai':
        default:
            return khabaryar_process_openai( $title, $content, $options );
    }
}

/**
 * Processes content using the OpenAI API.
 */
function khabaryar_process_openai( $title, $content, $options ) {
    $api_key = $options['api_key'];
    $source_replacement = $options['source_replacement'] ?? get_bloginfo( 'name' );
    $ai_model = $options['ai_model'] ?? 'gpt-3.5-turbo';
    $api_url = 'https://api.openai.com/v1/chat/completions';

    if ( ($options['ai_provider'] ?? 'openai') === 'custom' && ! empty( $options['custom_api_url'] ) ) {
        $api_url = $options['custom_api_url'];
    }

    $prompt = sprintf(
        "Rewrite the title to be more engaging. Rewrite the content in a formal tone. Summarize it. Replace any mention of the original source with '%s'. Return ONLY a valid JSON object in the format: {\"new_title\": \"...\", \"new_content\": \"...\"}\n\nOriginal Title: %s\nOriginal Content: %s",
        esc_html( $source_replacement ), $title, strip_tags( $content )
    );

    $body = [
        'model'    => $ai_model,
        'messages' => [ ['role' => 'user', 'content' => $prompt] ],
        'temperature' => 0.7,
        'response_format' => [ 'type' => 'json_object' ],
    ];

    $response = wp_remote_post( $api_url, [
        'method'  => 'POST',
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
        'body'    => json_encode( $body ), 'timeout' => 60,
    ] );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return [ 'title' => $title, 'content' => $content ];
    }

    $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
    $processed_data = json_decode( $response_body['choices'][0]['message']['content'] ?? '', true );

    if ( json_last_error() === JSON_ERROR_NONE && isset( $processed_data['new_title'] ) ) {
        return [
            'title'   => sanitize_text_field( $processed_data['new_title'] ),
            'content' => wp_kses_post( $processed_data['new_content'] ?? '' ),
        ];
    }
    return [ 'title' => $title, 'content' => $content ];
}

/**
 * Placeholder for processing content using the Google Gemini API.
 */
function khabaryar_process_gemini( $title, $content, $options ) {
    // This is a placeholder. Actual implementation would require a different API endpoint,
    // request structure, and response parsing.
    return [ 'title' => "[Gemini] " . $title, 'content' => $content ];
}

/**
 * Fetches URLs from an XML sitemap and returns them as a SimplePie-like object.
 */
function khabaryar_fetch_from_sitemap( $sitemap_url ) {
    $response = wp_remote_get( $sitemap_url );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return new WP_Error( 'sitemap_error', __( 'Could not fetch sitemap.', 'khabaryar' ) );
    }

    $xml = simplexml_load_string( wp_remote_retrieve_body( $response ) );
    if ( ! $xml ) {
        return new WP_Error( 'sitemap_error', __( 'Could not parse sitemap XML.', 'khabaryar' ) );
    }

    $items = [];
    foreach ( $xml->url as $url_node ) {
        $items[] = (object) [
            'get_permalink' => (string) $url_node->loc,
            'get_title' => '',
            'get_content' => '',
        ];
    }

    // Create a mock object that behaves like SimplePie
    return (object) [
        'get_items' => $items,
        'is_error' => false,
        'get_item_quantity' => count($items),
    ];
}

/**
 * Scrapes a URL to extract title and content from Open Graph meta tags.
 */
function khabaryar_scrape_url_for_data( $url ) {
    $response = wp_remote_get( $url );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return [ 'title' => '', 'content' => '' ];
    }

    $html = wp_remote_retrieve_body( $response );
    $doc = new DOMDocument();
    @$doc->loadHTML( $html );
    $metas = $doc->getElementsByTagName( 'meta' );

    $data = [ 'title' => '', 'content' => '' ];

    foreach ( $metas as $meta ) {
        $property = $meta->getAttribute( 'property' );
        if ( $property == 'og:title' ) {
            $data['title'] = $meta->getAttribute( 'content' );
        }
        if ( $property == 'og:description' ) {
            $data['content'] = $meta->getAttribute( 'content' );
        }
    }

    // Fallback if OG tags are not found
    if ( empty( $data['title'] ) ) {
        $titles = $doc->getElementsByTagName( 'title' );
        if ( $titles->length > 0 ) {
            $data['title'] = $titles->item(0)->nodeValue;
        }
    }

    // Try to find main content if description is short
    if ( empty( $data['content'] ) || strlen( $data['content'] ) < 200 ) {
        $xpath = new DOMXPath($doc);
        $article = $xpath->query('//article')->item(0);
        if ($article) {
            $data['content'] = $article->nodeValue;
        } else {
            // Fallback to body if no article tag
            $body = $doc->getElementsByTagName('body')->item(0);
            if ($body) {
                $data['content'] = $body->nodeValue;
            }
        }
    }

    return $data;
}

/**
 * Translates text to Persian using a public API.
 */
function khabaryar_translate_to_persian( $text ) {
    if ( empty( $text ) ) {
        return $text;
    }

    $api_url = 'https://api.mymemory.translated.net/get?q=' . urlencode( $text ) . '&langpair=en|fa';

    $response = wp_remote_get( $api_url );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $text; // Return original text on error
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $body['responseData']['translatedText'] ) ) {
        return $body['responseData']['translatedText'];
    }

    return $text;
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
