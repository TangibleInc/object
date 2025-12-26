---
sidebar_position: 3
title: TangibleFieldsRenderer
description: Rich form components with Tangible Fields
---

# TangibleFieldsRenderer

TangibleFieldsRenderer uses the Tangible Fields framework to provide rich, React-powered form components.

## Requirements

- Tangible Fields framework must be installed
- The `tangible_fields()` function must be available

## Usage

```php
use Tangible\Renderer\TangibleFieldsRenderer;

$view = new DataView([
    'slug'   => 'product',
    'label'  => 'Product',
    'fields' => [
        'name'        => 'string',
        'price'       => 'integer',
        'in_stock'    => 'boolean',
        'description' => 'text',
    ],
]);

$view->set_renderer(new TangibleFieldsRenderer());
$view->register();
```

## Field Type Mapping

TangibleFieldsRenderer maps DataView types to Tangible Fields types:

| DataView Type | Tangible Fields Type |
|---------------|---------------------|
| `string` | `text` |
| `text` | `textarea` |
| `email` | `text` |
| `url` | `text` |
| `integer` | `number` |
| `boolean` | `switch` |
| `date` | `date_picker` |
| `datetime` | `date_picker` |
| `repeater` | `repeater` |

## Enhanced Components

### Switches for Booleans

Boolean fields render as toggle switches instead of checkboxes:

```php
'fields' => [
    'is_active' => 'boolean',  // Renders as a toggle switch
]
```

### Date Pickers

Date and datetime fields use enhanced date pickers:

```php
'fields' => [
    'publish_date' => 'date',      // Date picker
    'event_time'   => 'datetime',  // Date and time picker
]
```

### Number Inputs

Integer fields can include min/max constraints:

```php
'fields' => [
    'quantity' => [
        'type' => 'integer',
        'min'  => 1,
        'max'  => 100,
    ],
]
```

## Repeater Support

TangibleFieldsRenderer fully supports repeater fields with drag-and-drop reordering:

```php
$view = new DataView([
    'slug'   => 'order',
    'label'  => 'Order',
    'fields' => [
        'customer' => 'string',
        'items' => [
            'type'       => 'repeater',
            'layout'     => 'table',
            'sub_fields' => [
                ['name' => 'product', 'type' => 'string'],
                ['name' => 'quantity', 'type' => 'integer', 'min' => 1],
                ['name' => 'price', 'type' => 'integer'],
            ],
        ],
    ],
]);

$view->set_renderer(new TangibleFieldsRenderer());
```

See [Repeaters](/advanced/repeaters) for full repeater documentation.

## Field Configuration

Pass additional options to Tangible Fields:

```php
'fields' => [
    'content' => [
        'type' => 'text',
        'rows' => 10,  // Textarea rows
    ],
    'published' => [
        'type'        => 'date',
        'future_only' => true,  // Only future dates
    ],
]
```

## Asset Handling

TangibleFieldsRenderer automatically enqueues required assets in the admin footer. No manual script loading is needed.

## Advantages

- **Rich UI** - Enhanced form components
- **Full repeater support** - Drag-and-drop, add/remove rows
- **Modern experience** - React-powered interactivity
- **Consistent styling** - Themed components

## Limitations

- **Additional dependency** - Requires Tangible Fields
- **Larger footprint** - React and component JavaScript
- **Learning curve** - More complex for customization

## When to Use

Use TangibleFieldsRenderer when:
- You need repeater fields
- You want enhanced date pickers and toggles
- User experience is a priority
- You're already using Tangible Fields elsewhere
