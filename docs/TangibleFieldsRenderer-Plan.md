# TangibleFieldsRenderer Implementation Plan

This document outlines the implementation plan for integrating Tangible Fields as a renderer for the DataView layer.

## Overview

The TangibleFieldsRenderer will implement the `Renderer` interface and use the Tangible Fields framework for rendering form fields, providing a rich UI experience with features like repeaters, date pickers, and color pickers.

## Scope

### In Scope
- Field type mapping (DataView → Tangible Fields)
- Repeater fields with JSON blob storage
- Layout structure rendering (sections, tabs, sidebar)
- Proper asset enqueueing
- Sub-field types: `string`, `integer`, `boolean` (and `null` for empty values)

### Out of Scope (Future)
- Nested repeaters (architecture allows, not implemented)
- Advanced sub-field validation helpers
- All Tangible Fields types (only mapping common ones)

## Architecture

### Data Flow

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   DataView      │────▶│ TangibleFields   │────▶│ Tangible Fields │
│   Config        │     │ Renderer         │     │ Framework       │
└─────────────────┘     └──────────────────┘     └─────────────────┘
        │                        │                        │
        │ fields config          │ render_field()         │ React UI
        │ + repeater defs        │ + type mapping         │
        ▼                        ▼                        ▼
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Database      │◀────│ RequestRouter    │◀────│ Form POST       │
│   (JSON blob)   │     │ (JSON parsing)   │     │ (hidden input)  │
└─────────────────┘     └──────────────────┘     └─────────────────┘
```

### Type Mapping

| DataView Type | Tangible Fields Type | Notes |
|---------------|---------------------|-------|
| `string` | `text` | Standard text input |
| `text` | `textarea` | Multi-line text |
| `email` | `text` | Text with email styling |
| `url` | `text` | Text input |
| `integer` | `number` | Number input with min/max |
| `boolean` | `switch` | Toggle switch |
| `date` | `date_picker` | Date picker |
| `datetime` | `date_picker` | Date picker (time TBD) |
| `repeater` | `repeater` | JSON blob storage |

### Repeater Sub-field Types

Limited to JSON-compatible primitives:
- `string` → Tangible Fields `text`
- `integer` → Tangible Fields `number`
- `boolean` → Tangible Fields `switch`

## Files to Create/Modify

### New Files

#### 1. `src/Renderer/TangibleFieldsRenderer.php`

```php
<?php declare(strict_types=1);

namespace Tangible\Renderer;

use Tangible\DataObject\DataSet;
use Tangible\EditorLayout\Layout;

class TangibleFieldsRenderer implements Renderer {

    protected Layout $layout;
    protected array $data;
    protected array $field_configs;
    protected bool $enqueued = false;

    /**
     * Type mapping: DataView type => Tangible Fields type
     */
    protected array $type_map = [
        'string'   => 'text',
        'text'     => 'textarea',
        'email'    => 'text',
        'url'      => 'text',
        'integer'  => 'number',
        'boolean'  => 'switch',
        'date'     => 'date_picker',
        'datetime' => 'date_picker',
        'repeater' => 'repeater',
    ];

    /**
     * Set field configurations (including repeater sub-fields).
     */
    public function set_field_configs(array $configs): void;

    /**
     * Render editor form.
     */
    public function render_editor(Layout $layout, array $data): string;

    /**
     * Render list view.
     */
    public function render_list(DataSet $dataset, array $entities): string;

    /**
     * Enqueue Tangible Fields assets.
     */
    public function enqueue_assets(): void;

    // Protected methods:
    protected function render_field(array $field): string;
    protected function render_repeater_field(array $field, mixed $value): string;
    protected function map_sub_fields(array $sub_fields): array;
    protected function render_section(array $section): string;
    protected function render_tabs(array $tabs): string;
    protected function render_sidebar(array $sidebar): string;
    protected function get_tangible_fields_type(string $dataview_type): string;
}
```

### Modified Files

#### 2. `src/DataView/FieldTypeRegistry.php`

Add `repeater` type and `tangible_fields_type` mapping:

```php
protected function register_default_types(): void {
    $this->types = [
        'string' => [
            'dataset'              => DataSet::TYPE_STRING,
            'sanitizer'            => 'sanitize_text_field',
            'schema'               => ['type' => 'varchar', 'length' => 255],
            'input'                => 'text',
            'tangible_fields_type' => 'text',
        ],
        // ... other types with tangible_fields_type added ...

        'repeater' => [
            'dataset'              => DataSet::TYPE_STRING,
            'sanitizer'            => [$this, 'sanitize_repeater'],
            'schema'               => ['type' => 'longtext'],
            'input'                => 'repeater',
            'tangible_fields_type' => 'repeater',
        ],
    ];
}

