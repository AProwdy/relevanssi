# Search Autocomplete — Design Spec

Date: 2026-06-12
Plugin: AP-relevanssi (fork of Relevanssi, v4.27.9)

## Problem

The site has no live/instant search suggestions. The stock free Relevanssi
overview tab (`lib/tabs/overview-tab.php`) points admins to the separate
"Relevanssi Live Ajax Search" plugin, which isn't installed. This spec covers
building a small autocomplete dropdown natively into AP-relevanssi so typing
in any site search box shows live suggestions powered by Relevanssi.

## Scope

- Searches **all indexed content** (whatever post types are already in
  `relevanssi_index_post_types` — products, pages, posts, etc.). No new
  post-type filtering is introduced.
- Suggestions show **title + link only**. No price, no stock status, for any
  user (logged in or not). Products additionally show their featured image
  thumbnail; non-product results show a generic type icon instead.
- Autocomplete **auto-attaches to standard WordPress search inputs**
  (`#s`, `input[type="search"]`, `.search-field`) site-wide — no theme
  template edits required.
- **Flat list** layout (see "UI / Layout" below): one ranked list, products
  get a thumbnail, everything else gets an icon.
- **Clicking a suggestion navigates directly** to that product/page/post.
  A trailing "View all results for X" row submits the normal search form.
- Feature ships **disabled by default**; an admin enables it via a new
  settings tab.

## Out of scope (not building now)

- Price / stock display in suggestions (explicitly excluded by request)
- Grouped-by-type or minimal text-only layouts (rejected mockup options)
- Result caching/transients beyond client-side debounce
- REST API endpoint (admin-ajax is used instead, see Approach)

## Approach

**admin-ajax endpoint + `WP_Query` with `'relevanssi' => true`**

A new AJAX action runs a `WP_Query` with the `relevanssi` query var set to
`true` (the same integration point Relevanssi exposes to other plugins, e.g.
`lib/compatibility/wp-search-suggest.php`). This guarantees autocomplete
results are filtered through the exact same restrictions as a normal search
(excluded posts/categories, indexed post types, WPML/Polylang language
scoping), so suggestions never show something "View all results" wouldn't.

Rejected alternative: a new REST API route. It would work, but admin-ajax is
the existing convention in this fork (`lib/admin-ajax.php`), and introducing
a second transport mechanism for one feature isn't worth it.

## Components

### 1. `lib/autocomplete.php` (new)

- `add_action('wp_ajax_relevanssi_autocomplete', ...)` and
  `add_action('wp_ajax_nopriv_relevanssi_autocomplete', ...)`
- Handler:
  1. `check_ajax_referer('relevanssi_autocomplete', 'nonce')`
  2. Bail with `wp_send_json_success(['results' => []])` if
     `relevanssi_autocomplete_enabled` option is off, or if `q` is shorter
     than `relevanssi_autocomplete_min_chars`
  3. `sanitize_text_field($_REQUEST['q'])`
  4. `new WP_Query(['s' => $q, 'relevanssi' => true, 'posts_per_page' => $max_results])`
  5. Map each post to:
     ```php
     [
       'title'     => get_the_title($post),
       'url'       => get_permalink($post),
       'type'      => 'product' === $post->post_type ? 'product' : 'other',
       'thumbnail' => 'product' === $post->post_type
           ? get_the_post_thumbnail_url($post, 'thumbnail') ?: null
           : null,
     ]
     ```
     `thumbnail` is `null` whenever there's no featured image (including
     products without one) — the JS falls back to the generic icon in that
     case, same as any `type: 'other'` row.
  6. `wp_send_json_success(['results' => $results, 'query' => $q])`
- `add_action('wp_enqueue_scripts', ...)`: when
  `relevanssi_autocomplete_enabled` is on and `!is_admin()`, enqueue
  `lib/autocomplete.css` and `lib/autocomplete.js`, and
  `wp_localize_script()` the JS with:
  ```php
  [
    'ajaxUrl'    => admin_url('admin-ajax.php'),
    'nonce'      => wp_create_nonce('relevanssi_autocomplete'),
    'minChars'   => (int) get_option('relevanssi_autocomplete_min_chars', 3),
    'maxResults' => (int) get_option('relevanssi_autocomplete_max_results', 5),
  ]
  ```

### 2. `lib/autocomplete.js` (new)

- On `DOMContentLoaded`, `document.querySelectorAll('#s, input[type="search"], .search-field')`
  — for each match, wrap/attach a dropdown container and bind a `keyup`
  listener (debounced ~250ms).
- If `value.length < minChars`, hide dropdown and return.
- Otherwise `fetch(ajaxUrl + '?action=relevanssi_autocomplete&nonce=...&q=...')`.
- Render results (flat list — see UI/Layout). Always append a final row:
  "View all results for "{q}"" which submits the original `<form>`.
- If `results` is empty, render only the "View all results" row.
- If the fetch fails or returns an error, hide the dropdown (no error UI);
  normal form submission still works.
- Keyboard: ArrowUp/ArrowDown move a `.active` highlight between rows
  (including the footer row), Enter activates the highlighted row (navigate
  or submit), Escape closes the dropdown. Click outside the input/dropdown
  closes it.
