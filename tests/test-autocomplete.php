<?php
/**
 * Class AutocompleteTest
 *
 * @package Relevanssi
 * @author  AP Development Team
 */

/**
 * Test the Relevanssi search autocomplete feature.
 *
 * @group autocomplete
 */
class AutocompleteTest extends WP_UnitTestCase {

	/**
	 * Installs Relevanssi and registers the 'product' post type (normally
	 * provided by WooCommerce) so autocomplete tests can create product
	 * posts without WooCommerce installed.
	 */
	public static function wpSetUpBeforeClass() {
		relevanssi_install();
		relevanssi_init();

		register_post_type(
			'product',
			array(
				'public' => true,
				'label'  => 'Products',
			)
		);
	}

	/**
	 * Test the autocomplete settings tab renders the saved option values.
	 */
	public function test_relevanssi_autocomplete_tab() {
		update_option( 'relevanssi_autocomplete_enabled', 'on' );
		update_option( 'relevanssi_autocomplete_min_chars', 4 );
		update_option( 'relevanssi_autocomplete_max_results', 8 );

		ob_start();
		relevanssi_autocomplete_tab();
		$output = ob_get_clean();

		$this->assertStringContainsString( "name='relevanssi_autocomplete_enabled'", $output );
		$this->assertStringContainsString( 'checked', $output );
		$this->assertStringContainsString( "name='relevanssi_autocomplete_min_chars'", $output );
		$this->assertStringContainsString( "value='4'", $output );
		$this->assertStringContainsString( "name='relevanssi_autocomplete_max_results'", $output );
		$this->assertStringContainsString( "value='8'", $output );
	}

	/**
	 * Test relevanssi_autocomplete_format_result().
	 */
	public function test_relevanssi_autocomplete_format_result() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'About Us',
			)
		);

		$result = relevanssi_autocomplete_format_result( get_post( $page_id ) );

		$this->assertSame( 'About Us', $result['title'] );
		$this->assertSame( get_permalink( $page_id ), $result['url'] );
		$this->assertSame( 'other', $result['type'] );
		$this->assertNull( $result['thumbnail'] );
		$this->assertArrayNotHasKey( 'price', $result );
		$this->assertArrayNotHasKey( 'stock', $result );

		$product_id = self::factory()->post->create(
			array(
				'post_type'  => 'product',
				'post_title' => 'Seachem Prime 500ml',
			)
		);

		$result = relevanssi_autocomplete_format_result( get_post( $product_id ) );

		$this->assertSame( 'Seachem Prime 500ml', $result['title'] );
		$this->assertSame( 'product', $result['type'] );
		$this->assertNull( $result['thumbnail'] );
		$this->assertArrayNotHasKey( 'price', $result );
		$this->assertArrayNotHasKey( 'stock', $result );

		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'thumbnail.jpg',
				'post_parent'    => $product_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		set_post_thumbnail( $product_id, $attachment_id );

		$result = relevanssi_autocomplete_format_result( get_post( $product_id ) );

		$this->assertNotNull( $result['thumbnail'] );
		$this->assertSame( get_the_post_thumbnail_url( $product_id, 'thumbnail' ), $result['thumbnail'] );
	}

	/**
	 * Test relevanssi_autocomplete_get_results().
	 */
	public function test_relevanssi_autocomplete_get_results() {
		relevanssi_truncate_index();

		$matching_id = self::factory()->post->create(
			array(
				'post_title'   => 'Seachem Prime Water Conditioner',
				'post_content' => 'Seachem Prime removes chlorine and chloramine from tap water.',
				'post_status'  => 'publish',
			)
		);

		relevanssi_build_index( false, false, 200, false );

		$results = relevanssi_autocomplete_get_results( 'Seachem Prime', 5 );

		$this->assertNotEmpty( $results );

		$titles = wp_list_pluck( $results, 'title' );
		$urls   = wp_list_pluck( $results, 'url' );

		$this->assertContains( get_the_title( $matching_id ), $titles );
		$this->assertContains( get_permalink( $matching_id ), $urls );
	}

	/**
	 * Test relevanssi_autocomplete_should_search().
	 */
	public function test_relevanssi_autocomplete_should_search() {
		update_option( 'relevanssi_autocomplete_enabled', 'off' );
		update_option( 'relevanssi_autocomplete_min_chars', 3 );

		$this->assertFalse( relevanssi_autocomplete_should_search( 'seachem' ) );

		update_option( 'relevanssi_autocomplete_enabled', 'on' );

		$this->assertFalse( relevanssi_autocomplete_should_search( 'se' ) );
		$this->assertTrue( relevanssi_autocomplete_should_search( 'sea' ) );
	}
}
