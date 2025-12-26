---
sidebar_position: 4
title: Storage
description: Storage backends for DataView
---

# Storage Options

DataView supports multiple storage backends to suit different use cases.

## Custom Post Type (Default)

```php
'storage' => 'cpt',
```

Uses WordPress Custom Post Types. Best for:
- Integration with existing WordPress workflows
- Content that benefits from post features (revisions, author, etc.)
- Compatibility with WordPress admin features

**Limitations:**
- CPT slugs must be 20 characters or less
- Uses post meta for field storage

**Storage options:**

```php
'storage_options' => [
    'public'       => false,        // Hide from frontend
    'show_in_rest' => true,         // Enable REST API
    'supports'     => ['title'],    // Post type supports
],
```

## Database

```php
'storage' => 'database',
```

Uses custom database tables via the Database Module. Best for:
- High-volume data
- Complex queries
- Data that doesn't fit the post model
- Better performance for large datasets

**Storage options:**

```php
'storage_options' => [
    'version' => 1,  // Increment when schema changes
],
```

The database schema is auto-generated from field definitions:

| Field Type | Database Column |
|------------|-----------------|
| `string` | VARCHAR(255) |
| `text` | TEXT |
| `email` | VARCHAR(255) |
| `url` | VARCHAR(512) |
| `integer` | INT(11) |
| `boolean` | TINYINT(1) |
| `date` | DATE |
| `datetime` | DATETIME |
| `repeater` | LONGTEXT |

## Option

```php
'storage' => 'option',
```

Uses WordPress options. Best for:
- Singular mode (settings pages)
- Single-instance data
- Simple key-value storage

**Note:** This storage type is typically used with `'mode' => 'singular'`.

```php
$view = new DataView([
    'slug'    => 'my_plugin_settings',
    'label'   => 'Settings',
    'storage' => 'option',
    'mode'    => 'singular',
    'fields'  => [
        'api_key'    => 'string',
        'debug_mode' => 'boolean',
    ],
]);
```

## Choosing a Storage Backend

| Use Case | Recommended Storage |
|----------|---------------------|
| Plugin settings | `option` + `singular` mode |
| Content-like data (posts, pages) | `cpt` |
| High-volume transactional data | `database` |
| Data needing WordPress features | `cpt` |
| Custom queries and joins | `database` |
| Simple CRUD with few records | `cpt` |

## Example: Settings Page

```php
$view = new DataView([
    'slug'    => 'my_plugin_settings',
    'label'   => 'Settings',
    'storage' => 'option',
    'mode'    => 'singular',
    'fields'  => [
        'api_key'     => 'string',
        'api_url'     => 'url',
        'cache_ttl'   => 'integer',
        'debug_mode'  => 'boolean',
    ],
    'ui' => [
        'menu_label' => 'My Plugin',
        'parent'     => 'options-general.php',
    ],
]);
```

## Example: High-Volume Data

```php
$view = new DataView([
    'slug'    => 'analytics_event',
    'label'   => 'Event',
    'storage' => 'database',
    'storage_options' => [
        'version' => 1,
    ],
    'fields'  => [
        'event_type' => 'string',
        'user_id'    => 'integer',
        'data'       => 'text',
        'created_at' => 'datetime',
    ],
]);
```
