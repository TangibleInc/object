---
sidebar_position: 2
title: Configuration
description: DataView configuration options reference
---

# Configuration

This page documents all configuration options available when creating a DataView.

## Required Options

| Option | Type | Description |
|--------|------|-------------|
| `slug` | string | Unique identifier. Must be lowercase alphanumeric with underscores, starting with a letter or underscore. For CPT storage, must be 20 characters or less. |
| `label` | string\|array | Singular label or array with label configuration. See [Internationalization](/advanced/i18n). |
| `fields` | array | Field name to type mapping. See [Field Types](/dataview/field-types). |

## Optional Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `storage` | string | `'cpt'` | Storage backend: `'cpt'`, `'database'`, or `'option'`. |
| `mode` | string | `'plural'` | `'plural'` for multiple items, `'singular'` for settings. |
| `capability` | string | `'manage_options'` | Required WordPress capability to access the admin page. |
| `storage_options` | array | `[]` | Additional options passed to storage adapter. |
| `ui` | array | See below | Admin UI configuration. |

## UI Options

```php
'ui' => [
    'menu_page'  => 'my_page',       // Menu page slug (defaults to config slug)
    'menu_label' => 'My Items',      // Menu label (defaults to config label)
    'parent'     => null,            // Parent menu slug (null for top-level)
    'icon'       => 'dashicons-admin-generic', // Menu icon
    'position'   => null,            // Menu position (null for default)
]
```

### Parent Menu Options

To add your page as a submenu:

```php
// Under Settings
'parent' => 'options-general.php'

// Under Tools
'parent' => 'tools.php'

// Under a custom post type
'parent' => 'edit.php?post_type=product'

// Under another plugin's menu
'parent' => 'my-plugin-slug'
```

## Complete Example

```php
use Tangible\DataView\DataView;

$view = new DataView([
    // Required
    'slug'   => 'customer',
    'label'  => [
        'singular' => 'Customer',
        'plural'   => 'Customers',
    ],
    'fields' => [
        'name'       => 'string',
        'email'      => 'email',
        'company'    => 'string',
        'notes'      => 'text',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
    ],

    // Optional
    'storage'    => 'cpt',
    'mode'       => 'plural',
    'capability' => 'manage_options',

    'storage_options' => [
        // CPT-specific options
        'public' => false,
    ],

    'ui' => [
        'menu_page'  => 'customers',
        'menu_label' => 'Customers',
        'parent'     => null,
        'icon'       => 'dashicons-groups',
        'position'   => 30,
    ],
]);
```

## Configuration Object

After creating a DataView, you can access the parsed configuration:

```php
$config = $view->get_config();

// Access properties
$config->slug;           // 'customer'
$config->label;          // 'Customer'
$config->fields;         // ['name' => 'string', ...]
$config->storage;        // 'cpt'
$config->mode;           // 'plural'
$config->capability;     // 'manage_options'

// Helper methods
$config->is_plural();    // true
$config->is_singular();  // false
$config->get_menu_page();   // 'customers'
$config->get_menu_label();  // 'Customers'
$config->get_parent_menu(); // null
$config->get_icon();        // 'dashicons-groups'
$config->get_position();    // 30
```