- Use `textContent` (never `innerHTML`) when inserting `title`/`query` text
  into the DOM, to avoid XSS via indexed content.
- Basic ARIA: dropdown gets `role="listbox"`, rows get `role="option"`, input
  gets `aria-expanded`/`aria-activedescendant` updated as the user navigates.

### 3. `lib/autocomplete.css` (new)

Implements the approved flat-list mockup:
- `.relevanssi-autocomplete` — absolutely positioned dropdown panel directly
  below the bound input, bordered, white background, scrollable if tall.
- `.relevanssi-autocomplete-row` — flex row: 32×32 thumbnail or icon cell +
  title text.
- `.relevanssi-autocomplete-row.is-active` — highlighted/keyboard-focused row.
- `.relevanssi-autocomplete-footer` — "View all results for X" row, styled
  distinctly (link color) and pinned as the last row.
- Icon cell for non-product types uses a simple inline SVG/emoji placeholder
  (no icon font dependency).

### 4. `lib/tabs/autocomplete-tab.php` (new settings tab)

Three fields, following the existing `relevanssi_check()` /
`relevanssi_select()` helper patterns used by other tabs:

- **Enable autocomplete search** — checkbox,
  option `relevanssi_autocomplete_enabled`, default off
- **Minimum characters before searching** — number input,
  option `relevanssi_autocomplete_min_chars`, default `3`
- **Number of results to show** — number input,
  option `relevanssi_autocomplete_max_results`, default `5`

### 5. `lib/interface.php`

Add a new entry to the `$tabs` array (after `searching`, before `logging`):

```php
array(
    'slug'     => 'autocomplete',
    'name'     => __( 'Autocomplete', 'relevanssi' ),
    'require'  => 'tabs/autocomplete-tab.php',
    'callback' => 'relevanssi_autocomplete_tab',
    'save'     => true,
),
```

### 6. `lib/options.php`

- Add a new `if ( 'autocomplete' === $request['rlv_tab'] )` block:
  - `relevanssi_turn_off_options($request, ['relevanssi_autocomplete_enabled'])`
  - `relevanssi_update_intval($request, 'relevanssi_autocomplete_min_chars', true, 3)`
  - `relevanssi_update_intval($request, 'relevanssi_autocomplete_max_results', true, 5)`
- Add the three option names to the autoload `$options` map (all `true`,
  consistent with other small scalar settings).

### 7. `relevanssi.php`

Add `require_once 'lib/autocomplete.php';` alongside the other `lib/*`
requires, and bump `Version:` in the plugin header (per project convention,
every edit bumps the patch version — final number to be decided at
implementation time based on the version at that point).

## UI / Layout (approved mockup: "Flat list")

One ranked list, no section headers:

```
┌─────────────────────────────────────────┐
│ [IMG] Seachem Prime 500ml                │
│ [IMG] Seachem Flourish 250ml             │
│ [ ▣ ] About Us                           │
│ [ ▣ ] How to Cycle a New Aquarium        │
├─────────────────────────────────────────┤
│ View all results for "seachem" →         │
└─────────────────────────────────────────┘
```

- Products with a featured image: 32×32 thumbnail + title
- Everything else (pages, posts, other indexed types, and products with no
  featured image): one generic icon + title — a single icon style, not
  varied per post type
- No price, no stock, on any row
- Footer row always present, submits the existing search form

## Error Handling

| Condition | Behavior |
|---|---|
| Query shorter than `minChars` | No request sent, dropdown hidden |
| AJAX request fails / network error | Dropdown hidden, normal submit still works |
| Nonce check fails | `wp_send_json_error()`, JS treats as empty results |
| No matching posts | Dropdown shows only the "View all results" row |
| Feature disabled in settings | JS/CSS not enqueued at all |

## Security

- `check_ajax_referer('relevanssi_autocomplete', 'nonce')` on every request
- `sanitize_text_field()` on the incoming query string before use in `WP_Query`
- All dynamic text inserted via `textContent`, never `innerHTML`
- Endpoint is read-only (no writes), registered for both logged-in and
  `nopriv` users since search must work for anonymous visitors

## Testing

- **Manual**: enable the setting, type a known product name and a known page
  title into the header search box on the live/staging site; verify
  thumbnails appear for products, icons for pages, keyboard navigation works,
  clicking a row navigates correctly, and the footer row performs a full
  search. Also verify the dropdown does *not* appear when the feature is
  disabled.
- **PHPUnit** (plugin has `tests/` + `phpunit.xml`): add a test for the
  result-mapping function — given a mocked set of `WP_Post` objects (mix of
  `product` and `page`/`post` types), assert the returned array has the
  correct `title`/`url`/`type`/`thumbnail` shape and that non-products never
  get a `thumbnail` value and no result ever contains price/stock keys.

## Open items for implementation plan

None — all decisions above were confirmed during brainstorming. The
implementation plan should sequence: settings tab + options plumbing first
(so the feature can be toggled off throughout development), then the
AJAX handler, then JS/CSS, then enqueue wiring, then tests.
