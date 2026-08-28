<?php
/**
 * /lib/compatibility/rankmath.php
 *
 * Rank Math noindex filtering function.
 *
 * @package Relevanssi
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

add_filter( 'relevanssi_do_not_index', 'relevanssi_rankmath_noindex', 10, 2 );
add_filter( 'relevanssi_indexing_restriction', 'relevanssi_rankmath_exclude' );
add_action( 'relevanssi_indexing_tab_advanced', 'relevanssi_rankmath_form', 20 );
add_action( 'relevanssi_indexing_options', 'relevanssi_rankmath_options' );

/**
 * Blocks indexing of posts marked "noindex" in the Rank Math settings.
 *
 * Attaches to the 'relevanssi_do_not_index' filter hook.
 *
 * WooCommerce products are exempt from this exclusion (see
 * relevanssi_rankmath_is_exempt_post_type()): products are routinely marked
 * noindex for SEO (freebie/POS "Merch" items, bundled specials, etc.) while
 * still needing to be findable through the site's own on-site search, so
 * Rank Math's noindex setting only affects posts/pages here.
 *
 * @param boolean $do_not_index True, if the post shouldn't be indexed.
 * @param integer $post_id      The post ID number.
 *
 * @return string|boolean If the post shouldn't be indexed, this returns
 * 'RankMath'. The value may also be a boolean.
 */
function relevanssi_rankmath_noindex( $do_not_index, $post_id ) {
	if ( 'on' !== get_option( 'relevanssi_seo_noindex' ) ) {
		return $do_not_index;
	}
	if ( relevanssi_rankmath_is_exempt_post_type( get_post_type( $post_id ) ) ) {
		return $do_not_index;
	}
	$noindex = get_post_meta( $post_id, 'rank_math_robots', true );
	if ( is_array( $noindex ) && in_array( 'noindex', $noindex, true ) ) {
		$do_not_index = 'RankMath';
	}
	return $do_not_index;
}

/**
 * Excludes the "noindex" posts from Relevanssi indexing.
 *
 * Adds a MySQL query restriction that blocks posts that have the Rank Math
 * "rank_math_robots" setting set to something that includes "noindex".
 * WooCommerce products are exempt, see relevanssi_rankmath_noindex().
 *
 * @param array $restriction An array with two values: 'mysql' for the MySQL
 * query restriction to modify, 'reason' for the reason of restriction.
 */
function relevanssi_rankmath_exclude( $restriction ) {
	if ( 'on' !== get_option( 'relevanssi_seo_noindex' ) ) {
		return $restriction;
	}

	global $wpdb;

	// Backwards compatibility code for 2.8.0, remove at some point.
	if ( is_string( $restriction ) ) {
		$restriction = array(
			'mysql'  => $restriction,
			'reason' => '',
		);
	}

	$exempt_post_types = relevanssi_rankmath_exempt_post_types();
	$exempt_clause      = '';
	if ( ! empty( $exempt_post_types ) ) {
		$placeholders  = implode( ', ', array_fill( 0, count( $exempt_post_types ), '%s' ) );
		$exempt_clause = $wpdb->prepare( "post.post_type IN ($placeholders) OR ", $exempt_post_types ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	$restriction['mysql']  .= " AND ( $exempt_clause post.ID NOT IN (SELECT post_id FROM
		$wpdb->postmeta WHERE meta_key = 'rank_math_robots'
		AND meta_value LIKE '%noindex%' ) ) ";
	$restriction['reason'] .= ' Rank Math (except exempt post types)';
	return $restriction;
}

/**
 * Post types exempt from the Rank Math noindex exclusion.
 *
 * @return array Post type names, default array( 'product' ).
 */
function relevanssi_rankmath_exempt_post_types() {
	/**
	 * Filters the post types exempt from Relevanssi's Rank Math noindex
	 * exclusion. Posts of these types are indexed (and so findable via
	 * on-site search) even when Rank Math marks them noindex for search
	 * engines.
	 *
	 * @param array Post type names, default array( 'product' ).
	 */
	return apply_filters( 'relevanssi_rankmath_noindex_exempt_post_types', array( 'product' ) );
}

/**
 * Checks whether a post type is exempt from the Rank Math noindex exclusion.
 *
 * @param string $post_type The post type to check.
 *
 * @return boolean True, if the post type is exempt.
 */
function relevanssi_rankmath_is_exempt_post_type( $post_type ) {
	return in_array( $post_type, relevanssi_rankmath_exempt_post_types(), true );
}

/**
 * Prints out the form fields for disabling the feature.
 */
function relevanssi_rankmath_form() {
	$seo_noindex = get_option( 'relevanssi_seo_noindex' );
	$seo_noindex = relevanssi_check( $seo_noindex );

	?>
	<tr>
		<th scope="row">
			<label for='relevanssi_seo_noindex'><?php esc_html_e( 'Use Rank Math SEO noindex', 'relevanssi' ); ?></label>
		</th>
		<td>
			<label for='relevanssi_seo_noindex'>
				<input type='checkbox' name='relevanssi_seo_noindex' id='relevanssi_seo_noindex' <?php echo esc_attr( $seo_noindex ); ?> />
				<?php esc_html_e( 'Use Rank Math SEO noindex.', 'relevanssi' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'If checked, Relevanssi will not index posts marked as "No index" in Rank Math SEO settings.', 'relevanssi' ); ?></p>
			<p class="description"><?php esc_html_e( 'WooCommerce products are exempt from this: noindexed products are still indexed and searchable on-site (filter: relevanssi_rankmath_noindex_exempt_post_types).', 'relevanssi' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Saves the SEO No index option.
 *
 * @param array $request An array of option values from the request.
 */
function relevanssi_rankmath_options( array $request ) {
	relevanssi_update_off_or_on( $request, 'relevanssi_seo_noindex', true );
}
