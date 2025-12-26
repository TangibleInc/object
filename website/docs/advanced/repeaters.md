---
sidebar_position: 3
title: Repeaters
description: Managing collections of sub-items
---

# Repeater Fields

Repeater fields allow users to manage collections of sub-items within a single entity. Data is stored as JSON.

## Basic Definition

```php
'fields' => [
    'name' => 'string',
    'items' => [
        'type'       => 'repeater',
        'sub_fields' => [
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'quantity', 'type' => 'integer'],
            ['name' => 'active', 'type' => 'boolean'],
        ],
    ],
]
```

## Sub-Field Types

Repeater sub-fields support JSON-compatible primitive types:

| Type | Description |
|------|-------------|
| `string` | Text values |
| `integer` | Numeric values |
| `boolean` | True/false values |

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `sub_fields` | array | (required) | Array of sub-field definitions |
| `layout` | string | `'table'` | Layout style: `'table'` or `'block'` |
| `min_rows` | int | - | Minimum number of rows |
| `max_rows` | int | - | Maximum number of rows |
| `button_label` | string | - | Custom "Add" button text |
| `default` | array | `[]` | Default rows for new items |
| `description` | string | - | Help text for the field |

## Sub-Field Options

Each sub-field accepts:

| Option | Type | Description |
|--------|------|-------------|
| `name` | string | Field identifier (required) |
| `type` | string | Field type (required) |
| `label` | string | Display label |
| `placeholder` | string | Placeholder text |
| `description` | string | Help text |
| `min` | int | Minimum value (for integers) |
| `max` | int | Maximum value (for integers) |

## Complete Example

```php
$view = new DataView([
    'slug'   => 'invoice',
    'label'  => 'Invoice',
    'fields' => [
        'customer_name' => 'string',
        'line_items' => [
            'type'         => 'repeater',
            'label'        => 'Line Items',
            'description'  => 'Add products or services',
            'layout'       => 'table',
            'min_rows'     => 1,
            'max_rows'     => 50,
            'button_label' => 'Add Line Item',
            'sub_fields'   => [
                [
                    'name'        => 'description',
                    'type'        => 'string',
                    'label'       => 'Description',
                    'placeholder' => 'Product or service',
                ],
                [
                    'name'  => 'quantity',
                    'type'  => 'integer',
                    'label' => 'Qty',
                    'min'   => 1,
                ],
                [
                    'name'  => 'unit_price',
                    'type'  => 'integer',
                    'label' => 'Price (cents)',
                    'min'   => 0,
                ],
                [
                    'name'  => 'taxable',
                    'type'  => 'boolean',
                    'label' => 'Tax',
                ],
            ],
            'default' => [
                ['description' => '', 'quantity' => 1, 'unit_price' => 0, 'taxable' => true],
            ],
        ],
    ],
]);
```

## Reading Repeater Data

Repeater data is stored as JSON. Decode it when reading:

```php
$handler = $view->get_handler();
$result = $handler->read($id);
$entity = $result->get_entity();

$line_items = json_decode($entity->get('line_items'), true);

foreach ($line_items as $item) {
    echo $item['description'] . ': ';
    echo $item['quantity'] . ' × ' . $item['unit_price'];
}
```

## Writing Repeater Data

Encode data as JSON when creating or updating:

```php
$handler->create([
    'customer_name' => 'John Doe',
    'line_items' => json_encode([
        ['description' => 'Widget', 'quantity' => 2, 'unit_price' => 1000, 'taxable' => true],
        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 5000, 'taxable' => false],
    ]),
]);
```

## Data Structure

Rows include a `key` property for identification:

```json
[
    {"key": "abc123", "description": "Widget", "quantity": 2, "unit_price": 1000},
    {"key": "def456", "description": "Service", "quantity": 1, "unit_price": 5000}
]
```

The `key` is managed automatically by the renderer.

## Layout Options

### Table Layout

Displays rows as a table with columns:

```php
'layout' => 'table',
```

Best for:
- Many short fields
- Numeric data
- Quick scanning

### Block Layout

Displays each row as a card/block:

```php
'layout' => 'block',
```

Best for:
- Fewer, longer fields
- Text content
- Complex sub-structures

## Renderer Support

| Renderer | Repeater Support |
|----------|------------------|
| HtmlRenderer | Basic table UI |
| TangibleFieldsRenderer | Full support with drag-and-drop |

For the best repeater experience, use TangibleFieldsRenderer:

```php
use Tangible\Renderer\TangibleFieldsRenderer;

$view->set_renderer(new TangibleFieldsRenderer());
```

## Security

The repeater sanitizer:
- Strips nested arrays/objects (only primitives allowed)
- Sanitizes all string values
- Returns `[]` for invalid JSON
- Preserves the `key` field

## Processing Repeater Data

Common patterns for working with repeater data:

### Calculate Totals

```php
$handler->before_create(function($data) {
    $items = json_decode($data['line_items'] ?? '[]', true);
    $total = 0;

    foreach ($items as $item) {
        $total += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
    }

    $data['total'] = $total;
    return $data;
});
```

### Validate Rows

```php
$handler->add_validator('line_items', function($value) {
    $items = json_decode($value, true);

    if (empty($items)) {
        return new ValidationError('At least one line item is required');
    }

    foreach ($items as $i => $item) {
        if (empty($item['description'])) {
            return new ValidationError("Row " . ($i + 1) . ": Description is required");
        }
    }

    return true;
});
```