/**
 * Sanitize repeater JSON value.
 */
public function sanitize_repeater(mixed $value): string {
    if (is_string($value)) {
        $decoded = json_decode(stripslashes($value), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Recursively sanitize values
            return json_encode($this->sanitize_repeater_rows($decoded));
        }
    }
    if (is_array($value)) {
        return json_encode($this->sanitize_repeater_rows($value));
    }
    return '[]';
}

/**
 * Sanitize repeater row values.
 */
protected function sanitize_repeater_rows(array $rows): array {
    return array_map(function($row) {
        if (!is_array($row)) return [];
        return array_map(function($value) {
            if (is_string($value)) return sanitize_text_field($value);
            if (is_int($value) || is_float($value)) return $value;
            if (is_bool($value)) return $value;
            if (is_null($value)) return null;
            return null;
        }, $row);
    }, $rows);
}

/**
 * Get Tangible Fields type for a field type.
 */
public function get_tangible_fields_type(string $type): string {
    $this->validate_type($type);
    return $this->types[$type]['tangible_fields_type'] ?? 'text';
}
```

#### 3. `src/DataView/DataViewConfig.php`

Support array field definitions for repeaters:

```php
/**
 * Parsed field configurations (for repeaters with sub-fields).
 */
public readonly array $field_configs;

// In constructor, after setting $this->fields:
$this->field_configs = $this->parse_field_configs($config['fields']);

/**
 * Parse field configurations, extracting repeater configs.
 */
protected function parse_field_configs(array $fields): array {
    $configs = [];
    foreach ($fields as $name => $definition) {
        if (is_array($definition)) {
            // Complex field (repeater)
            $configs[$name] = $definition;
        } else {
            // Simple field (just type string)
            $configs[$name] = ['type' => $definition];
        }
    }
    return $configs;
}

/**
 * Get normalized fields array (name => type string).
 * For backward compatibility and schema generation.
 */
