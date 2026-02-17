<?php
/**
 * /lib/compatibility/woocommerce.php
 *
 * WooCommerce compatibility features.
 *
 * @package Relevanssi
 * @author  Mikko Saari
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

add_filter( 'relevanssi_indexing_restriction', 'relevanssi_woocommerce_restriction' );
add_filter( 'relevanssi_admin_search_blocked_post_types', 'relevanssi_woocommerce_admin_search_blocked_post_types' );
add_filter( 'relevanssi_modify_wp_query', 'relevanssi_woocommerce_filters' );
add_filter( 'relevanssi_post_ok', 'relevanssi_variation_post_ok', 10, 2 );
add_filter( 'relevanssi_hits_filter', 'relevanssi_woocommerce_prioritize_in_stock_hits', 11, 2 );

/**
 * This action solves the problems introduced by adjust_posts_count() in
 * WooCommerce version 4.4.0.
 */
add_action( 'woocommerce_before_shop_loop', 'relevanssi_wc_reset_loop' );

RELEVANSSI_PREMIUM && add_filter( 'relevanssi_match', 'relevanssi_sku_boost' );

/**
 * Resets the WC post loop in search queries.
 *
 * Hooks on to woocommerce_before_shop_loop.
 */
function relevanssi_wc_reset_loop() {
	global $wp_query;
	if ( $wp_query->is_search ) {
		wc_reset_loop();
	}
}
/**
 * Applies the WooCommerce product visibility filter.
 *
 * @param array $restriction An array with two values: 'mysql' for the MySQL
 * query restriction to modify, 'reason' for the reason of restriction.
 */
function relevanssi_woocommerce_restriction( $restriction ) {
	// Backwards compatibility code for 2.8.0, remove at some point.
	if ( is_string( $restriction ) ) {
		$restriction = array(
			'mysql'  => $restriction,
			'reason' => '',
		);
	}

	$restriction['mysql']  .= relevanssi_woocommerce_indexing_filter();
	$restriction['reason'] .= 'WooCommerce';
	return $restriction;
}

/**
 * WooCommerce product visibility filtering for indexing.
 *
 * This filter is applied before the posts are selected for indexing, so this will
 * skip all the excluded posts right away.
 *
 * @since 4.0.9 (2.1.5)
 * @global $wpdb The WordPress database interface.
 *
 * @return string $restriction The query restriction for the WooCommerce filtering.
 */
function relevanssi_woocommerce_indexing_filter() {
	global $wpdb;

	$restriction        = '';
	$woocommerce_blocks = array(
		'outofstock'           => false,
		'exclude-from-catalog' => false,
		'exclude-from-search'  => true,
	);
	/**
	 * Controls the WooCommerce product visibility filtering.
	 *
	 * @param array $woocommerce_blocks Has three keys: 'outofstock',
	 * 'exclude-from-catalog' and 'exclude-from-search', matching three different
	 * product visibility settings. If the filter sets some of these to 'true',
	 * those posts will be filtered in the indexing.
	 */
	$woocommerce_blocks     = apply_filters( 'relevanssi_woocommerce_indexing', $woocommerce_blocks );
	$term_taxonomy_id_array = array();
	if ( $woocommerce_blocks['outofstock'] ) {
		$out_of_stock = get_term_by( 'slug', 'outofstock', 'product_visibility', OBJECT );
		if ( $out_of_stock && isset( $out_of_stock->term_taxonomy_id ) ) {
			$term_taxonomy_id_array[] = $out_of_stock->term_taxonomy_id;
		}
	}
	if ( $woocommerce_blocks['exclude-from-catalog'] ) {
		$exclude_from_catalog = get_term_by( 'slug', 'exclude-from-catalog', 'product_visibility', OBJECT );
		if ( $exclude_from_catalog && isset( $exclude_from_catalog->term_taxonomy_id ) ) {
			$term_taxonomy_id_array[] = $exclude_from_catalog->term_taxonomy_id;
		}
	}
	if ( $woocommerce_blocks['exclude-from-search'] ) {
		$exclude_from_search = get_term_by( 'slug', 'exclude-from-search', 'product_visibility', OBJECT );
		if ( $exclude_from_search && isset( $exclude_from_search->term_taxonomy_id ) ) {
			$term_taxonomy_id_array[] = $exclude_from_search->term_taxonomy_id;
		}
	}
	if ( ! empty( $term_taxonomy_id_array ) ) {
		$term_taxonomy_id_string = implode( ',', $term_taxonomy_id_array );
		$restriction            .= " AND post.ID NOT IN (SELECT object_id FROM $wpdb->term_relationships WHERE object_id = post.ID AND term_taxonomy_id IN ($term_taxonomy_id_string)) ";
	}
	return $restriction;
}

/**
 * SKU weight boost.
 *
 * Increases the weight for matches in the _sku custom field. The amount of
 * boost can be adjusted with the `relevanssi_sku_boost` filter hook. The
 * default is 2.
 *
 * @param object $match_object The match object.
 *
 * @return object The match object.
 */
