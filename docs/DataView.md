# DataView Layer

The DataView layer is a high-level facade that orchestrates all Object toolset components to provide a simple, declarative API for creating WordPress admin interfaces for custom data types.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Field Types](#field-types)
- [Storage Options](#storage-options)
- [Modes](#modes)
- [API Reference](#api-reference)
- [Custom Layouts](#custom-layouts)
- [Custom Renderers](#custom-renderers)
- [URL Generation](#url-generation)
- [Validation](#validation)
- [Lifecycle Hooks](#lifecycle-hooks)
- [Custom Field Types](#custom-field-types)
- [Internationalization (i18n)](#internationalization-i18n)
- [Examples](#examples)

## Overview

Instead of manually wiring together DataSet, EditorLayout, Renderer, and RequestHandler, the DataView layer lets you define everything in a single configuration array:

```php
$view = new DataView([
    'slug'   => 'contact_entry',
    'label'  => 'Contact',
    'fields' => [
        'name'    => 'string',
        'email'   => 'email',
        'message' => 'text',
    ],
    'storage' => 'database',
    'ui' => [
        'menu_page'  => 'contacts',
        'menu_label' => 'Contact Entries',
    ],
]);

$view->register();
```

This single declaration:
- Creates a DataSet with the specified fields
- Sets up the appropriate storage adapter
- Creates a request handler with proper sanitization
- Registers an admin menu page
- Handles all CRUD operations with forms and validation

## Quick Start

### Basic Plural View (Multiple Items)

```php
use Tangible\DataView\DataView;
use Tangible\RequestHandler\Validators;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'book',
        'label'  => 'Book',
        'fields' => [
            'title'     => 'string',
            'author'    => 'string',
            'isbn'      => 'string',
            'published' => 'date',
            'in_stock'  => 'boolean',
        ],
        'storage' => 'database',
        'ui' => [
            'menu_label' => 'Books',
            'icon'       => 'dashicons-book',
        ],
    ]);

    // Add validation
    $view->get_handler()
        ->add_validator('title', Validators::required())
        ->add_validator('isbn', Validators::required());

    $view->register();
});
```

### Basic Singular View (Settings Page)

```php
use Tangible\DataView\DataView;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'my_plugin_settings',
        'label'  => 'Settings',
        'fields' => [
            'api_key'     => 'string',
            'api_url'     => 'url',
            'cache_ttl'   => 'integer',
            'debug_mode'  => 'boolean',
        ],
        'storage' => 'option',
        'mode'    => 'singular',
        'ui' => [
            'menu_label' => 'My Plugin',
            'parent'     => 'options-general.php',
        ],
    ]);

    $view->register();
});
```

## Configuration

### Required Options

| Option | Type | Description |
|--------|------|-------------|
| `slug` | string | Unique identifier. Must be lowercase alphanumeric with underscores, starting with a letter or underscore. |
| `label` | string\|array | Singular label or array with `singular` key. See [Internationalization](#internationalization-i18n) for full options. |
| `fields` | array | Field name to type mapping. See [Field Types](#field-types). |

### Optional Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `storage` | string | `'cpt'` | Storage backend: `'cpt'`, `'database'`, or `'option'`. |
| `mode` | string | `'plural'` | `'plural'` for multiple items, `'singular'` for settings. |
| `capability` | string | `'manage_options'` | Required WordPress capability to access the admin page. |
| `storage_options` | array | `[]` | Additional options passed to storage adapter. |
| `ui` | array | See below | Admin UI configuration. |

### UI Options

```php
'ui' => [
    'menu_page'  => 'my_page',       // Menu page slug (defaults to config slug)
    'menu_label' => 'My Items',      // Menu label (defaults to config label)
    'parent'     => null,            // Parent menu slug (null for top-level)
    'icon'       => 'dashicons-admin-generic', // Menu icon
    'position'   => null,            // Menu position (null for default)
]
```

## Field Types

DataView provides built-in field types that handle:
- DataSet type mapping
- Input sanitization
- Database schema generation
- HTML input type selection

### Built-in Types

| Type | DataSet Type | HTML Input | Sanitizer | DB Schema |
|------|-------------|------------|-----------|-----------|
| `string` | STRING | text | `sanitize_text_field` | VARCHAR(255) |
| `text` | STRING | textarea | `sanitize_textarea_field` | TEXT |
| `email` | STRING | email | `sanitize_email` | VARCHAR(255) |
| `url` | STRING | url | `esc_url_raw` | VARCHAR(512) |
| `integer` | INTEGER | number | `intval` | INT(11) |
| `boolean` | BOOLEAN | checkbox | custom boolean sanitizer | TINYINT(1) |
| `date` | STRING | date | `sanitize_text_field` | DATE |
| `datetime` | STRING | datetime-local | `sanitize_text_field` | DATETIME |

### Boolean Sanitization

The boolean sanitizer accepts various truthy values:
- `true`, `'1'`, `'true'`, `'yes'`, `'on'` => `true`
- `false`, `'0'`, `''`, `'no'`, any other value => `false`

## Storage Options

### Custom Post Type (`'cpt'`)

Uses WordPress custom post types. Best for:
- Integration with existing WordPress workflows
- Content that benefits from post features (revisions, etc.)

```php
'storage' => 'cpt',
```

Note: CPT slugs must be 20 characters or less.

### Database (`'database'`)

Uses the Database Module for custom tables. Best for:
- High-volume data
- Complex queries
- Data that doesn't fit the post model

```php
'storage' => 'database',
'storage_options' => [
    'version' => 1,  // Increment when schema changes
],
```

The schema is auto-generated from field definitions. The `storage_options` are merged with the generated settings, allowing you to override defaults like version number.

### Option (`'option'`)

Uses WordPress options. Best for:
- Singular mode (settings pages)
- Single-instance data

```php
'storage' => 'option',
'mode'    => 'singular',
```

## Modes

### Plural Mode (Default)

For managing multiple items (CRUD operations):

```php
'mode' => 'plural',
```

Provides:
- List view with all items
- Create form
- Edit form
- Delete action

### Singular Mode

For single-instance data like settings:

```php
'mode' => 'singular',
```

Provides:
- Single form for reading/updating settings
- No create/delete operations

## API Reference

### DataView Class

#### Constructor

```php
$view = new DataView(array $config);
```

#### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `register()` | `static` | Registers the admin menu and hooks. Call during `admin_menu` action. |
| `get_handler()` | `PluralHandler\|SingularHandler` | Returns the request handler for adding validators and hooks. |
| `get_object()` | `PluralObject\|SingularObject` | Returns the underlying data object. |
| `get_dataset()` | `DataSet` | Returns the DataSet instance. |
| `get_config()` | `DataViewConfig` | Returns the configuration object. |
| `get_field_registry()` | `FieldTypeRegistry` | Returns the field type registry. |
| `url(string $action, ?int $id)` | `string` | Generates admin URL for an action. |
| `set_layout(callable $callback)` | `static` | Sets a custom layout callback. |
| `set_renderer(Renderer $renderer)` | `static` | Sets a custom renderer. |
| `handle_request()` | `void` | Handles the current admin page request. |

### DataViewConfig Class

Configuration value object with public readonly properties:

| Property | Type | Description |
|----------|------|-------------|
| `$slug` | string | Unique identifier |
| `$label` | string | Singular label |
| `$fields` | array | Field definitions |
| `$storage` | string | Storage type |
| `$mode` | string | Plural or singular |
| `$capability` | string | Required capability |
| `$storage_options` | array | Storage adapter options |
| `$ui` | array | UI configuration |
| `$label` | string | Singular label (for backward compatibility) |
| `$labels` | array | Full labels configuration array |

#### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `is_plural()` | `bool` | True if plural mode |
| `is_singular()` | `bool` | True if singular mode |
| `get_menu_page()` | `string` | Menu page slug |
| `get_menu_label()` | `string` | Menu label |
| `get_parent_menu()` | `?string` | Parent menu or null |
| `get_icon()` | `string` | Menu icon |
| `get_position()` | `?int` | Menu position or null |
| `get_singular_label()` | `string` | Get the singular label |
| `get_plural_label()` | `?string` | Get the plural label (null if not set) |
| `get_label(string $key, ?string $fallback)` | `?string` | Get a specific label with fallback |

### FieldTypeRegistry Class

Manages field type definitions.

#### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `has_type(string $type)` | `bool` | Check if type is registered |
| `get_dataset_type(string $type)` | `string` | Get DataSet type constant |
| `get_sanitizer(string $type)` | `callable` | Get sanitizer function |
| `get_schema(string $type)` | `array` | Get database schema definition |
| `get_input_type(string $type)` | `string` | Get HTML input type |
| `register_type(string $name, array $config)` | `void` | Register custom type |

### UrlBuilder Class

Generates admin URLs.

#### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `url(string $action, ?int $id, array $extra)` | `string` | Generate admin URL |
| `url_with_nonce(string $action, ?int $id, string $nonce_action)` | `string` | URL with nonce |
| `get_current_action()` | `string` | Current action from request |
| `get_current_id()` | `?int` | Current ID from request |
| `get_nonce_action(string $action, ?int $id)` | `string` | Generate nonce action name |

## Custom Layouts

Override the default layout with a custom callback:

```php
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;
use Tangible\EditorLayout\Tabs;
use Tangible\EditorLayout\Tab;

$view->set_layout(function(Layout $layout) {
    $layout->tabs(function(Tabs $tabs) {
        $tabs->tab('General', function(Tab $tab) {
            $tab->field('title')
                ->placeholder('Enter title')
                ->help('The item title');
            $tab->field('description');
        });

        $tabs->tab('Details', function(Tab $tab) {
            $tab->field('price');
            $tab->field('quantity');
        });
    });

    $layout->sidebar(function(Sidebar $sidebar) {
        $sidebar->field('status');
        $sidebar->actions(['save', 'delete']);
    });
});
```

See the [EditorLayout documentation](../readme.md#editorlayout-structure) for full layout options.

## Custom Renderers

Replace the default HTML renderer:

```php
use Tangible\Renderer\Renderer;

class MyCustomRenderer implements Renderer {
    public function render_editor(Layout $layout, array $data = []): string {
        // Custom rendering logic
    }
}

$view->set_renderer(new MyCustomRenderer());
```

## URL Generation

Generate admin URLs for navigation:

```php
$view = new DataView([/* config */]);

// List page
$list_url = $view->url('list');
// => admin.php?page=my_page

// Create page
$create_url = $view->url('create');
// => admin.php?page=my_page&action=create

// Edit page
$edit_url = $view->url('edit', 42);
// => admin.php?page=my_page&action=edit&id=42

// Delete action
$delete_url = $view->url('delete', 42);
// => admin.php?page=my_page&action=delete&id=42
```

## Validation

Add validators through the handler:

```php
use Tangible\RequestHandler\Validators;

$handler = $view->get_handler();

// Built-in validators
$handler
    ->add_validator('title', Validators::required())
    ->add_validator('title', Validators::min_length(3))
    ->add_validator('title', Validators::max_length(200))
    ->add_validator('price', Validators::min(0))
    ->add_validator('price', Validators::max(10000))
    ->add_validator('status', Validators::in(['draft', 'published']))
    ->add_validator('email', Validators::email());

// Custom validator
$handler->add_validator('slug', function($value) {
    if (preg_match('/[^a-z0-9-]/', $value)) {
        return new \Tangible\RequestHandler\ValidationError(
            'Slug can only contain lowercase letters, numbers, and hyphens'
        );
    }
    return true;
});
```

## Lifecycle Hooks

### Plural Mode Hooks

```php
$handler = $view->get_handler();

// Before/after create
$handler->before_create(function(array $data) {
    $data['created_at'] = current_time('mysql');
    return $data;
});

$handler->after_create(function($entity) {
    do_action('my_plugin_item_created', $entity);
});

// Before/after update
$handler->before_update(function($entity, array $data) {
    $data['updated_at'] = current_time('mysql');
    return $data;
});

$handler->after_update(function($entity) {
    // Clear caches, send notifications, etc.
});

// Before/after delete
$handler->before_delete(function($entity) {
    if ($entity->get('is_protected')) {
        return false; // Cancel deletion
    }
    return true;
});

$handler->after_delete(function($id) {
    // Cleanup related data
});
```

### Singular Mode Hooks

```php
$handler = $view->get_handler();

// Before update receives current and new data
$handler->before_update(function(array $current, array $data) {
    if ($current['api_key'] !== $data['api_key']) {
        // API key changed, invalidate tokens
        delete_transient('my_api_token');
    }
    return $data;
});

// After update receives the updated data
$handler->after_update(function(array $data) {
    do_action('my_plugin_settings_updated', $data);
});
```

## Custom Field Types

Register custom field types through the registry:

```php
use Tangible\DataObject\DataSet;

$registry = $view->get_field_registry();

$registry->register_type('phone', [
    'dataset'   => DataSet::TYPE_STRING,
    'sanitizer' => function($value) {
        return preg_replace('/[^0-9+\-\s()]/', '', $value);
    },
    'schema'    => ['type' => 'varchar', 'length' => 20],
    'input'     => 'tel',
]);

$registry->register_type('currency', [
    'dataset'   => DataSet::TYPE_INTEGER,
    'sanitizer' => function($value) {
        return (int) round(floatval($value) * 100); // Store as cents
    },
    'schema'    => ['type' => 'int', 'length' => 11],
    'input'     => 'number',
]);
```

Note: Register custom types before creating the DataView, or use the registry from an existing DataView for subsequent views.

## Internationalization (i18n)

WordPress i18n tools like `wp i18n make-pot` scan source files for literal strings passed to translation functions (`__()`, `_e()`, etc.). To ensure your DataView labels are translatable, pass pre-translated strings in the `label` configuration.

### Basic i18n Usage

Instead of a simple string, pass an array with at least the `singular` key:

```php
$view = new DataView([
    'slug'   => 'book',
    'label'  => [
        'singular' => __('Book', 'my-plugin'),
        'plural'   => __('Books', 'my-plugin'),
    ],
    'fields' => [
        'title' => 'string',
    ],
]);
```

### Available Label Keys

You can override any of these labels for full i18n control:

| Key | Default | Description |
|-----|---------|-------------|
| `singular` | (required) | Singular form (e.g., "Book") |
| `plural` | Auto-generated | Plural form (e.g., "Books") |
| `all_items` | `{plural}` | List page title |
| `add_new` | "Add New" | Add new button text |
| `add_new_item` | "Add New {singular}" | Create page title |
| `edit_item` | "Edit {singular}" | Edit page title |
| `new_item` | "New {singular}" | New item text |
| `view_item` | "View {singular}" | View item text |
| `view_items` | "View {plural}" | View items text |
| `search_items` | "Search {plural}" | Search text |
| `not_found` | "No {plural} found" | Empty list message |
| `not_found_in_trash` | "No {plural} found in Trash" | Empty trash message |
| `settings` | "{singular} Settings" | Settings page title (singular mode) |
| `item_created` | "Item created successfully." | Create success notice |
| `item_updated` | "Item updated successfully." | Update success notice |
| `item_deleted` | "Item deleted successfully." | Delete success notice |
| `settings_saved` | "Settings saved successfully." | Settings save notice (singular mode) |
| `menu_name` | `{plural}` | WordPress menu name |

### Complete i18n Example

```php
use Tangible\DataView\DataView;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'book',
        'label'  => [
            // Required
            'singular'        => __('Book', 'my-plugin'),
            'plural'          => __('Books', 'my-plugin'),

            // Page titles
            'all_items'       => __('All Books', 'my-plugin'),
            'add_new_item'    => __('Add New Book', 'my-plugin'),
            'edit_item'       => __('Edit Book', 'my-plugin'),

            // WordPress CPT labels
            'add_new'         => __('Add New', 'my-plugin'),
            'new_item'        => __('New Book', 'my-plugin'),
            'view_item'       => __('View Book', 'my-plugin'),
            'view_items'      => __('View Books', 'my-plugin'),
            'search_items'    => __('Search Books', 'my-plugin'),
            'not_found'       => __('No books found', 'my-plugin'),
            'not_found_in_trash' => __('No books found in Trash', 'my-plugin'),

            // Success notices
            'item_created'    => __('Book created successfully.', 'my-plugin'),
            'item_updated'    => __('Book updated successfully.', 'my-plugin'),
            'item_deleted'    => __('Book deleted successfully.', 'my-plugin'),

            // Menu
            'menu_name'       => __('Books', 'my-plugin'),
        ],
        'fields' => [
            'title'  => 'string',
            'author' => 'string',
        ],
        'storage' => 'database',
        'ui' => [
            'menu_label' => __('Books', 'my-plugin'),
        ],
    ]);

    $view->register();
});
```

### Settings Page i18n Example

```php
$view = new DataView([
    'slug'   => 'my_plugin_settings',
    'label'  => [
        'singular'       => __('Settings', 'my-plugin'),
        'settings'       => __('Plugin Settings', 'my-plugin'),
        'settings_saved' => __('Settings saved.', 'my-plugin'),
    ],
    'fields' => [
        'api_key' => 'string',
    ],
    'storage' => 'option',
    'mode'    => 'singular',
    'ui' => [
        'menu_label' => __('My Plugin', 'my-plugin'),
        'parent'     => 'options-general.php',
    ],
]);
```

### Minimal i18n Setup

At minimum, provide translated `singular` and `plural` labels. Other labels will be auto-generated from these:

```php
$view = new DataView([
    'slug'   => 'product',
    'label'  => [
        'singular' => __('Product', 'my-plugin'),
        'plural'   => __('Products', 'my-plugin'),
    ],
    'fields' => ['name' => 'string'],
]);
```

This ensures:
- Page titles like "Add New Product" and "Edit Product" are generated correctly
- Success messages use proper grammar
- WordPress translation tools can extract all strings

## Examples

### Complete Blog Post Manager

```php
use Tangible\DataView\DataView;
use Tangible\RequestHandler\Validators;
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;
use Tangible\EditorLayout\Tabs;
use Tangible\EditorLayout\Tab;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'blog_post',
        'label'  => 'Blog Post',
        'fields' => [
            'title'        => 'string',
            'slug'         => 'string',
            'content'      => 'text',
            'excerpt'      => 'text',
            'author_email' => 'email',
            'published_at' => 'datetime',
            'is_featured'  => 'boolean',
            'view_count'   => 'integer',
        ],
        'storage' => 'database',
        'storage_options' => ['version' => 1],
        'ui' => [
            'menu_label' => 'Blog Posts',
            'icon'       => 'dashicons-edit',
            'position'   => 5,
        ],
    ]);

    // Custom layout
    $view->set_layout(function(Layout $layout) {
        $layout->tabs(function(Tabs $tabs) {
            $tabs->tab('Content', function(Tab $tab) {
                $tab->field('title')
                    ->placeholder('Post title')
                    ->help('The main title of the post');
                $tab->field('slug')
                    ->placeholder('post-url-slug')
                    ->help('URL-friendly identifier');
                $tab->field('content')
                    ->help('Main post content');
                $tab->field('excerpt')
                    ->help('Short summary for listings');
            });

            $tabs->tab('Meta', function(Tab $tab) {
                $tab->field('author_email');
                $tab->field('published_at');
                $tab->field('view_count')->readonly();
            });
        });

        $layout->sidebar(function(Sidebar $sidebar) {
            $sidebar->field('is_featured');
            $sidebar->actions(['save', 'delete']);
        });
    });

    // Validation
    $view->get_handler()
        ->add_validator('title', Validators::required())
        ->add_validator('title', Validators::max_length(200))
        ->add_validator('slug', Validators::required())
        ->add_validator('author_email', Validators::email())
        ->add_validator('slug', function($value) {
            if (!preg_match('/^[a-z0-9-]+$/', $value)) {
                return new \Tangible\RequestHandler\ValidationError(
                    'Slug must contain only lowercase letters, numbers, and hyphens'
                );
            }
            return true;
        });

    // Lifecycle hooks
    $view->get_handler()
        ->before_create(function($data) {
            $data['view_count'] = 0;
            if (empty($data['slug'])) {
                $data['slug'] = sanitize_title($data['title']);
            }
            return $data;
        })
        ->after_create(function($entity) {
            // Clear blog listing cache
            delete_transient('blog_posts_list');
        });

    $view->register();
});
```

### Plugin Settings with Multiple Sections

```php
use Tangible\DataView\DataView;
use Tangible\RequestHandler\Validators;
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'my_plugin_settings',
        'label'  => 'Settings',
        'fields' => [
            // API Settings
            'api_key'      => 'string',
            'api_endpoint' => 'url',
            'api_timeout'  => 'integer',
            // Cache Settings
            'cache_enabled' => 'boolean',
            'cache_ttl'     => 'integer',
            // Display Settings
            'items_per_page' => 'integer',
            'date_format'    => 'string',
            // Debug
            'debug_mode'     => 'boolean',
            'log_level'      => 'string',
        ],
        'storage' => 'option',
        'mode'    => 'singular',
        'ui' => [
            'menu_label' => 'My Plugin',
            'parent'     => 'options-general.php',
        ],
    ]);

    $view->set_layout(function(Layout $layout) {
        $layout->section('API Configuration', function(Section $s) {
            $s->field('api_key')
                ->placeholder('Enter your API key')
                ->help('Get your API key from the dashboard');
            $s->field('api_endpoint')
                ->placeholder('https://api.example.com/v1');
            $s->field('api_timeout')
                ->help('Request timeout in seconds');
        });

        $layout->section('Caching', function(Section $s) {
            $s->field('cache_enabled')
                ->help('Enable response caching');
            $s->field('cache_ttl')
                ->help('Cache time-to-live in seconds');
        });

        $layout->section('Display', function(Section $s) {
            $s->field('items_per_page');
            $s->field('date_format')
                ->placeholder('Y-m-d H:i:s');
        });

        $layout->section('Development', function(Section $s) {
            $s->field('debug_mode')
                ->help('Enable detailed logging');
            $s->field('log_level')
                ->help('Logging verbosity: debug, info, warning, error');
        });

        $layout->sidebar(function(Sidebar $sb) {
            $sb->actions(['save']);
        });
    });

    $view->get_handler()
        ->add_validator('api_key', Validators::required())
        ->add_validator('api_timeout', Validators::min(1))
        ->add_validator('api_timeout', Validators::max(60))
        ->add_validator('cache_ttl', Validators::min(0))
        ->add_validator('items_per_page', Validators::min(1))
        ->add_validator('items_per_page', Validators::max(100))
        ->add_validator('log_level', Validators::in(['debug', 'info', 'warning', 'error']))
        ->before_update(function($current, $data) {
            // Clear cache when cache settings change
            if (($current['cache_ttl'] ?? 0) !== ($data['cache_ttl'] ?? 0) ||
                ($current['cache_enabled'] ?? false) !== ($data['cache_enabled'] ?? false)) {
                wp_cache_flush();
            }
            return $data;
        });

    $view->register();
});
```

### Submenu Under Existing Page

```php
$view = new DataView([
    'slug'   => 'product_review',
    'label'  => 'Review',
    'fields' => [
        'product_id' => 'integer',
        'rating'     => 'integer',
        'comment'    => 'text',
        'approved'   => 'boolean',
    ],
    'storage' => 'database',
    'ui' => [
        'menu_label' => 'Reviews',
        'parent'     => 'edit.php?post_type=product', // Under Products CPT
    ],
]);
```

### Programmatic Access Outside Admin

```php
// Create the view (can be done outside admin context)
$view = new DataView([
    'slug'   => 'subscriber',
    'label'  => 'Subscriber',
    'fields' => [
        'email'        => 'email',
        'subscribed'   => 'boolean',
        'subscribed_at' => 'datetime',
    ],
    'storage' => 'database',
]);

// Use the handler programmatically
$handler = $view->get_handler();

// Create a new subscriber
$result = $handler->create([
    'email'         => 'user@example.com',
    'subscribed'    => true,
    'subscribed_at' => current_time('mysql'),
]);

if ($result->is_success()) {
    $subscriber = $result->get_entity();
    $id = $subscriber->get_id();
}

// List all subscribers
$result = $handler->list();
foreach ($result->get_entities() as $entity) {
    echo $entity->get('email') . "\n";
}

// Update a subscriber
$handler->update($id, ['subscribed' => false]);

// Delete a subscriber
$handler->delete($id);
```
