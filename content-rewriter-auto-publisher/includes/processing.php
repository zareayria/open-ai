<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

add_action( 'admin_post_crwp_process_urls', 'crwp_handle_url_queue_submission' );
function crwp_handle_url_queue_submission() {
    if ( ! isset( $_POST['crwp_process_nonce'] ) || ! wp_verify_nonce( $_POST['crwp_process_nonce'], 'crwp_process_urls_action' ) ) {
        wp_die( 'Security check failed.' );
    }

    if ( empty( $_POST['crwp_urls_to_queue'] ) ) {
        wp_redirect( admin_url( 'admin.php?page=content-rewriter-auto-publisher&message=empty_urls' ) );
        exit;
    }

    $urls = array_filter( array_map( 'trim', explode( "\n", trim( $_POST['crwp_urls_to_queue'] ) ) ) );
    $valid_urls = [];
    foreach ( $urls as $url ) {
        if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
            $valid_urls[] = $url;
        }
    }

    if ( empty( $valid_urls ) ) {
        wp_redirect( admin_url( 'admin.php?page=content-rewriter-auto-publisher&message=no_valid_urls' ) );
        exit;
    }

    $queue = get_option( 'crwp_url_queue', [] );
    $new_queue = array_unique( array_merge( $queue, $valid_urls ) );
    update_option( 'crwp_url_queue', $new_queue );

    $count = count( $new_queue ) - count( $queue );
    wp_redirect( admin_url( 'admin.php?page=content-rewriter-auto-publisher&message=urls_added&count=' . $count ) );
    exit;
}

