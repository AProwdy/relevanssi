# Search Autocomplete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a native, opt-in live-search (autocomplete) dropdown to AP-relevanssi: as a visitor types into any site search box, an admin-ajax endpoint runs the query through Relevanssi and returns a small list of title+link suggestions (with thumbnails for products), shown in a flat-list dropdown with a "View all results" footer row.

**Architecture:** A new `lib/autocomplete.php` registers `wp_ajax_relevanssi_autocomplete` / `wp_ajax_nopriv_relevanssi_autocomplete` handlers that run `relevanssi_do_query()` on a fresh `WP_Query` (the same low-level pattern `relevanssi_admin_search()` uses) and map results through `relevanssi_autocomplete_format_result()`. A new "Autocomplete" settings tab (`lib/tabs/autocomplete-tab.php`, registered in `lib/interface.php`, options handled in `lib/options.php`) controls 3 options: enabled, min characters, max results. When enabled, `wp_enqueue_scripts` loads `lib/autocomplete.js` + `lib/autocomplete.css`, which auto-attach to standard WP search inputs, debounce keystrokes, fetch suggestions, and render the dropdown.

**Tech Stack:** PHP 7.1+ (WordPress plugin conventions: `add_option`, `update_option`, `WP_Query`, `wp_ajax_*`, `wp_localize_script`), vanilla front-end JS (`fetch`, DOM APIs, no jQuery dependency since this runs on the front end), plain CSS. Tests: PHPUnit via `WP_UnitTestCase` (`composer test` / `./vendor/bin/phpunit`), following `tests/test-options.php` and `tests/test-searching.php` conventions.

---

## Before you start

- Current plugin version is `4.27.9` (both `relevanssi.php:16` `* Version: 4.27.9` and `relevanssi.php:69` `$relevanssi_variables['plugin_version'] = '4.27.9';`).
- **Every task that edits a source file must bump BOTH version strings together** (per `CLAUDE.md`), continuing the sequence `4.27.10` → `4.27.17` across the 8 tasks below.
- **Every task ends with a commit whose message includes the new version number**, per `CLAUDE.md`: "Commit: After each completed change, commit immediately with a message that includes the new version number."
- Run tests with `composer test` (= `./vendor/bin/phpunit`) from the plugin root. If `vendor/` isn't installed yet, run `composer install` first.
- Spec reference: `docs/superpowers/specs/2026-06-12-autocomplete-design.md`.

---

## Task 1: Options plumbing (settings storage)

Adds the 3 new option names (`relevanssi_autocomplete_enabled`, `relevanssi_autocomplete_min_chars`, `relevanssi_autocomplete_max_results`) to the install defaults and to `update_relevanssi_options()`'s "autocomplete" tab handler, following the exact pattern used by the existing "logging"/"debugging" tabs.

**Files:**
- Modify: `lib/install.php` (around line 73)
- Modify: `lib/options.php` (around lines 93-98 and 109-110)
- Test: `tests/test-options.php` (after `test_update_relevanssi_options`, around line 215)

- [ ] **Step 1: Write the failing test**

In `tests/test-options.php`, find this block (the end of `test_update_relevanssi_options`):

```php
		$this->assertEquals( 'off', get_option( 'relevanssi_excerpts' ) );
		$this->assertEquals( "Text with 'quotes' to fix.", get_option( 'relevanssi_show_matches_text' ) );
		$this->assertEquals( '1,2,3', get_option( 'relevanssi_exclude_posts' ) );
	}

	/**
	 * Test relevanssi_process_weights_and_indexing.
	 */
	public function test_relevanssi_process_weights_and_indexing() {
```

Insert a new test method between the closing `}` of `test_update_relevanssi_options` and the docblock for `test_relevanssi_process_weights_and_indexing`:

```php
		$this->assertEquals( 'off', get_option( 'relevanssi_excerpts' ) );
		$this->assertEquals( "Text with 'quotes' to fix.", get_option( 'relevanssi_show_matches_text' ) );
		$this->assertEquals( '1,2,3', get_option( 'relevanssi_exclude_posts' ) );
	}

	/**
	 * Test update_relevanssi_options for the autocomplete tab.
	 */
	public function test_update_relevanssi_options_autocomplete() {
		$request = array(
			'rlv_tab'                              => 'autocomplete',
			'relevanssi_autocomplete_enabled'      => 'on',
			'relevanssi_autocomplete_min_chars'    => '4',
			'relevanssi_autocomplete_max_results'  => '7',
		);

		update_relevanssi_options( $request );

		$this->assertEquals( 'on', get_option( 'relevanssi_autocomplete_enabled' ) );
		$this->assertEquals( 4, get_option( 'relevanssi_autocomplete_min_chars' ) );
		$this->assertEquals( 7, get_option( 'relevanssi_autocomplete_max_results' ) );

		// Unchecking the checkbox turns it off; zero values fall back to defaults.
		$request = array(
			'rlv_tab'                              => 'autocomplete',
			'relevanssi_autocomplete_min_chars'    => '0',
			'relevanssi_autocomplete_max_results'  => '0',
		);

		update_relevanssi_options( $request );

		$this->assertEquals( 'off', get_option( 'relevanssi_autocomplete_enabled' ) );
		$this->assertEquals( 3, get_option( 'relevanssi_autocomplete_min_chars' ) );
		$this->assertEquals( 5, get_option( 'relevanssi_autocomplete_max_results' ) );
	}

	/**
	 * Test relevanssi_process_weights_and_indexing.
	 */
	public function test_relevanssi_process_weights_and_indexing() {
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter test_update_relevanssi_options_autocomplete`

