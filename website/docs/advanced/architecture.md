---
sidebar_position: 1
title: Architecture
description: Understanding the four-layer architecture
---

# Four-Layer Architecture

Tangible Object separates concerns into four distinct layers. While DataView handles all of this for you, understanding the layers helps with advanced customization.

## Layer Overview

```
┌─────────────────────────────────────────────┐
│                  DataView                    │
│         (High-level orchestration)           │
└─────────────────────────────────────────────┘
                      │
    ┌─────────────────┼─────────────────┐
    ▼                 ▼                 ▼
┌─────────┐    ┌─────────────┐    ┌──────────┐
│ DataSet │    │EditorLayout │    │ Renderer │
└─────────┘    └─────────────┘    └──────────┘
    │                 │                 │
    └────────────┬────┘                 │
                 ▼                      │
         ┌──────────────┐               │
         │RequestHandler│◄──────────────┘
         └──────────────┘
```

## Layer 1: DataSet

Defines field types and handles type coercion.

```php
use Tangible\DataObject\DataSet;

$dataset = new DataSet();
$dataset
    ->add_string('title')
    ->add_string('email')
    ->add_integer('count')
    ->add_boolean('is_active');
```

### Type Coercion

DataSet automatically coerces values:

```php
$dataset->coerce([
    'count' => '42',        // String → Integer: 42
    'is_active' => 'yes',   // String → Boolean: true
]);
```

### Available Types

- `add_string($name)` - Text values
- `add_integer($name)` - Whole numbers
- `add_boolean($name)` - True/false

## Layer 2: EditorLayout

Composes the editor structure with sections, tabs, and fields.

```php
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Tabs;
use Tangible\EditorLayout\Tab;
use Tangible\EditorLayout\Sidebar;

$layout = new Layout($dataset);

$layout->section('Details', function(Section $s) {
    $s->field('title')
      ->placeholder('Enter title')
      ->help('The main title');
    $s->field('count');
});

$layout->tabs(function(Tabs $tabs) {
    $tabs->tab('Settings', function(Tab $t) {
        $t->field('is_active');
    });
});

$layout->sidebar(function(Sidebar $sb) {
    $sb->actions(['save', 'delete']);
});
```

## Layer 3: Renderer

Generates HTML from the layout structure.

```php
use Tangible\Renderer\HtmlRenderer;

$renderer = new HtmlRenderer();
$html = $renderer->render_editor($layout, [
    'title' => 'My Item',
    'count' => 5,
]);
```

## Layer 4: RequestHandler

Handles CRUD operations with validation.

### PluralObject & PluralHandler

For multiple items (like posts):

```php
use Tangible\DataObject\PluralObject;
use Tangible\RequestHandler\PluralHandler;
use Tangible\RequestHandler\Validators;

$object = new PluralObject('my_item');
$object->set_dataset($dataset);
$object->register([
    'public' => false,
    'label' => 'My Items',
]);

$handler = new PluralHandler($object);
$handler
    ->add_validator('title', Validators::required())
    ->before_create(function($data) {
        $data['created_at'] = current_time('mysql');
        return $data;
    });

// CRUD operations
$result = $handler->create(['title' => 'New Item', 'count' => 1]);
$result = $handler->read($id);
$result = $handler->update($id, ['count' => 2]);
$result = $handler->delete($id);
$result = $handler->list();
```

### SingularObject & SingularHandler

For single-instance data (like settings):

```php
use Tangible\DataObject\SingularObject;
use Tangible\RequestHandler\SingularHandler;

$object = new SingularObject('my_settings');
$object->set_dataset($dataset);

$handler = new SingularHandler($object);

// Only read and update
$result = $handler->read();
$result = $handler->update(['title' => 'New Value']);
```

## Using Layers Directly

For maximum control, use the layers directly instead of DataView:

```php
// 1. Define data
$dataset = new DataSet();
$dataset->add_string('name')->add_string('email');

// 2. Create layout
$layout = new Layout($dataset);
$layout->section('Contact', function(Section $s) {
    $s->field('name');
    $s->field('email');
});

// 3. Set up handler
$object = new PluralObject('contact');
$object->set_dataset($dataset);
$object->register();

$handler = new PluralHandler($object);
$handler->add_validator('email', Validators::email());

// 4. Create renderer
$renderer = new HtmlRenderer();

// 5. Build admin page manually
add_action('admin_menu', function() use ($layout, $handler, $renderer) {
    add_menu_page(
        'Contacts',
        'Contacts',
        'manage_options',
        'contacts',
        function() use ($layout, $handler, $renderer) {
            // Handle requests and render
        }
    );
});
```

## When to Use Layers Directly

Use the layers directly when you need:
- Custom admin page structure
- Non-standard workflows
- Integration with existing systems
- Maximum flexibility

For most cases, DataView provides everything you need with less code.
