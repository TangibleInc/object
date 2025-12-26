---
sidebar_position: 2
title: Custom Field Types
description: Registering custom field types
---

# Custom Field Types

You can register custom field types to extend DataView's capabilities.

## Registration

Access the field registry and register your type:

```php
use Tangible\DataObject\DataSet;

$view = new DataView([...]);
$registry = $view->get_field_registry();

$registry->register_type('phone', [
    'dataset'   => DataSet::TYPE_STRING,
    'sanitizer' => function($value) {
        return preg_replace('/[^0-9+\-\s()]/', '', $value);
    },
    'schema'    => ['type' => 'varchar', 'length' => 20],
    'input'     => 'tel',
]);
```

## Registration Options

| Option | Type | Description |
|--------|------|-------------|
| `dataset` | string | DataSet type constant (`TYPE_STRING`, `TYPE_INTEGER`, `TYPE_BOOLEAN`) |
| `sanitizer` | callable | Function to sanitize input values |
| `schema` | array | Database column definition (for `'storage' => 'database'`) |
| `input` | string | HTML input type |

## Example: Currency Field

Store prices as cents (integers), but allow decimal input:

```php
$registry->register_type('currency', [
    'dataset'   => DataSet::TYPE_INTEGER,
    'sanitizer' => function($value) {
        // Convert dollars to cents
        $float = floatval(str_replace(['$', ','], '', $value));
        return (int) round($float * 100);
    },
    'schema'    => ['type' => 'int', 'length' => 11],
    'input'     => 'text',
]);

// Usage
$view = new DataView([
    'slug'   => 'product',
    'label'  => 'Product',
    'fields' => [
        'name'  => 'string',
        'price' => 'currency',  // Custom type
    ],
]);
```

## Example: Slug Field

Auto-sanitize to URL-friendly format:

```php
$registry->register_type('slug', [
    'dataset'   => DataSet::TYPE_STRING,
    'sanitizer' => function($value) {
        return sanitize_title($value);
    },
    'schema'    => ['type' => 'varchar', 'length' => 200],
    'input'     => 'text',
]);
```

## Example: JSON Field

Store structured data as JSON:

```php
$registry->register_type('json', [
    'dataset'   => DataSet::TYPE_STRING,
    'sanitizer' => function($value) {
        if (is_string($value)) {
            // Validate JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
            return '{}';
        }
        return json_encode($value);
    },
    'schema'    => ['type' => 'longtext'],
    'input'     => 'textarea',
]);
```

## Example: Color Picker

```php
$registry->register_type('color', [
    'dataset'   => DataSet::TYPE_STRING,
    'sanitizer' => function($value) {
        // Validate hex color
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return $value;
        }
        return '#000000';
    },
    'schema'    => ['type' => 'varchar', 'length' => 7],
    'input'     => 'color',
]);
```

## Registration Timing

Register custom types **before** creating the DataView that uses them:

```php
// Get a registry instance
$registry = new \Tangible\DataView\FieldTypeRegistry();

// Register custom types
$registry->register_type('phone', [...]);
$registry->register_type('currency', [...]);

// Create DataView (it will use the same registry)
$view = new DataView([
    'slug'   => 'contact',
    'label'  => 'Contact',
    'fields' => [
        'name'   => 'string',
        'phone'  => 'phone',     // Custom type
        'budget' => 'currency',  // Custom type
    ],
]);
```

## Sharing Types Across Views

For multiple DataViews sharing custom types:

```php
// Create and configure a shared registry
$registry = new \Tangible\DataView\FieldTypeRegistry();
$registry->register_type('phone', [...]);

// Use it for multiple views
$view1 = new DataView(['fields' => ['phone' => 'phone'], ...]);
$view2 = new DataView(['fields' => ['mobile' => 'phone'], ...]);
```

## Database Schema Options

For `'storage' => 'database'`, the schema defines the column:

```php
'schema' => [
    'type'   => 'varchar',  // Column type
    'length' => 255,        // Column length
]

// Common types:
// varchar(length)
// int(length)
// tinyint(1) - for booleans
// text
// longtext
// date
// datetime
```