Expected: FAIL — `update_relevanssi_options()` doesn't recognize `rlv_tab === 'autocomplete'`, so none of the three `get_option()` calls return the expected values (the options don't exist yet, so `get_option()` returns `false`, and `assertEquals('on', false)` / `assertEquals(4, false)` fail).

- [ ] **Step 3: Add option defaults to `lib/install.php`**

Find this line (around line 73):

```php
	add_option( 'relevanssi_admin_search', 'off' );
	add_option( 'relevanssi_bg_col', '#ffaf75' );
```

Insert three new `add_option()` calls between them, keeping alphabetical order:

```php
	add_option( 'relevanssi_admin_search', 'off' );
	add_option( 'relevanssi_autocomplete_enabled', 'off' );
	add_option( 'relevanssi_autocomplete_max_results', '5' );
	add_option( 'relevanssi_autocomplete_min_chars', '3' );
	add_option( 'relevanssi_bg_col', '#ffaf75' );
```

- [ ] **Step 4: Add the "autocomplete" tab handler to `lib/options.php`**

Find this block (around lines 93-98):

```php
	if ( 'debugging' === $request['rlv_tab'] ) {
		relevanssi_turn_off_options( $request, array( 'relevanssi_debugging_mode' ) );
	}

	relevanssi_process_weights_and_indexing( $request );
```

Insert a new `if` block between them:

```php
	if ( 'debugging' === $request['rlv_tab'] ) {
		relevanssi_turn_off_options( $request, array( 'relevanssi_debugging_mode' ) );
	}

	if ( 'autocomplete' === $request['rlv_tab'] ) {
		relevanssi_turn_off_options( $request, array( 'relevanssi_autocomplete_enabled' ) );
		relevanssi_update_intval( $request, 'relevanssi_autocomplete_min_chars', true, 3 );
		relevanssi_update_intval( $request, 'relevanssi_autocomplete_max_results', true, 5 );
	}

	relevanssi_process_weights_and_indexing( $request );
```

- [ ] **Step 5: Add `relevanssi_autocomplete_enabled` to the autoload `$options` map in `lib/options.php`**

