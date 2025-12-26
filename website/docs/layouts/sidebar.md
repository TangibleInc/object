---
sidebar_position: 4
title: Sidebar
description: Adding sidebars for actions and metadata
---

# Sidebar

The sidebar provides a fixed panel for status information and action buttons.

## Basic Sidebar

```php
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Sidebar;

$view->set_layout(function(Layout $layout) {
    $layout->section('Content', function(Section $s) {
        $s->field('title');
        $s->field('body');
    });

    $layout->sidebar(function(Sidebar $sb) {
        $sb->actions(['save', 'delete']);
    });
});
```

## Sidebar Fields

Add fields to the sidebar for quick access:

```php
$layout->sidebar(function(Sidebar $sb) {
    $sb->field('status');
    $sb->field('publish_date');
    $sb->field('is_featured');

    $sb->actions(['save', 'delete']);
});
```

## Field Options

Sidebar fields support the same options as section fields:

```php
$sb->field('created_at')->readonly();
$sb->field('status')->help('Current publication status');
```

## Actions

The `actions()` method defines which buttons appear:

```php
// Save and delete buttons
$sb->actions(['save', 'delete']);

// Save only (for settings pages)
$sb->actions(['save']);
```

## Sidebar for Settings Pages

For singular mode (settings), typically only a save button is needed:

```php
$view = new DataView([
    'slug'    => 'settings',
    'mode'    => 'singular',
    'storage' => 'option',
    'fields'  => [...],
]);

$view->set_layout(function(Layout $layout) {
    $layout->section('API', function(Section $s) {
        $s->field('api_key');
        $s->field('api_url');
    });

    $layout->sidebar(function(Sidebar $sb) {
        $sb->actions(['save']);
    });
});
```

## Complete Example

A product editor with status sidebar:

```php
$view->set_layout(function(Layout $layout) {
    // Main content area
    $layout->tabs(function(Tabs $tabs) {
        $tabs->tab('General', function(Tab $t) {
            $t->field('title');
            $t->field('description');
            $t->field('price');
        });

        $tabs->tab('Inventory', function(Tab $t) {
            $t->field('sku');
            $t->field('stock_quantity');
            $t->field('allow_backorders');
        });
    });

    // Sidebar with status and actions
    $layout->sidebar(function(Sidebar $sb) {
        // Status fields
        $sb->field('status')
           ->help('Publication status');
        $sb->field('is_featured')
           ->help('Show on homepage');

        // Read-only metadata
        $sb->field('created_at')->readonly();
        $sb->field('updated_at')->readonly();
        $sb->field('view_count')->readonly();

        // Action buttons
        $sb->actions(['save', 'delete']);
    });
});
```

## Sidebar Position

The sidebar is typically rendered on the right side of the form, following WordPress admin conventions. The exact styling depends on the renderer being used.
