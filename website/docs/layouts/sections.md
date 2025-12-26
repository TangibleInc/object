---
sidebar_position: 2
title: Sections
description: Grouping fields into sections
---

# Sections

Sections group related fields under a labeled heading.

## Basic Section

```php
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;

$view->set_layout(function(Layout $layout) {
    $layout->section('Contact Information', function(Section $s) {
        $s->field('name');
        $s->field('email');
        $s->field('phone');
    });
});
```

## Field Options

Configure how fields are displayed:

```php
$s->field('title')
  ->placeholder('Enter a title')    // Placeholder text
  ->help('Help text below field')   // Help text
  ->readonly()                      // Make read-only
  ->width('50%');                   // Set width
```

### placeholder

Sets the input placeholder text:

```php
$s->field('email')->placeholder('you@example.com');
```

### help

Displays help text below the field:

```php
$s->field('slug')->help('URL-friendly identifier, lowercase with hyphens');
```

### readonly

Makes the field read-only (displayed but not editable):

```php
$s->field('created_at')->readonly();
```

### width

Sets the field width (useful for inline fields):

```php
$s->field('first_name')->width('50%');
$s->field('last_name')->width('50%');
```

## Section Options

### columns

Display fields in multiple columns:

```php
$layout->section('Address', function(Section $s) {
    $s->columns(2);

    $s->field('street');
    $s->field('city');
    $s->field('state');
    $s->field('zip');
});
```

### condition

Show section conditionally based on another field's value:

```php
$layout->section('Shipping Address', function(Section $s) {
    $s->condition('needs_shipping', true);

    $s->field('shipping_street');
    $s->field('shipping_city');
});
```

## Nested Sections

Sections can contain other sections:

```php
$layout->section('Product', function(Section $s) {
    $s->field('title');
    $s->field('description');

    $s->section('Pricing', function(Section $nested) {
        $nested->field('price');
        $nested->field('sale_price');
    });

    $s->section('Inventory', function(Section $nested) {
        $nested->field('stock_quantity');
        $nested->field('allow_backorders');
    });
});
```

## Sections with Tabs

Embed tabs within a section:

```php
$layout->section('Product Details', function(Section $s) {
    $s->field('title');

    $s->tabs(function(Tabs $tabs) {
        $tabs->tab('Description', function(Tab $t) {
            $t->field('short_description');
            $t->field('full_description');
        });

        $tabs->tab('Specifications', function(Tab $t) {
            $t->field('weight');
            $t->field('dimensions');
        });
    });
});
```

## Multiple Top-Level Sections

```php
$view->set_layout(function(Layout $layout) {
    $layout->section('Basic Info', function(Section $s) {
        $s->field('title');
        $s->field('slug');
    });

    $layout->section('Content', function(Section $s) {
        $s->field('body');
        $s->field('excerpt');
    });

    $layout->section('Settings', function(Section $s) {
        $s->field('is_published');
        $s->field('publish_date');
    });
});
```
