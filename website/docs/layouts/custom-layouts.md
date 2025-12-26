---
sidebar_position: 5
title: Custom Layouts
description: Advanced layout customization techniques
---

# Custom Layouts

This page covers advanced layout patterns and techniques.

## Layout Structure

When you call `set_layout()`, you're building a structure that the renderer will convert to HTML:

```php
$view->set_layout(function(Layout $layout) {
    // Build your structure here
});
```

The layout object tracks:
- **Items** - Sections, tabs, and fields in the main area
- **Sidebar** - Sidebar configuration (optional)
- **Dataset** - Reference to the DataSet for field type info

## Accessing the Structure

You can inspect the layout structure:

```php
$structure = $layout->get_structure();

// Returns:
// [
//     'items' => [...],
//     'sidebar' => [...],
// ]
```

This is useful when building custom renderers.

## Conditional Sections

Show sections based on field values:

```php
$layout->section('Basic', function(Section $s) {
    $s->field('product_type'); // 'physical' or 'digital'
});

$layout->section('Shipping', function(Section $s) {
    $s->condition('product_type', 'physical');

    $s->field('weight');
    $s->field('dimensions');
});

$layout->section('Download', function(Section $s) {
    $s->condition('product_type', 'digital');

    $s->field('download_url');
    $s->field('download_limit');
});
```

## Dynamic Layout Building

Build layouts programmatically:

```php
$view->set_layout(function(Layout $layout) use ($custom_fields) {
    // Standard fields
    $layout->section('Basic', function(Section $s) {
        $s->field('title');
        $s->field('description');
    });

    // Dynamic fields from configuration
    if (!empty($custom_fields)) {
        $layout->section('Custom Fields', function(Section $s) use ($custom_fields) {
            foreach ($custom_fields as $field) {
                $s->field($field['name'])
                  ->help($field['description'] ?? '');
            }
        });
    }

    $layout->sidebar(function(Sidebar $sb) {
        $sb->actions(['save', 'delete']);
    });
});
```

## Reusable Layout Components

Create reusable layout functions:

```php
function add_seo_section(Layout $layout) {
    $layout->section('SEO', function(Section $s) {
        $s->field('meta_title')
          ->placeholder('Page title for search engines');
        $s->field('meta_description')
          ->help('Max 160 characters');
        $s->field('meta_keywords');
    });
}

function add_status_sidebar(Layout $layout) {
    $layout->sidebar(function(Sidebar $sb) {
        $sb->field('status');
        $sb->field('publish_date');
        $sb->actions(['save', 'delete']);
    });
}

// Use in multiple DataViews
$view->set_layout(function(Layout $layout) {
    $layout->section('Content', function(Section $s) {
        $s->field('title');
        $s->field('body');
    });

    add_seo_section($layout);
    add_status_sidebar($layout);
});
```

## Complex Nesting Example

A deeply nested layout for complex data:

```php
$view->set_layout(function(Layout $layout) {
    $layout->section('Event', function(Section $s) {
        $s->field('title');
        $s->field('description');

        // Location section
        $s->section('Location', function(Section $loc) {
            $loc->field('venue_name');

            $loc->section('Address', function(Section $addr) {
                $addr->columns(2);
                $addr->field('street');
                $addr->field('city');
                $addr->field('state');
                $addr->field('zip');
            });

            $loc->section('Coordinates', function(Section $coords) {
                $coords->columns(2);
                $coords->field('latitude');
                $coords->field('longitude');
            });
        });

        // Schedule tabs
        $s->tabs(function(Tabs $tabs) {
            $tabs->tab('Date & Time', function(Tab $t) {
                $t->field('start_date');
                $t->field('end_date');
                $t->field('timezone');
            });

            $tabs->tab('Recurrence', function(Tab $t) {
                $t->field('is_recurring');
                $t->field('recurrence_pattern');
                $t->field('recurrence_end');
            });
        });
    });

    $layout->sidebar(function(Sidebar $sb) {
        $sb->field('status');
        $sb->field('is_featured');
        $sb->field('max_attendees');
        $sb->actions(['save', 'delete']);
    });
});
```

## Layout Without Sidebar

Not all layouts need a sidebar:

```php
$view->set_layout(function(Layout $layout) {
    $layout->section('Settings', function(Section $s) {
        $s->field('option1');
        $s->field('option2');
    });

    // The save button will appear at the bottom of the form
});
```
