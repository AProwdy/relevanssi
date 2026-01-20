<?php
/**
 * /lib/pinning.php
 *
 * @package Relevanssi
 * @author  AP Development Team
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 */

/**
 * Add pinning meta box to product edit screen
 */
function relevanssi_add_pinning_meta_box() {
    // Get indexed post types from Relevanssi settings
    $post_types = get_option('relevanssi_index_post_types', array('post', 'page'));

    // Always add 'product' post type for WooCommerce
    if (!in_array('product', $post_types)) {
        $post_types[] = 'product';
    }

    foreach ($post_types as $post_type) {
        add_meta_box(
            'relevanssi_pinning',
            __('Relevanssi Search Pinning', 'relevanssi'),
            'relevanssi_pinning_meta_box_content',
            $post_type,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'relevanssi_add_pinning_meta_box');

/**
 * Display pinning meta box content
 */
function relevanssi_pinning_meta_box_content($post) {
    wp_nonce_field('relevanssi_pinning_nonce', 'relevanssi_pinning_nonce');

    $pinned_keywords = get_post_meta($post->ID, '_relevanssi_pin', true);
    $pin_position = get_post_meta($post->ID, '_relevanssi_pin_position', true);

    if (!$pin_position) {
        $pin_position = 1;
    }

    ?>
    <p>
        <label for="relevanssi_pin_keywords">
            <strong><?php _e('Pin for keywords:', 'relevanssi'); ?></strong>
        </label>
        <input type="text"
               id="relevanssi_pin_keywords"
               name="relevanssi_pin_keywords"
               value="<?php echo esc_attr($pinned_keywords); ?>"
               class="widefat"
               placeholder="<?php _e('e.g., leddy 60, aquael tank', 'relevanssi'); ?>" />
        <span class="description">
            <?php _e('Enter keywords separated by commas. This product will appear first when these keywords are searched.', 'relevanssi'); ?>
        </span>
    </p>

    <p>
        <label for="relevanssi_pin_position">
            <strong><?php _e('Pin position:', 'relevanssi'); ?></strong>
        </label>
        <input type="number"
               id="relevanssi_pin_position"
               name="relevanssi_pin_position"
               value="<?php echo esc_attr($pin_position); ?>"
               min="1"
               max="100"
               class="small-text" />
        <span class="description">
            <?php _e('Position in search results (1 = first)', 'relevanssi'); ?>
        </span>
    </p>
    <?php
}

/**
 * Save pinning meta data
 */
function relevanssi_save_pinning_meta($post_id) {
    // Check nonce
    if (!isset($_POST['relevanssi_pinning_nonce']) ||
        !wp_verify_nonce($_POST['relevanssi_pinning_nonce'], 'relevanssi_pinning_nonce')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save pinned keywords
    if (isset($_POST['relevanssi_pin_keywords'])) {
        $keywords = sanitize_text_field($_POST['relevanssi_pin_keywords']);
        if (!empty($keywords)) {
            update_post_meta($post_id, '_relevanssi_pin', $keywords);
        } else {
            delete_post_meta($post_id, '_relevanssi_pin');
        }
    }

    // Save pin position
    if (isset($_POST['relevanssi_pin_position'])) {
        $position = absint($_POST['relevanssi_pin_position']);
        if ($position > 0) {
            update_post_meta($post_id, '_relevanssi_pin_position', $position);
        }
    }
}
add_action('save_post', 'relevanssi_save_pinning_meta');

/**
 * Apply pinning to search results
 * This filter runs after relevanssi_search() and modifies the results
 *
 * @param array $filter_data Array where index 0 contains the hits array
 * @param WP_Query $query The WP_Query object
 * @return array The modified filter_data array
 */
function relevanssi_apply_pinning($filter_data, $query) {
    // Extract hits from filter_data structure
    if (empty($filter_data) || !is_array($filter_data) || !isset($filter_data[0])) {
        return $filter_data;
    }

    $hits = $filter_data[0];

    if (empty($hits) || !is_array($hits)) {
        return $filter_data;
    }

    // Get the search query
    $search_query = '';
    if (isset($query->query_vars['s'])) {
        $search_query = strtolower(trim($query->query_vars['s']));
    }

    if (empty($search_query)) {
        return $filter_data;
    }

    // Find pinned posts for this search query
    global $wpdb;

    // Search for posts that have pinning keywords matching the search query
    $pinned_posts = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id, meta_value as keywords,
                (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = pm.post_id AND meta_key = '_relevanssi_pin_position' LIMIT 1) as position
         FROM {$wpdb->postmeta} pm
         WHERE meta_key = '_relevanssi_pin'
         AND meta_value LIKE %s",
        '%' . $wpdb->esc_like($search_query) . '%'
    ));

    if (empty($pinned_posts)) {
        return $filter_data;
    }

    // Separate pinned and non-pinned results
    $pinned_results = array();
    $regular_results = array();

    foreach ($hits as $hit) {
        $post_id = is_object($hit) ? $hit->ID : $hit;
        $is_pinned = false;

        foreach ($pinned_posts as $pinned_post) {
            if ($pinned_post->post_id == $post_id) {
                // Check if any of the pinned keywords match the search query
                // Support both exact matches and partial matches
                $keywords = array_map('trim', explode(',', strtolower($pinned_post->keywords)));

                foreach ($keywords as $keyword) {
                    if (empty($keyword)) continue;

                    // Check if search query contains keyword OR keyword contains search query
                    // This allows "leddy 60" to match searches for "leddy" or "leddy 60"
                    if (strpos($search_query, $keyword) !== false || strpos($keyword, $search_query) !== false) {
                        $position = !empty($pinned_post->position) ? intval($pinned_post->position) : 1;
                        $pinned_results[$position] = $hit;
                        $is_pinned = true;
                        break 2;
                    }
                }
            }
        }

        if (!$is_pinned) {
            $regular_results[] = $hit;
        }
    }

    // Sort pinned results by position
    ksort($pinned_results);

    // Merge pinned results at the top, followed by regular results
    $final_results = array_merge(array_values($pinned_results), $regular_results);

    // Return in the same structure as input
    $filter_data[0] = $final_results;
    return $filter_data;
}
add_filter('relevanssi_hits_filter', 'relevanssi_apply_pinning', 10, 2);

/**
 * Admin search pinning buttons (for admin search results)
 */
function relevanssi_admin_search_pinning($post, $query) {
    $pinned_keywords = get_post_meta($post->ID, '_relevanssi_pin', true);
    $pin_position = get_post_meta($post->ID, '_relevanssi_pin_position', true);

    $pinning_buttons = '';
    $pinned = '';

    if (!empty($pinned_keywords)) {
        $pinned = sprintf(
            '<span style="color: #d63638; font-weight: bold;">📌 Pinned for: %s (Position: %d)</span>',
            esc_html($pinned_keywords),
            !empty($pin_position) ? intval($pin_position) : 1
        );
    }

    return array($pinning_buttons, $pinned);
}