function relevanssi_sku_boost( $match_object ) {
	$custom_field_detail = json_decode( $match_object->customfield_detail );
	if ( null !== $custom_field_detail && isset( $custom_field_detail->_sku ) ) {
		/**
		 * Filters the SKU boost value.
		 *
		 * @param float The boost multiplier, default 2.
		 */
		$match_object->weight *= apply_filters( 'relevanssi_sku_boost', 2 );
	}
	return $match_object;
}

/**
 * Adds blocked WooCommerce post types to the list of blocked post types.
 *
 *  Stops Relevanssi from taking over the admin search for the WooCommerce
 * blocked post types using the relevanssi_admin_search_blocked_post_types
 * filter hook.
 *
 * @param array $post_types The list of blocked post types.
 * @return array
 */
function relevanssi_woocommerce_admin_search_blocked_post_types( array $post_types ): array {
	$woo_post_types = array(
		'shop_coupon',
		'shop_order',
		'shop_order_refund',
		'wc_order_status',
		'wc_order_email',
		'shop_webhook',
	);
	return array_merge( $post_types, $woo_post_types );
}

/**
 * Relevanssi support for WooCommerce filtering.
 *
 * @param WP_Query $query The WP_Query object.
 * @return WP_Query The WP_Query object.
 */
function relevanssi_woocommerce_filters( $query ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended

	$min_price = isset( $_REQUEST['min_price'] ) ? intval( $_REQUEST['min_price'] ) : false;
	$max_price = isset( $_REQUEST['max_price'] ) ? intval( $_REQUEST['max_price'] ) : false;

	$meta_query = $query->get( 'meta_query' );
	if ( $min_price ) {
		$meta_query[] = array(
			'key'     => '_price',
			'value'   => $min_price,
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);
	}
	if ( $max_price ) {
		$meta_query[] = array(
			'key'     => '_price',
			'value'   => $max_price,
			'compare' => '<=',
			'type'    => 'NUMERIC',
		);
	}
	if ( $meta_query ) {
		$query->set( 'meta_query', $meta_query );
	}

	foreach ( array( 'product_tag', 'product_cat', 'product_brand' ) as $taxonomy ) {
		$value = isset( $_REQUEST[ $taxonomy ] ) ? intval( $_REQUEST[ $taxonomy ] ) : false;
		if ( $value ) {
			$tax_query = $query->get( 'tax_query' );
			if ( ! is_array( $tax_query ) ) {
				$tax_query = array();
			}
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $value,
			);
			$query->set( 'tax_query', $tax_query );
		}
	}

	if ( 'no' === get_option( 'woocommerce_attribute_lookup_enabled' ) ) {
		return $query;
	}

	$chosen_attributes = array();

	if ( ! empty( $_GET ) ) {
		foreach ( $_GET as $key => $value ) {
			if ( 0 === strpos( $key, 'filter_' ) ) {
				$attribute    = wc_sanitize_taxonomy_name( str_replace( 'filter_', '', $key ) );
				$taxonomy     = wc_attribute_taxonomy_name( $attribute );
				$filter_terms = ! empty( $value ) ? explode( ',', wc_clean( wp_unslash( $value ) ) ) : array();

				if ( empty( $filter_terms ) || ! taxonomy_exists( $taxonomy ) || ! wc_attribute_taxonomy_id_by_name( $attribute ) ) {
					continue;
				}

				$query_type = ! empty( $_GET[ 'query_type_' . $attribute ] ) && in_array( $_GET[ 'query_type_' . $attribute ], array( 'and', 'or' ), true )
					? wc_clean( wp_unslash( $_GET[ 'query_type_' . $attribute ] ) )
					: '';

				$chosen_attributes[ $taxonomy ]['terms']      = array_map( 'sanitize_title', $filter_terms );
				$chosen_attributes[ $taxonomy ]['query_type'] = $query_type ? $query_type : apply_filters( 'woocommerce_layered_nav_default_query_type', 'and' );
			}
		}
	}

	$tax_query = $query->get( 'tax_query' );
	if ( ! is_array( $tax_query ) ) {
		$tax_query = array();
	}
	foreach ( $chosen_attributes as $taxonomy => $data ) {
		$tax_query[] = array(
			'taxonomy'         => $taxonomy,
			'field'            => 'slug',
			'terms'            => $data['terms'],
			'operator'         => 'and' === $data['query_type'] ? 'AND' : 'IN',
			'include_children' => false,
		);
	}
	$query->set( 'tax_query', $tax_query );

	return $query;
}

/**
 * Provides layered navigation term counts based on Relevanssi searches.
 *
 * Hooks onto woocommerce_get_filtered_term_product_counts_query to provide
 * accurate term counts.
 *
 * @param array $query The MySQL query parts.
 *
 * @return array The modified query.
 */