public function get_normalized_fields(): array {
    $normalized = [];
    foreach ($this->field_configs as $name => $config) {
        $normalized[$name] = $config['type'] ?? 'string';
    }
    return $normalized;
}
```

#### 4. `src/DataView/DataView.php`

Pass field configs to renderer:

```php
// In constructor, after building router:
if ($this->renderer instanceof TangibleFieldsRenderer) {
    $this->renderer->set_field_configs($this->config->field_configs);
}
```

Update `build_dataset()` to use normalized fields:

```php
protected function build_dataset(): void {
    $this->dataset = new DataSet();

    $fields = $this->config->get_normalized_fields();
    foreach ($fields as $name => $type) {
        // ... existing logic ...
    }
}
```

#### 5. `src/DataView/RequestRouter.php`

Update `extract_post_data()` to handle repeaters:

```php
protected function extract_post_data(): array {
    $data = [];

    foreach ($this->config->field_configs as $name => $config) {
        $type = $config['type'] ?? 'string';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST[$name])) {
            // Handle missing boolean fields (unchecked checkboxes)
            if ($this->registry->get_dataset_type($type) === DataSet::TYPE_BOOLEAN) {
                $data[$name] = false;
            }
            // Handle missing repeater fields
            if ($type === 'repeater') {
                $data[$name] = '[]';
            }
            continue;
        }

        $sanitizer = $this->registry->get_sanitizer($type);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $data[$name] = $sanitizer($_POST[$name]);
    }

    return $data;
}
```

#### 6. `src/DataView/SchemaGenerator.php`

Already handles this via registry - `longtext` schema for repeater.

## Usage Example

```php
use Tangible\DataView\DataView;
use Tangible\Renderer\TangibleFieldsRenderer;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug' => 'invoice',
        'label' => 'Invoice',
        'fields' => [
            // Simple fields
            'customer_name' => 'string',
            'invoice_date' => 'date',
            'is_paid' => 'boolean',

            // Repeater field
            'line_items' => [
                'type' => 'repeater',
                'label' => 'Line Items',
                'layout' => 'table',
                'sub_fields' => [
                    [
                        'name' => 'description',
                        'type' => 'string',
                        'label' => 'Description',
                    ],
                    [
                        'name' => 'quantity',
                        'type' => 'integer',
                        'label' => 'Qty',
                    ],
                    [
                        'name' => 'unit_price',
                        'type' => 'integer',
                        'label' => 'Unit Price',
                    ],
                ],
                'default' => [
                    ['description' => '', 'quantity' => 1, 'unit_price' => 0],
                ],
            ],
        ],
        'storage' => 'database',
    ]);

    // Use Tangible Fields renderer
    $view->set_renderer(new TangibleFieldsRenderer());

    // Add repeater validation
    $view->get_handler()->add_validator('line_items', function($value) {
        $items = json_decode($value, true);
        if (!is_array($items)) {
            return new \Tangible\RequestHandler\ValidationError(
                'line_items',
                'Invalid line items format'
            );
        }
        if (empty($items)) {
            return new \Tangible\RequestHandler\ValidationError(
                'line_items',
                'At least one line item is required'
            );
        }
        foreach ($items as $i => $item) {
            $row = $i + 1;
            if (empty($item['description'])) {
                return new \Tangible\RequestHandler\ValidationError(
                    'line_items',
                    "Row {$row}: Description is required"
                );
            }
            if (($item['quantity'] ?? 0) < 1) {
                return new \Tangible\RequestHandler\ValidationError(
                    'line_items',
                    "Row {$row}: Quantity must be at least 1"
                );
            }
        }
        return true;
    });

    $view->register();
});
```

## Repeater Validation Helpers (Future Enhancement)

For easier validation, we could add helpers:

```php
use Tangible\RequestHandler\Validators;

// Future API possibility:
$view->get_handler()->add_validator(
    'line_items',
    Validators::repeater([
        'min_rows' => 1,
        'max_rows' => 50,
        'sub_fields' => [
            'description' => Validators::required(),
            'quantity' => [Validators::required(), Validators::min(1)],
            'unit_price' => Validators::min(0),
        ],
    ])
);
```

## Layout Mapping

### Sections → Tangible Fields Accordion/FieldGroup

```php
// DataView Layout
$layout->section('Details', function(Section $s) {
    $s->field('name');
    $s->field('email');
});

// Rendered as Tangible Fields
$fields->render_field('details_section', [
    'type' => 'accordion',
    'label' => 'Details',
    'fields' => [
        ['type' => 'text', 'name' => 'name', ...],
        ['type' => 'text', 'name' => 'email', ...],
    ],
]);
```

### Tabs → Tangible Fields Tab

```php
// DataView Layout
$layout->tabs(function(Tabs $tabs) {
    $tabs->tab('General', function(Tab $t) { ... });
    $tabs->tab('Advanced', function(Tab $t) { ... });
});

// Rendered as Tangible Fields
$fields->render_field('editor_tabs', [
    'type' => 'tab',
    'tabs' => [
        'general' => ['label' => 'General', 'fields' => [...]],
        'advanced' => ['label' => 'Advanced', 'fields' => [...]],
    ],
]);
```

## Asset Enqueueing

The renderer must enqueue Tangible Fields assets:

```php
public function enqueue_assets(): void {
    if ($this->enqueued) return;

    $fields = tangible_fields();
    $fields->enqueue();

    $this->enqueued = true;
}
```

This should be called in `RequestRouter` after rendering, or hooked to `admin_footer`.

## Testing Considerations

1. **Unit Tests**: Field type mapping, sanitization
2. **Integration Tests**: Full save/load cycle with repeaters
3. **Manual Testing**: UI rendering, repeater add/remove/reorder

## Migration Path

Existing DataView users can migrate gradually:
1. Keep using `HtmlRenderer` (default)
2. Opt-in to `TangibleFieldsRenderer` per view
3. Add repeater fields as needed

## Dependencies

- Tangible Fields framework must be loaded (`tangible_fields()` available)
- TangibleFieldsRenderer should check and throw if framework not available
