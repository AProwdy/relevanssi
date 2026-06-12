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
}