function relevanssi_filtered_term_product_counts_query( $query ) {
	if ( defined( 'BeRocket_AJAX_filters_version' ) ) {
		return $query;
	}

	global $relevanssi_variables, $wpdb;

	if ( false !== stripos( $query['select'], 'product_or_parent_id' ) ) {
		$query['from']  = str_replace( 'FROM ', "FROM {$relevanssi_variables['relevanssi_table']} AS relevanssi, ", $query['from'] );
		$query['where'] = str_replace( 'WHERE ', " WHERE relevanssi.doc = $wpdb->posts.ID AND ", $query['where'] );
		$query['where'] = preg_replace( '/\(\w+posts.post_title LIKE(.*?)\)\)/', 'relevanssi.term LIKE\1)', $query['where'] );
		$query['where'] = preg_replace( array( '/OR \(\w+posts.post_excerpt LIKE .*?\)/', '/OR \(\w+posts.post_content LIKE .*?\)/' ), '', $query['where'] );
	} else {
		$query['select'] = 'SELECT COUNT( DISTINCT( relevanssi.doc ) ) AS term_count, terms.term_id AS term_count_id';
		$query['from']   = "FROM {$relevanssi_variables['relevanssi_table']} AS relevanssi, $wpdb->posts";
		$query['where']  = str_replace( 'WHERE ', " WHERE relevanssi.doc = $wpdb->posts.ID AND ", $query['where'] );
		$query['where']  = preg_replace( '/\(\w+posts.post_title LIKE(.*?)\)\)/', 'relevanssi.term LIKE\1)', $query['where'] );
		$query['where']  = preg_replace( array( '/OR \(\w+posts.post_excerpt LIKE .*?\)/', '/OR \(\w+posts.post_content LIKE .*?\)/' ), '', $query['where'] );
	}

	return $query;
}

/**
 * Checks the parent product status for product variations.
 *
 * @param bool $ok      Whether the post is OK to return in search.
 * @param int  $post_id The post ID.
 *
 * @return bool
 */
function relevanssi_variation_post_ok( $ok, $post_id ) : bool {
	$post_type = relevanssi_get_post_type( $post_id );
	if ( 'product_variation' === $post_type ) {
		$parent = get_post_parent( $post_id );
		return apply_filters( 'relevanssi_post_ok', $ok, $parent->ID );
	}
	return $ok;
}

/**
 * Prioritizes in-stock WooCommerce products over out-of-stock products.
 *
 * Keeps the original relevance order inside each stock-status group, while
 * moving out-of-stock products after in-stock products.
 *
 * @param array    $filter_data The Relevanssi filter data array.
 * @param WP_Query $query       The current query object.
 *
 * @return array
 */
function relevanssi_woocommerce_prioritize_in_stock_hits( $filter_data, $query ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return $filter_data;
	}

	if ( ! $query instanceof WP_Query || ! $query->is_search() ) {
		return $filter_data;
	}

	$post_type = $query->get( 'post_type' );
	if ( is_array( $post_type ) ) {
		if ( ! in_array( 'product', $post_type, true ) && ! in_array( 'product_variation', $post_type, true ) ) {
			return $filter_data;
		}
	} elseif ( ! empty( $post_type ) && 'product' !== $post_type && 'product_variation' !== $post_type ) {
		return $filter_data;
	}

	$hits = isset( $filter_data[0] ) && is_array( $filter_data[0] ) ? $filter_data[0] : array();
	if ( count( $hits ) < 2 ) {
		return $filter_data;
	}

	$sorted_hits = array();
	foreach ( $hits as $index => $hit ) {
		$hit_id = 0;
		if ( is_object( $hit ) && isset( $hit->ID ) ) {
			$hit_id = (int) $hit->ID;
		} elseif ( is_numeric( $hit ) ) {
			$hit_id = (int) $hit;
		}

		$rank = 2; // Unknown/non-product: keep after product stock-status groups.
		if ( $hit_id > 0 ) {
			$hit_post_type = get_post_type( $hit_id );
			if ( 'product' === $hit_post_type || 'product_variation' === $hit_post_type ) {
				$stock_status = get_post_meta( $hit_id, '_stock_status', true );
				$rank         = ( 'outofstock' === $stock_status ) ? 1 : 0;
			}
		}

		$sorted_hits[] = array(
			'rank'  => $rank,
			'index' => $index,
			'hit'   => $hit,
		);
	}

	usort(
		$sorted_hits,
		function( $a, $b ) {
			if ( $a['rank'] === $b['rank'] ) {
				return $a['index'] <=> $b['index'];
			}
			return $a['rank'] <=> $b['rank'];
		}
	);

	$filter_data[0] = array_map(
		function( $item ) {
			return $item['hit'];
		},
		$sorted_hits
	);

	return $filter_data;
}

if ( 'on' === get_option( 'relevanssi_index_sku' ) ) {
	add_filter( 'relevanssi_index_custom_fields', 'relevanssi_woocommerce_add_sku' );
}

/**
 * Adds the _sku custom field to the indexed fields.
 *
 * @param array $fields The list of custom fields to index.
 *
 * @return array The list of custom fields with _sku added.
 */
function relevanssi_woocommerce_add_sku( $fields ) {
	if ( ! in_array( '_sku', $fields, true ) ) {
		$fields[] = '_sku';
	}
	return $fields;
}