`relevanssi_turn_off_options()` only persists the "off" value if the option name is in the autoload map below (it mutates `$request`, and the map's `array_walk` is what calls `update_option()`). The two `_min_chars`/`_max_results` options are persisted directly by `relevanssi_update_intval()` and do NOT need to be in this map (matching how `relevanssi_excerpt_length` and `relevanssi_min_word_length` — also handled via `relevanssi_update_intval`/`relevanssi_update_floatval` — are absent from this map).

Find (around lines 109-111):

```php
	$options = array(
		'relevanssi_admin_search'            => false,
		'relevanssi_bg_col'                  => true,
```

Change to:

```php
	$options = array(
		'relevanssi_admin_search'            => false,
		'relevanssi_autocomplete_enabled'    => true,
		'relevanssi_bg_col'                  => true,
```

- [ ] **Step 6: Run test to verify it passes**

Run: `composer test -- --filter test_update_relevanssi_options_autocomplete`

Expected: PASS (1 test, several assertions, no failures).

- [ ] **Step 7: Run the full options test file to check for regressions**

Run: `composer test -- --filter OptionsTest`

Expected: PASS (all tests in `OptionsTest` green, including the pre-existing `test_update_relevanssi_options`).

- [ ] **Step 8: Bump version and commit**

In `relevanssi.php`, change line 16:

```php
 * Version: 4.27.9
```
to:
```php
 * Version: 4.27.10
```

And line 69:

```php
$relevanssi_variables['plugin_version']                        = '4.27.9';
```
to:
```php
$relevanssi_variables['plugin_version']                        = '4.27.10';
```

```bash
git add lib/install.php lib/options.php tests/test-options.php relevanssi.php
git commit -m "feat: add autocomplete option storage and bump to v4.27.10"
```

---

## Task 2: Settings tab UI

Adds the "Autocomplete" settings tab: a checkbox to enable the feature and two number fields for min characters / max results, following the `lib/tabs/logging-tab.php` markup pattern. Registers the tab in `lib/interface.php` between "Searching" and "Logging". Creates `tests/test-autocomplete.php`, the test file used by this and all later tasks.

**Files:**
- Create: `lib/tabs/autocomplete-tab.php`
- Modify: `lib/interface.php` (around lines 196-203)
- Create: `tests/test-autocomplete.php`

- [ ] **Step 1: Write the failing test (new file)**

Create `tests/test-autocomplete.php`:

```php
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
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter AutocompleteTest`

Expected: FAIL — `Error: Call to undefined function relevanssi_autocomplete_tab()`.

- [ ] **Step 3: Create `lib/tabs/autocomplete-tab.php`**

```php
<?php
/**
 * /lib/tabs/autocomplete-tab.php
 *
 * Prints out the Autocomplete tab in Relevanssi settings.
 *
 * @package Relevanssi
 * @author  AP Development Team
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

/**
 * Prints out the autocomplete tab in Relevanssi settings.
 */
function relevanssi_autocomplete_tab() {
	$enabled     = get_option( 'relevanssi_autocomplete_enabled' );
	$enabled     = relevanssi_check( $enabled );
	$min_chars   = get_option( 'relevanssi_autocomplete_min_chars' );
	$max_results = get_option( 'relevanssi_autocomplete_max_results' );
	?>
	<table class="form-table" role="presentation">
	<tr id="row_autocomplete_enabled">
		<th scope="row">
			<?php esc_html_e( 'Enable autocomplete', 'relevanssi' ); ?>
		</th>
		<td>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Show live search suggestions as visitors type.', 'relevanssi' ); ?></legend>
			<label for='relevanssi_autocomplete_enabled'>
				<input type='checkbox' name='relevanssi_autocomplete_enabled' id='relevanssi_autocomplete_enabled' <?php echo esc_html( $enabled ); ?> />
				<?php esc_html_e( 'Show live search suggestions as visitors type.', 'relevanssi' ); ?>
			</label>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Adds a dropdown of suggested results under search boxes site-wide as the visitor types. No price or stock information is shown.', 'relevanssi' ); ?></p>
		</td>
	</tr>
	<tr id="row_autocomplete_min_chars">
		<th scope="row">
			<label for='relevanssi_autocomplete_min_chars'><?php esc_html_e( 'Minimum characters', 'relevanssi' ); ?></label>
		</th>
		<td>
			<input type='number' min='1' name='relevanssi_autocomplete_min_chars' id='relevanssi_autocomplete_min_chars' value='<?php echo esc_attr( $min_chars ); ?>' />
			<p class="description"><?php esc_html_e( 'How many characters the visitor must type before suggestions are fetched.', 'relevanssi' ); ?></p>
		</td>
	</tr>
	<tr id="row_autocomplete_max_results">
		<th scope="row">
			<label for='relevanssi_autocomplete_max_results'><?php esc_html_e( 'Number of suggestions', 'relevanssi' ); ?></label>
		</th>
		<td>
			<input type='number' min='1' name='relevanssi_autocomplete_max_results' id='relevanssi_autocomplete_max_results' value='<?php echo esc_attr( $max_results ); ?>' />
			<p class="description"><?php esc_html_e( 'How many suggestions to show in the dropdown.', 'relevanssi' ); ?></p>
		</td>
	</tr>
	</table>
	<?php
}
```

- [ ] **Step 4: Register the tab in `lib/interface.php`**

Find (around lines 196-203):

```php
		array(
			'slug'     => 'searching',
			'name'     => __( 'Searching', 'relevanssi' ),
			'require'  => 'tabs/searching-tab.php',
			'callback' => 'relevanssi_searching_tab',
			'save'     => true,
		),
		array(
			'slug'     => 'logging',
```

Insert a new entry between the "searching" and "logging" entries:

```php
		array(
			'slug'     => 'searching',
			'name'     => __( 'Searching', 'relevanssi' ),
			'require'  => 'tabs/searching-tab.php',
			'callback' => 'relevanssi_searching_tab',
			'save'     => true,
		),
		array(
			'slug'     => 'autocomplete',
			'name'     => __( 'Autocomplete', 'relevanssi' ),
			'require'  => 'tabs/autocomplete-tab.php',
			'callback' => 'relevanssi_autocomplete_tab',
			'save'     => true,
		),
		array(
			'slug'     => 'logging',
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter AutocompleteTest`

Expected: PASS (1 test).

- [ ] **Step 6: Bump version and commit**

In `relevanssi.php`, bump line 16 to `* Version: 4.27.11` and line 69 to `$relevanssi_variables['plugin_version']                        = '4.27.11';`.

```bash
git add lib/tabs/autocomplete-tab.php lib/interface.php tests/test-autocomplete.php relevanssi.php
git commit -m "feat: add autocomplete settings tab and bump to v4.27.11"
```

---

## Task 3: Result formatter

Adds `lib/autocomplete.php` (new file) with `relevanssi_autocomplete_format_result()`, the function that turns a `WP_Post` into the `{title, url, type, thumbnail}` shape used by the dropdown. Products get a `thumbnail` URL when they have a featured image; everything else (and products without one) gets `thumbnail: null`. No result ever contains price or stock keys.

**Files:**
- Create: `lib/autocomplete.php`
- Modify: `relevanssi.php` (around line 75)
- Test: `tests/test-autocomplete.php` (append)

- [ ] **Step 1: Write the failing test**

Append to `tests/test-autocomplete.php`, inside the `AutocompleteTest` class, after `test_relevanssi_autocomplete_tab()`'s closing `}`:

```php

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter test_relevanssi_autocomplete_format_result`

Expected: FAIL — `Error: Call to undefined function relevanssi_autocomplete_format_result()`.

- [ ] **Step 3: Create `lib/autocomplete.php`**

```php
<?php
/**
 * /lib/autocomplete.php
 *
 * Search autocomplete (live suggestions) for Relevanssi.
 *
 * @package Relevanssi
 * @author  AP Development Team
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

/**
 * Formats a post into an autocomplete suggestion.
 *
 * Suggestions never include price or stock information, for any user.
 * Non-product results, and products without a featured image, get a null
 * thumbnail; the front end shows a generic icon in that case.
 *
 * @param WP_Post $post The post to format.
 *
 * @return array {
 *     @type string      $title     The post title.
 *     @type string      $url       The post permalink.
 *     @type string      $type      'product' for the 'product' post type, 'other' otherwise.
 *     @type string|null $thumbnail The product thumbnail URL, or null.
 * }
 */
function relevanssi_autocomplete_format_result( $post ) {
	$type      = 'product' === $post->post_type ? 'product' : 'other';
	$thumbnail = null;

	if ( 'product' === $type ) {
		$thumbnail_url = get_the_post_thumbnail_url( $post, 'thumbnail' );
		if ( $thumbnail_url ) {
			$thumbnail = $thumbnail_url;
		}
	}

	return array(
		'title'     => get_the_title( $post ),
		'url'       => get_permalink( $post ),
		'type'      => $type,
		'thumbnail' => $thumbnail,
	);
}
```

- [ ] **Step 4: Require the new file from `relevanssi.php`**

Find (around line 75):

```php
require_once 'lib/admin-ajax.php';
require_once 'lib/common.php';
```

Change to:

```php
require_once 'lib/admin-ajax.php';
require_once 'lib/autocomplete.php';
require_once 'lib/common.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter test_relevanssi_autocomplete_format_result`

Expected: PASS (1 test, 7 assertions).

- [ ] **Step 6: Bump version and commit**

Bump `relevanssi.php` line 16 to `* Version: 4.27.12` and line 69 to `'4.27.12'`.

```bash
git add lib/autocomplete.php relevanssi.php tests/test-autocomplete.php
git commit -m "feat: add autocomplete result formatter and bump to v4.27.12"
```

---

## Task 4: Search function

Adds `relevanssi_autocomplete_get_results( string $q, int $max_results )` to `lib/autocomplete.php`. It runs `$q` through Relevanssi via the same `new WP_Query(); $query->parse_query(...); relevanssi_do_query($query);` pattern used by `relevanssi_admin_search()` and `tests/test-searching.php`'s `results_from_args()`, then maps the results through `relevanssi_autocomplete_format_result()`.

**Files:**
- Modify: `lib/autocomplete.php` (append)
- Test: `tests/test-autocomplete.php` (append)

- [ ] **Step 1: Write the failing test**

Append to `tests/test-autocomplete.php`, inside `AutocompleteTest`, after `test_relevanssi_autocomplete_format_result()`'s closing `}`:

```php

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter test_relevanssi_autocomplete_get_results`

Expected: FAIL — `Error: Call to undefined function relevanssi_autocomplete_get_results()`.

- [ ] **Step 3: Append `relevanssi_autocomplete_get_results()` to `lib/autocomplete.php`**

Add this function after `relevanssi_autocomplete_format_result()`, at the end of the file:

```php

/**
 * Runs a Relevanssi search for autocomplete suggestions.
 *
 * Uses the same WP_Query + relevanssi_do_query() pattern as
 * relevanssi_admin_search(), so results are filtered through the exact
 * same restrictions (excluded posts/categories, indexed post types,
 * language scoping) as a normal search.
 *
 * @param string $q           The search query.
 * @param int    $max_results Maximum number of suggestions to return.
 *
 * @return array Formatted suggestions, see relevanssi_autocomplete_format_result().
 */
function relevanssi_autocomplete_get_results( string $q, int $max_results ) {
	$query = new WP_Query();
	$query->parse_query(
		array(
			's'              => $q,
			'relevanssi'     => true,
			'posts_per_page' => $max_results,
			'post_status'    => 'publish',
		)
	);
	$posts = relevanssi_do_query( $query );

	return array_map( 'relevanssi_autocomplete_format_result', $posts );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter test_relevanssi_autocomplete_get_results`

Expected: PASS (1 test).

- [ ] **Step 5: Run the full autocomplete test file to check for regressions**

Run: `composer test -- --filter AutocompleteTest`

Expected: PASS (all 3 tests so far: tab rendering, formatter, search).

- [ ] **Step 6: Bump version and commit**

Bump `relevanssi.php` line 16 to `* Version: 4.27.13` and line 69 to `'4.27.13'`.

```bash
git add lib/autocomplete.php relevanssi.php tests/test-autocomplete.php
git commit -m "feat: add autocomplete search function and bump to v4.27.13"
```

---

## Task 5: Gating logic, AJAX handler, and script enqueue

Adds `relevanssi_autocomplete_should_search()` (decides whether a query is worth running, based on the enabled flag and minimum-characters setting), the `wp_ajax_relevanssi_autocomplete` / `wp_ajax_nopriv_relevanssi_autocomplete` handler, and the `wp_enqueue_scripts` hook that loads the front-end JS/CSS (only when the feature is enabled and not in the admin).

**Files:**
- Modify: `lib/autocomplete.php` (append)
- Test: `tests/test-autocomplete.php` (append)

- [ ] **Step 1: Write the failing test**

Append to `tests/test-autocomplete.php`, inside `AutocompleteTest`, after `test_relevanssi_autocomplete_get_results()`'s closing `}`:

```php

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter test_relevanssi_autocomplete_should_search`

Expected: FAIL — `Error: Call to undefined function relevanssi_autocomplete_should_search()`.

- [ ] **Step 3: Append the gating function, AJAX handler, and enqueue hook to `lib/autocomplete.php`**

Add this at the end of the file, after `relevanssi_autocomplete_get_results()`:

```php

add_action( 'wp_ajax_relevanssi_autocomplete', 'relevanssi_autocomplete_ajax' );
add_action( 'wp_ajax_nopriv_relevanssi_autocomplete', 'relevanssi_autocomplete_ajax' );
add_action( 'wp_enqueue_scripts', 'relevanssi_autocomplete_enqueue_scripts' );

/**
 * Decides whether an autocomplete request should run a search.
 *
 * @param string $q The search query string.
 *
 * @return bool True if the feature is enabled and the query is long enough.
 */
function relevanssi_autocomplete_should_search( string $q ) {
	if ( 'on' !== get_option( 'relevanssi_autocomplete_enabled' ) ) {
		return false;
	}

	$min_chars = (int) get_option( 'relevanssi_autocomplete_min_chars', 3 );

	return mb_strlen( $q ) >= $min_chars;
}

/**
 * Handles the relevanssi_autocomplete AJAX action.
 *
 * Registered for both logged-in and logged-out users, since search must
 * work for anonymous visitors.
 */
function relevanssi_autocomplete_ajax() {
	check_ajax_referer( 'relevanssi_autocomplete', 'nonce' );

	$q = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['q'] ) ) : '';

	if ( ! relevanssi_autocomplete_should_search( $q ) ) {
		wp_send_json_success(
			array(
				'query'   => $q,
				'results' => array(),
			)
		);
	}

	$max_results = (int) get_option( 'relevanssi_autocomplete_max_results', 5 );

	wp_send_json_success(
		array(
			'query'   => $q,
			'results' => relevanssi_autocomplete_get_results( $q, $max_results ),
		)
	);
}

/**
 * Enqueues the autocomplete script and style on the front end.
 *
 * Only runs when the feature is enabled in settings and we're not in the
 * admin area.
 *
 * @global array $relevanssi_variables The global Relevanssi variables array.
 */
function relevanssi_autocomplete_enqueue_scripts() {
	if ( is_admin() ) {
		return;
	}

	if ( 'on' !== get_option( 'relevanssi_autocomplete_enabled' ) ) {
		return;
	}

	global $relevanssi_variables;
	$plugin_dir_url = plugin_dir_url( $relevanssi_variables['file'] );
	$version        = $relevanssi_variables['plugin_version'];

	wp_enqueue_style( 'relevanssi-autocomplete', $plugin_dir_url . 'lib/autocomplete.css', array(), $version );
	wp_enqueue_script( 'relevanssi-autocomplete', $plugin_dir_url . 'lib/autocomplete.js', array(), $version, true );

	wp_localize_script(
		'relevanssi-autocomplete',
		'relevanssiAutocomplete',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'relevanssi_autocomplete' ),
			'minChars'   => (int) get_option( 'relevanssi_autocomplete_min_chars', 3 ),
			'maxResults' => (int) get_option( 'relevanssi_autocomplete_max_results', 5 ),
		)
	);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter test_relevanssi_autocomplete_should_search`

Expected: PASS (1 test, 3 assertions).

- [ ] **Step 5: Run the full autocomplete test file to check for regressions**

Run: `composer test -- --filter AutocompleteTest`

Expected: PASS (all 4 tests).

- [ ] **Step 6: Bump version and commit**

Bump `relevanssi.php` line 16 to `* Version: 4.27.14` and line 69 to `'4.27.14'`.

```bash
git add lib/autocomplete.php relevanssi.php tests/test-autocomplete.php
git commit -m "feat: add autocomplete AJAX handler and script enqueue, bump to v4.27.14"
```

**Note for Step 7 of the next task:** at this point `lib/autocomplete.css` and `lib/autocomplete.js` are enqueued but don't exist yet. That's fine — `relevanssi_autocomplete_enabled` defaults to `off`, so `relevanssi_autocomplete_enqueue_scripts()` won't run on a default install. Tasks 6 and 7 create those files before any manual testing happens.

---

## Task 6: Front-end JS

Creates `lib/autocomplete.js`: a vanilla-JS, dependency-free script (the front end can't assume jQuery is loaded) that auto-attaches to `#s`, `input[type="search"]`, and `.search-field` inputs, debounces keystrokes (~250ms), fetches `relevanssi_autocomplete` via `admin-ajax.php`, renders a flat-list dropdown (thumbnail or generic icon + title, plus a "View all results" footer row), supports Arrow/Enter/Escape keyboard navigation and click-outside-to-close, and only ever uses `textContent` for dynamic text.

**Files:**
- Create: `lib/autocomplete.js`

There is no PHPUnit test for this file — it's pure front-end behavior, covered by the manual verification checklist in Task 8. Read `relevanssiAutocomplete` (the object localized in Task 5: `ajaxUrl`, `nonce`, `minChars`, `maxResults`).

- [ ] **Step 1: Create `lib/autocomplete.js`**

```js
( function () {
	'use strict';

	if ( 'undefined' === typeof relevanssiAutocomplete ) {
		return;
	}

	var settings = relevanssiAutocomplete;
	var debounceTimer = null;

	function debounce( fn, delay ) {
		return function () {
			var args = arguments;
			var context = this;
			window.clearTimeout( debounceTimer );
			debounceTimer = window.setTimeout( function () {
				fn.apply( context, args );
			}, delay );
		};
	}

	function createIcon() {
		var icon = document.createElement( 'div' );
		icon.className = 'relevanssi-autocomplete-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		icon.textContent = '▣';
		return icon;
	}

	function createThumbnail( url ) {
		var img = document.createElement( 'img' );
		img.className = 'relevanssi-autocomplete-thumbnail';
		img.src = url;
		img.alt = '';
		return img;
	}

	function buildRow( result, index ) {
		var row = document.createElement( 'div' );
		row.className = 'relevanssi-autocomplete-row';
		row.setAttribute( 'role', 'option' );
		row.id = 'relevanssi-autocomplete-option-' + index;
		row.tabIndex = -1;

		if ( 'product' === result.type && result.thumbnail ) {
			row.appendChild( createThumbnail( result.thumbnail ) );
		} else {
			row.appendChild( createIcon() );
		}

		var title = document.createElement( 'div' );
		title.className = 'relevanssi-autocomplete-title';
		title.textContent = result.title;
		row.appendChild( title );

		row.addEventListener( 'click', function () {
			window.location.href = result.url;
		} );

		return row;
	}

	function buildFooterRow( query, form, index ) {
		var row = document.createElement( 'div' );
		row.className = 'relevanssi-autocomplete-row relevanssi-autocomplete-footer';
		row.setAttribute( 'role', 'option' );
		row.id = 'relevanssi-autocomplete-option-' + index;
		row.tabIndex = -1;

		var label = document.createElement( 'div' );
		label.className = 'relevanssi-autocomplete-title';
		label.textContent = 'View all results for "' + query + '"';
		row.appendChild( label );

		row.addEventListener( 'click', function () {
			if ( form ) {
				form.submit();
			}
		} );

		return row;
	}

	function setupField( input ) {
		var form = input.form;

		var wrapper = document.createElement( 'div' );
		wrapper.className = 'relevanssi-autocomplete-wrapper';
		input.parentNode.insertBefore( wrapper, input );
		wrapper.appendChild( input );

		var dropdown = document.createElement( 'div' );
		dropdown.className = 'relevanssi-autocomplete';
		dropdown.setAttribute( 'role', 'listbox' );
		dropdown.style.display = 'none';
		wrapper.appendChild( dropdown );

		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'autocomplete', 'off' );

		var activeIndex = -1;
		var rowCount = 0;

		function closeDropdown() {
			dropdown.style.display = 'none';
			dropdown.textContent = '';
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			activeIndex = -1;
			rowCount = 0;
		}

		function setActive( index ) {
			var rows = dropdown.querySelectorAll( '.relevanssi-autocomplete-row' );
			for ( var i = 0; i < rows.length; i++ ) {
				rows[ i ].classList.remove( 'is-active' );
			}
			if ( index >= 0 && index < rows.length ) {
				rows[ index ].classList.add( 'is-active' );
				input.setAttribute( 'aria-activedescendant', rows[ index ].id );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
			activeIndex = index;
		}

		function renderResults( query, results ) {
			dropdown.textContent = '';
			var index = 0;

			results.forEach( function ( result ) {
				dropdown.appendChild( buildRow( result, index ) );
				index++;
			} );

			dropdown.appendChild( buildFooterRow( query, form, index ) );
			rowCount = index + 1;

			dropdown.style.display = 'block';
			input.setAttribute( 'aria-expanded', 'true' );
			setActive( -1 );
		}

		function fetchResults( query ) {
			var url = settings.ajaxUrl
				+ '?action=relevanssi_autocomplete'
				+ '&nonce=' + encodeURIComponent( settings.nonce )
				+ '&q=' + encodeURIComponent( query );

			fetch( url, { credentials: 'same-origin' } )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Request failed' );
					}
					return response.json();
				} )
				.then( function ( data ) {
					if ( ! data || ! data.success ) {
						closeDropdown();
						return;
					}
					renderResults( query, data.data.results || [] );
				} )
				.catch( function () {
					closeDropdown();
				} );
		}

		var onInput = debounce( function () {
			var query = input.value.trim();

			if ( query.length < settings.minChars ) {
				closeDropdown();
				return;
			}

			fetchResults( query );
		}, 250 );

		input.addEventListener( 'input', onInput );

		input.addEventListener( 'keydown', function ( event ) {
			if ( ! rowCount ) {
				return;
			}

			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				setActive( activeIndex < rowCount - 1 ? activeIndex + 1 : 0 );
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				setActive( activeIndex > 0 ? activeIndex - 1 : rowCount - 1 );
			} else if ( 'Enter' === event.key ) {
				if ( activeIndex > -1 ) {
					event.preventDefault();
					var rows = dropdown.querySelectorAll( '.relevanssi-autocomplete-row' );
					rows[ activeIndex ].click();
				}
			} else if ( 'Escape' === event.key ) {
				closeDropdown();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! wrapper.contains( event.target ) ) {
				closeDropdown();
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var inputs = document.querySelectorAll( '#s, input[type="search"], .search-field' );
		var seen = [];

		inputs.forEach( function ( input ) {
			if ( seen.indexOf( input ) > -1 ) {
				return;
			}
			seen.push( input );
			setupField( input );
		} );
	} );
} )();
```

- [ ] **Step 2: Run the full test suite to check for regressions**

Run: `composer test`

Expected: PASS (no PHP changes were made in this task, so this should be unchanged from Task 5 — this step is a sanity check before committing).

- [ ] **Step 3: Bump version and commit**

Bump `relevanssi.php` line 16 to `* Version: 4.27.15` and line 69 to `'4.27.15'`.

```bash
git add lib/autocomplete.js relevanssi.php
git commit -m "feat: add autocomplete front-end script and bump to v4.27.15"
```

---

## Task 7: Front-end CSS

Creates `lib/autocomplete.css`, implementing the approved flat-list mockup: an absolutely-positioned dropdown panel below the bound input, rows with a 32×32 thumbnail or generic icon cell plus title, an active/hover highlight, and a distinctly-styled "View all results" footer row.

**Files:**
- Create: `lib/autocomplete.css`

- [ ] **Step 1: Create `lib/autocomplete.css`**

```css
.relevanssi-autocomplete-wrapper {
	position: relative;
}

.relevanssi-autocomplete {
	position: absolute;
	top: 100%;
	left: 0;
	right: 0;
	z-index: 9999;
	max-height: 320px;
	overflow-y: auto;
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 0 0 4px 4px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
	font-size: 14px;
}

.relevanssi-autocomplete-row {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 6px 10px;
	cursor: pointer;
}

.relevanssi-autocomplete-row + .relevanssi-autocomplete-row {
	border-top: 1px solid #f0f0f1;
}

.relevanssi-autocomplete-row.is-active,
.relevanssi-autocomplete-row:hover {
	background: #f0f6fc;
}

.relevanssi-autocomplete-thumbnail {
	width: 32px;
	height: 32px;
	object-fit: cover;
	border-radius: 3px;
	flex: none;
}

.relevanssi-autocomplete-icon {
	width: 32px;
	height: 32px;
	flex: none;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 16px;
	color: #a7aaad;
	background: #f6f7f7;
	border-radius: 3px;
}

.relevanssi-autocomplete-title {
	flex: 1;
	color: #1d2327;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.relevanssi-autocomplete-footer {
	color: #2271b1;
	font-weight: 600;
}
```

- [ ] **Step 2: Run the full test suite to check for regressions**

Run: `composer test`

Expected: PASS (no PHP changes in this task either — sanity check).

- [ ] **Step 3: Bump version and commit**

Bump `relevanssi.php` line 16 to `* Version: 4.27.16` and line 69 to `'4.27.16'`.

```bash
git add lib/autocomplete.css relevanssi.php
git commit -m "feat: add autocomplete front-end styles and bump to v4.27.16"
```

---

## Task 8: Changelog entry and manual verification

Adds a single changelog entry summarizing the whole feature, then walks through the manual verification checklist from the spec on a real (staging/dev) WordPress install, since the AJAX endpoint, enqueue gating, and JS/CSS can't be exercised by PHPUnit alone.

**Files:**
- Modify: `changelog.txt` (top of file)

- [ ] **Step 1: Add a changelog entry**

Find the top of `changelog.txt`:

```
= 4.25.0 =
* New feature: New filter hook `relevanssi_index_excerpt` can be used to control which excerpts are indexed and which are not.
```

Insert a new entry above it:

```
= 4.27.17 =
* New feature: Added an opt-in search autocomplete (live suggestions). When enabled on the new "Autocomplete" settings tab, a dropdown of matching titles (with thumbnails for products) appears under search boxes site-wide as visitors type. No price or stock information is shown.

= 4.25.0 =
* New feature: New filter hook `relevanssi_index_excerpt` can be used to control which excerpts are indexed and which are not.
```

- [ ] **Step 2: Run the full test suite**

Run: `composer test`

Expected: PASS (all `OptionsTest` and `AutocompleteTest` tests green, no regressions in the rest of the suite).

- [ ] **Step 3: Bump version and commit**

Bump `relevanssi.php` line 16 to `* Version: 4.27.17` and line 69 to `'4.27.17'`.

```bash
git add changelog.txt relevanssi.php
git commit -m "docs: add changelog entry for autocomplete feature, bump to v4.27.17"
```

- [ ] **Step 4: Manual verification checklist**

On a staging/dev site with this branch deployed and `composer install` run:

1. Go to Relevanssi settings → "Autocomplete" tab. Confirm the three fields appear (checkbox off, min chars `3`, max results `5` by default).
2. With the feature **disabled**, view any front-end page with a search box, open browser dev tools → Network tab, and confirm no `autocomplete.js`/`autocomplete.css` requests are made and no dropdown appears when typing.
3. Enable the feature, save settings. Reload a front-end page and confirm `autocomplete.js` and `autocomplete.css` are now loaded.
4. Type a known product name (≥ `min_chars` characters) into the header search box. Confirm:
   - A dropdown appears below the input.
   - Products with a featured image show a 32×32 thumbnail; everything else (and products without an image) shows the generic `▣` icon.
   - No price or stock text appears on any row.
   - A "View all results for "..."" row appears at the bottom.
5. Type a known page/post title. Confirm it appears with the generic icon and correct title.
6. Type fewer than `min_chars` characters. Confirm no request is sent and the dropdown stays hidden.
7. Type a query that matches nothing indexed. Confirm the dropdown shows only the "View all results" row.
8. Click a product/page row. Confirm the browser navigates directly to that item's permalink.
9. Click the "View all results" row. Confirm the original search form submits (full search results page loads).
10. With the dropdown open, press ArrowDown/ArrowUp to move the highlight through all rows (including the footer), press Enter on a highlighted row to activate it, and press Escape to close the dropdown.
11. Click outside the search box/dropdown and confirm it closes.
12. Repeat steps 4-11 on a secondary search input (e.g., a widget search form using `.search-field`) to confirm the script attaches to multiple inputs without double-binding.

---

## Self-Review Notes

- **Spec coverage:** All 7 spec components are covered — options/tab (Tasks 1-2), result formatter (Task 3), search function (Task 4), AJAX handler + enqueue (Task 5), JS (Task 6), CSS (Task 7), `relevanssi.php` require + changelog (Tasks 3 & 8). The "no price/stock, ever" rule is enforced by Task 3's test (`assertArrayNotHasKey('price', ...)` / `assertArrayNotHasKey('stock', ...)`) and never introduced in the JS. The flat-list layout, single generic icon, footer row, and direct-navigation click behavior are implemented in Task 6/7 per the approved mockup.
- **Refinement vs. spec:** The spec describes `new WP_Query(['s' => $q, 'relevanssi' => true, ...])`; Task 4 instead uses `new WP_Query(); $query->parse_query($args); relevanssi_do_query($query);` — the explicit, directly-testable pattern already used by `relevanssi_admin_search()` and `tests/test-searching.php`. The `'relevanssi' => true` query var is retained in `$args` for parity with the spec's stated integration point, even though `relevanssi_do_query()` doesn't gate on it when called directly.
- **Options map refinement:** The spec says to add all three new option names to the autoload `$options` map in `lib/options.php`. Task 1 adds only `relevanssi_autocomplete_enabled` (a checkbox needing `relevanssi_turn_off_options`), matching the established pattern where `relevanssi_update_intval`-handled options (e.g. `relevanssi_excerpt_length`, `relevanssi_min_word_length`) are *not* duplicated in that map, since `relevanssi_update_intval` already calls `update_option()` directly.
- **Type/signature consistency:** `relevanssi_autocomplete_format_result( $post )` (Task 3) is used unchanged by `relevanssi_autocomplete_get_results()` (Task 4) via `array_map`. `relevanssi_autocomplete_should_search( string $q )` (Task 5) and `relevanssi_autocomplete_get_results( string $q, int $max_results )` (Task 4) are both called from `relevanssi_autocomplete_ajax()` (Task 5) with matching signatures. The `relevanssiAutocomplete` JS object's keys (`ajaxUrl`, `nonce`, `minChars`, `maxResults`) match exactly between `wp_localize_script()` (Task 5) and `lib/autocomplete.js` (Task 6).
- **Placeholder scan:** No TBD/TODO markers; every step has complete, runnable code and exact `composer test -- --filter ...` commands with expected outcomes.
