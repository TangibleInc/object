---
sidebar_position: 3
title: Field Types
description: Available field types and their configuration
---

# Field Types

DataView provides built-in field types that handle data type mapping, input sanitization, and HTML input rendering.

## Built-in Types

| Type | HTML Input | Sanitizer | Description |
|------|------------|-----------|-------------|
| `string` | text | `sanitize_text_field` | Single-line text |
| `text` | textarea | `sanitize_textarea_field` | Multi-line text |
| `email` | email | `sanitize_email` | Email address |
| `url` | url | `esc_url_raw` | URL |
| `integer` | number | `intval` | Whole numbers |
| `boolean` | checkbox | custom | True/false values |
| `date` | date | `sanitize_text_field` | Date (YYYY-MM-DD) |
| `datetime` | datetime-local | `sanitize_text_field` | Date and time |
| `repeater` | repeater | JSON sanitizer | Collection of sub-items |

## Defining Fields

Fields can be defined in two formats:

### Simple Format

Use a string for the type:

```php
'fields' => [
    'name'  => 'string',
    'email' => 'email',
    'count' => 'integer',
    'notes' => 'text',
]
```

### Complex Format

Use an array with a `type` key for additional configuration:

```php
'fields' => [
    'name' => [
        'type'        => 'string',
        'label'       => 'Full Name',
        'placeholder' => 'Enter your name',
        'description' => 'Your legal name',
    ],
    'quantity' => [
        'type' => 'integer',
        'min'  => 1,
        'max'  => 100,
    ],
]
```

The complex format is required for repeater fields and allows additional configuration for any type.

## Field Configuration Options

Common options available in complex format:

| Option | Type | Description |
|--------|------|-------------|
| `type` | string | The field type (required) |
| `label` | string | Display label (auto-generated from name if not set) |
| `placeholder` | string | Placeholder text for input |
| `description` | string | Help text shown below the field |

Type-specific options:

| Type | Option | Description |
|------|--------|-------------|
| `integer` | `min` | Minimum value |
| `integer` | `max` | Maximum value |
| `text` | `rows` | Number of textarea rows |

## Boolean Sanitization

The boolean sanitizer accepts various truthy values:

- `true`, `'1'`, `'true'`, `'yes'`, `'on'` → `true`
- `false`, `'0'`, `''`, `'no'`, any other value → `false`

## Type Coercion

DataView automatically coerces values based on field type:

```php
// Integer field with string input
$handler->create(['count' => '5']); // Stored as integer 5

// Boolean field with various inputs
$handler->create(['active' => 'yes']); // Stored as true
$handler->create(['active' => '0']);   // Stored as false
```

## Custom Field Types

You can register custom field types. See [Custom Field Types](/advanced/custom-field-types) for details.

```php
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
