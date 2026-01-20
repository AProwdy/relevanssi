# Security Inspection Report for Relevanssi

## Executive Summary
The Relevanssi plugin code was inspected for common security vulnerabilities. The plugin generally follows WordPress security best practices, using nonces for action verification, capability checks for access control, and sanitization/escaping for database queries and output.

A few low-severity issues were identified, primarily affecting authenticated users with high privileges or requiring specific configurations.

## Findings

### 1. Authenticated SQL Injection via Settings (Admin Only)
*   **Location:** `lib/phrases.php` in `relevanssi_generate_phrase_queries()`
*   **Vulnerability:** The code constructs a SQL `REGEXP` pattern using the `relevanssi_index_fields` option. If this option contains a percent sign `%` (used for ACF repeater matching), the code manually constructs a regex string without full SQL escaping for the regex context.
*   **Code Snippet:**
    ```php
    if ( strpos( implode( ' ', $custom_fields ), '%' ) ) {
        // ACF repeater fields involved.
        $custom_fields_regexp = str_replace( '%', '.+', implode( '|', $custom_fields ) );
        $keys                 = "AND m.meta_key REGEXP ('$custom_fields_regexp')";
    }
    ```
*   **Impact:** An administrator could theoretically inject SQL commands by crafting a malicious custom field name in the settings. Since this requires `manage_options` capability and CSRF protection is in place for saving options, this is considered low severity (Admin-to-SQLi).

### 2. Potential Self-XSS in Admin Search
*   **Location:** `lib/admin-ajax.php` in `relevanssi_admin_search_debugging_info()`
*   **Vulnerability:** The function echoes search query variables (like `s`) without HTML escaping into the JSON response.
*   **Code Snippet:**
    ```php
    foreach ( $query->query_vars as $key => $value ) {
        // ...
        $result .= "<li>$key: $value</li>";
    }
    ```
*   **Context:** This data is returned via AJAX to the Admin Search interface (`lib/admin_scripts.js`), which inserts it into the DOM using `innerHTML`.
*   **Impact:** A user with `edit_posts` capability can execute arbitrary JavaScript in their own browser by performing a search with a malicious payload (e.g., `<script>alert(1)</script>`).
*   **Mitigation:** The action is protected by a nonce (`relevanssi_admin_search_nonce`), preventing Cross-Site Request Forgery (CSRF). Therefore, an attacker cannot force another user (like an Admin) to execute this search. This limits the issue to Self-XSS.

### 3. Conditional Reflected XSS via Debug Mode
*   **Location:** `lib/debug.php` in `relevanssi_debug_array()`
*   **Vulnerability:** Uses `print_r()` to display data, which does not escape HTML entities.
*   **Context:** If the `relevanssi_debugging_mode` option is enabled, any user can append `?relevanssi_debug=on` to a search URL. If the search term contains XSS characters, they will be reflected in the debug output.
*   **Impact:** Reflected XSS if debugging is enabled on a production site.
*   **Recommendation:** Ensure `relevanssi_debugging_mode` is disabled in production.

### 4. General Observations
*   **SQL Safety:** Most SQL queries use `$wpdb->prepare()` or `esc_sql()`. The phrase matching logic (`lib/phrases.php`) constructs complex queries but generally escapes inputs.
*   **Output Escaping:** Output in admin pages is generally escaped using `esc_html`, `esc_attr`, or `wp_kses`.
*   **Access Control:** AJAX actions consistently check capabilities (`manage_options` or `edit_posts`) and nonces.

## Recommendations
1.  **Escape Output in Debugging:** Modify `relevanssi_admin_search_debugging_info` to use `esc_html()` when printing query variables.
2.  **Sanitize Custom Fields:** Ensure custom field names in settings are strictly sanitized to prevent SQL injection in the `REGEXP` clause.
3.  **Disable Debugging:** Ensure debugging features are disabled by default and used only when necessary.