function crwp_process_queue() {
    $options = get_option( 'crwp_options' );
    $queue = get_option( 'crwp_url_queue', [] );
    if ( empty( $queue ) ) {
        return;
    }

    $posts_per_run = $options['posts_per_run'] ?? 1;
    $urls_to_process = array_slice( $queue, 0, $posts_per_run );

    foreach ( $urls_to_process as $url ) {
        $response = wp_remote_get( $url, [ 'timeout' => 20, 'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ] );
        if ( is_wp_error( $response ) ) {
            crwp_add_log( $url, 'error', 'Fetch failed: ' . $response->get_error_message() );
            continue;
        }

        $body = wp_remote_retrieve_body( $response );
        $extracted_data = crwp_extract_content_from_html( $url, $body );
        if ( ! empty( $extracted_data['error'] ) ) {
            crwp_add_log( $url, 'error', 'Extraction failed: ' . $extracted_data['error'] );
            continue;
        }

        // Content Length Check
        $word_count = str_word_count( strip_tags( $extracted_data['content'] ) );
        $min_length = $options['min_content_length'] ?? 0;
        $max_length = $options['max_content_length'] ?? 0;
        if ( $min_length > 0 && $word_count < $min_length ) {
            crwp_add_log( $url, 'error', sprintf( 'Content too short (%d words). Min required: %d.', $word_count, $min_length ) );
            continue;
        }
        if ( $max_length > 0 && $word_count > $max_length ) {
            crwp_add_log( $url, 'error', sprintf( 'Content too long (%d words). Max allowed: %d.', $word_count, $max_length ) );
            continue;
        }

        $rewritten_data = crwp_rewrite_content( $extracted_data, $options );
        if ( ! empty( $rewritten_data['error'] ) ) {
            crwp_add_log( $url, 'error', 'Rewriting failed: ' . $rewritten_data['error'] );
            continue;
        }

        if ( empty( $options['dry_run_mode'] ) ) {
            $post_id = crwp_create_post( $rewritten_data, $options );
            if ( is_wp_error( $post_id ) ) {
                crwp_add_log( $url, 'error', 'Publishing failed: ' . $post_id->get_error_message() );
            } else {
                crwp_add_log( $url, 'success', 'Post created successfully. ID: ' . $post_id );
            }
        } else {
            crwp_add_log( $url, 'success', '[DRY RUN] Content rewritten successfully.' );
        }
    }

    $remaining_urls = array_slice( $queue, count( $urls_to_process ) );
    update_option( 'crwp_url_queue', $remaining_urls );
}

function crwp_rewrite_content( $data, $options ) {
    $api_key = $options['api_key'] ?? '';
    if ( empty( $api_key ) ) {
        $data['error'] = __( 'API Key is not set.', 'content-rewriter-auto-publisher' );
        return $data;
    }

    $prompt_template = $options['prompt_template'] ?? 'Rewrite this: [CONTENT]';
    $final_prompt = str_replace( '[CONTENT]', $data['content'], $prompt_template );

    $intensity_prompt = '';
    switch( $options['rewriting_intensity'] ?? 'medium' ) {
        case 'low': $intensity_prompt = ' Make only light changes.'; break;
        case 'high': $intensity_prompt = ' Rewrite this extensively.'; break;
    }
    $final_prompt .= $intensity_prompt;

    if ( ! empty( $options['focus_keyword'] ) ) {
        $final_prompt .= ' Incorporate the keyword "' . $options['focus_keyword'] . '".';
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Content-Type'  => 'application/json' ],
        'body'    => json_encode([ 'model' => 'gpt-3.5-turbo', 'messages' => [ [ 'role' => 'user', 'content' => $final_prompt ] ] ]),
        'timeout' => 60,
    ] );

    if ( is_wp_error( $response ) ) {
        $data['error'] = 'API Error: ' . $response->get_error_message();
        return $data;
    }

    $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( isset( $response_body['choices'][0]['message']['content'] ) ) {
        $data['rewritten_content'] = trim( $response_body['choices'][0]['message']['content'] );
    } else {
        $data['error'] = __( 'Could not get rewritten content from API.', 'content-rewriter-auto-publisher' );
    }
    return $data;
}

function crwp_create_post( $data, $options ) {
    $post_content = $data['rewritten_content'];

    if ( ! empty( $options['add_source_attribution'] ) ) {
        $post_content .= "\n\n<p><small>" . sprintf( __( 'Source: %s', 'content-rewriter-auto-publisher' ), '<a href="' . esc_url( $data['url'] ) . '">' . esc_url( $data['url'] ) . '</a>' ) . "</small></p>";
    }

    $post_data = [
        'post_title'   => sanitize_text_field( $data['title'] ),
        'post_content' => wp_kses_post( $post_content ),
        'post_status'  => $options['post_status'] ?? 'draft',
        'post_author'  => get_current_user_id(),
        'post_category' => [ $options['default_category'] ?? 0 ],
        'tags_input'   => sanitize_text_field( $options['default_tags'] ?? '' ),
    ];

    $post_id = wp_insert_post( $post_data, true );
    if ( is_wp_error( $post_id ) ) return $post_id;

    $image_url = ! empty( $data['featured_image'] ) ? $data['featured_image'] : ( ! empty( $options['fallback_image'] ) ? $options['fallback_image'] : '' );
    if ( ! empty( $image_url ) ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $image_id = media_sideload_image( $image_url, $post_id, $data['title'], 'id' );
        if ( ! is_wp_error( $image_id ) ) set_post_thumbnail( $post_id, $image_id );
    }
    return $post_id;
}

function crwp_extract_content_from_html( $url, $html ) {
    $data = [ 'url' => $url, 'title' => '', 'content' => '', 'featured_image' => '', 'error' => '' ];
    if ( empty( $html ) ) {
        $data['error'] = __( 'HTML content is empty.', 'content-rewriter-auto-publisher' );
        return $data;
    }

    $doc = new DOMDocument();
    @$doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
    $xpath = new DOMXPath( $doc );

    $title_node = $xpath->query('//meta[@property="og:title"]/@content')->item(0);
    if ( $title_node ) $data['title'] = $title_node->nodeValue;
    else {
        $h1_node = $doc->getElementsByTagName('h1')->item(0);
        if ( $h1_node ) $data['title'] = $h1_node->nodeValue;
        else {
            $title_tag_node = $doc->getElementsByTagName('title')->item(0);
            if ( $title_tag_node ) $data['title'] = $title_tag_node->nodeValue;
        }
    }

    $image_node = $xpath->query('//meta[@property="og:image"]/@content')->item(0);
    if ( $image_node ) $data['featured_image'] = $image_node->nodeValue;

    foreach (['script', 'style', 'header', 'footer', 'nav', 'aside', 'form', '.advertisement', '.ad', '.comments'] as $selector) {
        $nodes = $xpath->query('//' . $selector . ' | //*[contains(@class, "' . str_replace('.', '', $selector) . '")] | //*[contains(@id, "' . str_replace('#', '', $selector) . '")]');
        foreach ($nodes as $node) {
            if ($node && $node->parentNode) $node->parentNode->removeChild($node);
        }
    }

    $content_node = null;
    $selectors = ['article', '#content', '#main', '.post-content', '.entry-content', '#post-body'];
    foreach($selectors as $selector) {
        $query_selector = str_replace(['.', '#'], '', $selector);
        $query = '//*[contains(@id, "' . $query_selector . '")] | //*[contains(@class, "' . $query_selector . '")]';
        if($selector === 'article') $query = '//article';
        $nodes = $xpath->query($query);
        if($nodes->length > 0) {
            $content_node = $nodes->item(0);
            break;
        }
    }

    if($content_node) {
        $data['content'] = $doc->saveHTML($content_node);
    } else {
        $body_node = $doc->getElementsByTagName('body')->item(0);
        if($body_node) $data['content'] = $doc->saveHTML($body_node);
        else $data['error'] = __( 'Could not find the main content.', 'content-rewriter-auto-publisher' );
    }

    $data['content'] = strip_tags($data['content'], '<p><a><h1><h2><h3><h4><h5><h6><img><ul><ol><li><blockquote><b><strong><i><em>');
    $data['content'] = preg_replace('/\s+/', ' ', $data['content']);

    if ( empty( $data['title'] ) || strlen(trim(strip_tags($data['content']))) < 50 ) {
        $data['error'] = __( 'Could not extract sufficient title or content.', 'content-rewriter-auto-publisher' );
    }

    return $data;
}
