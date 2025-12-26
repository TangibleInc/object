---
sidebar_position: 1
title: Overview
description: Introduction to EditorLayout for structuring forms
---

# Layouts Overview

The EditorLayout system lets you structure your forms with sections, tabs, sidebars, and nested layouts.

## Default Layout

By default, DataView creates a simple layout with all fields in a single section:

```php
$view = new DataView([
    'slug'   => 'product',
    'label'  => 'Product',
    'fields' => [
        'title'       => 'string',
        'description' => 'text',
        'price'       => 'integer',
        'in_stock'    => 'boolean',
    ],
]);
```

This renders all fields in order with auto-generated labels.

## Custom Layouts

Use `set_layout()` to define a custom structure:

```php
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;

$view->set_layout(function(Layout $layout) {
    $layout->section('Product Details', function(Section $s) {
        $s->field('title')
          ->placeholder('Product name')
          ->help('The display name for this product');
        $s->field('description');
    });

    $layout->section('Inventory', function(Section $s) {
        $s->field('price');
        $s->field('in_stock');
    });

    $layout->sidebar(function(Sidebar $sb) {
        $sb->actions(['save', 'delete']);
    });
});
```

## Layout Components

### Sections

Group related fields together:

```php
$layout->section('Section Title', function(Section $s) {
    $s->field('field_name');
});
```

### Tabs

Organize content into tabbed panels:

```php
$layout->tabs(function(Tabs $tabs) {
    $tabs->tab('Tab 1', function(Tab $t) {
        $t->field('field1');
    });
    $tabs->tab('Tab 2', function(Tab $t) {
        $t->field('field2');
    });
});
```

### Sidebar

Add a sidebar for status fields and actions:

```php
$layout->sidebar(function(Sidebar $sb) {
    $sb->field('status');
    $sb->actions(['save', 'delete']);
});
```

## Nesting

Sections and tabs can be nested arbitrarily:

```php
$layout->section('Main', function(Section $s) {
    $s->field('title');

    // Nested section
    $s->section('Advanced', function(Section $nested) {
        $nested->field('slug');
    });

    // Tabs inside section
    $s->tabs(function(Tabs $tabs) {
        $tabs->tab('Content', function(Tab $t) {
            $t->field('body');
        });
        $tabs->tab('SEO', function(Tab $t) {
            $t->field('meta_description');
        });
    });
});
```

## Field Configuration

Within layouts, you can configure field presentation:

```php
$s->field('title')
  ->placeholder('Enter title')
  ->help('Help text shown below')
  ->readonly()
  ->width('50%');
```

See [Sections](/layouts/sections) for all field options.
