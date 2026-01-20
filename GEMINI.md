# Relevanssi - WordPress Search Plugin

## Project Overview

Relevanssi is a popular WordPress plugin that replaces the default WordPress search with a relevance-sorting partial-match search engine. It includes features for fuzzy matching, custom excerpts, term highlighting, and advanced indexing options.

**Key Technologies:**
- **Language:** PHP
- **Platform:** WordPress (Plugin)
- **Dependencies:** Managed via Composer

## File Structure

- **`relevanssi.php`**: The main plugin entry point. Contains the plugin header and core constants.
- **`lib/`**: Contains the bulk of the plugin's logic and functionality.
    - **`lib/init.php`**: Initialization hooks and loading compatibility code.
    - **`lib/indexing.php`**: Logic related to building and maintaining the search index.
    - **`lib/search.php`**: Handles the search query execution.
    - **`lib/options.php`**: Manages plugin settings and options processing.
    - **`lib/compatibility/`**: Compatibility modules for third-party plugins (WooCommerce, ACF, etc.).
    - **`lib/tabs/`**: Renders the individual tabs in the admin settings UI.
- **`tests/`**: Contains PHPUnit tests.
- **`languages/`**: Translation files (.po/.mo).
- **`stopwords/`**: Localized stopword lists.

## Development & Building

### Dependencies
This project uses Composer.
```bash
composer install
```

### Linting
The project adheres to WordPress Coding Standards.
```bash
# Run PHP CodeSniffer
./vendor/bin/phpcs -p -s
```
Configuration is defined in `phpcs.xml.dist`.

### Testing
Tests are built using PHPUnit.
```bash
# Run tests
./vendor/bin/phpunit
```
Configuration is in `phpunit.xml`.

There is also a script `multi-version-test.sh` designed to run tests against multiple WordPress versions, which requires a specific directory structure (`~/multiwptest`) to function.

### Building for Release
To package the plugin for distribution (e.g., uploading to a site):
1. Ensure dependencies are installed (`composer install --no-dev`).
2. Zip the plugin directory, excluding development files like `tests/`, `.git/`, `.gitignore`, etc.
   *(Example command used previously)*:
   ```bash
   mkdir -p build/relevanssi && cp -r lib languages stopwords relevanssi.php uninstall.php readme.txt changelog.txt LICENSE relevanssi.po build/relevanssi/ && cd build && zip -r ../relevanssi.zip relevanssi
   ```

## Conventions

- **Code Style:** Strictly follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
- **Naming:** Use snake_case for functions and variables.
- **Architecture:** 
    - Functionality is broken down into files within `lib/` based on domain (indexing, searching, options).
    - Compatibility with other plugins is isolated in `lib/compatibility/`.
    - Admin UI is separated into tabs in `lib/tabs/`.
