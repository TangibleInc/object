---
sidebar_position: 3
title: Tabs
description: Organizing content with tabbed navigation
---

# Tabs

Tabs organize content into separate panels, reducing visual clutter for complex forms.

## Basic Tabs

```php
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Tabs;
use Tangible\EditorLayout\Tab;

$view->set_layout(function(Layout $layout) {
    $layout->tabs(function(Tabs $tabs) {
        $tabs->tab('General', function(Tab $t) {
            $t->field('title');
            $t->field('description');
        });

        $tabs->tab('Settings', function(Tab $t) {
            $t->field('is_published');
            $t->field('publish_date');
        });
    });
});
```

## Tab Content

Tabs can contain fields, sections, or nested tabs.

### Fields in Tabs

```php
$tabs->tab('Content', function(Tab $t) {
    $t->field('title')
      ->placeholder('Enter title');
    $t->field('body')
      ->help('Main content area');
});
```

### Sections in Tabs

```php
$tabs->tab('Details', function(Tab $t) {
    $t->section('Basic', function(Section $s) {
        $s->field('name');
        $s->field('email');
    });

    $t->section('Address', function(Section $s) {
        $s->field('street');
        $s->field('city');
    });
});
```

### Nested Tabs

```php
$tabs->tab('Advanced', function(Tab $t) {
    $t->tabs(function(Tabs $nested) {
        $nested->tab('SEO', function(Tab $seo) {
            $seo->field('meta_title');
            $seo->field('meta_description');
        });

        $nested->tab('Social', function(Tab $social) {
            $social->field('og_title');
            $social->field('og_image');
        });
    });
});
```

## Tabs Inside Sections

Tabs can be placed inside sections for organized layouts:

```php
$layout->section('Product', function(Section $s) {
    $s->field('title');
    $s->field('price');

    $s->tabs(function(Tabs $tabs) {
        $tabs->tab('Description', function(Tab $t) {
            $t->field('short_description');
            $t->field('full_description');
        });

        $tabs->tab('Images', function(Tab $t) {
            $t->field('main_image');
            $t->field('gallery');
        });

        $tabs->tab('Inventory', function(Tab $t) {
            $t->field('sku');
            $t->field('stock_quantity');
        });
    });
});
```

## Complete Example

A blog post editor with multiple tab groups:

```php
$view->set_layout(function(Layout $layout) {
    // Main content tabs
    $layout->tabs(function(Tabs $tabs) {
        $tabs->tab('Content', function(Tab $t) {
            $t->field('title')
              ->placeholder('Post title');
            $t->field('body')
              ->help('Main post content');
            $t->field('excerpt')
              ->help('Short summary for listings');
        });

        $tabs->tab('Media', function(Tab $t) {
            $t->field('featured_image');
            $t->field('gallery');
        });

        $tabs->tab('SEO', function(Tab $t) {
            $t->section('Meta Tags', function(Section $s) {
                $s->field('meta_title');
                $s->field('meta_description');
            });

            $t->section('Social Sharing', function(Section $s) {
                $s->field('og_title');
                $s->field('og_description');
                $s->field('og_image');
            });
        });

        $tabs->tab('Advanced', function(Tab $t) {
            $t->field('slug');
            $t->field('custom_css');
            $t->field('custom_js');
        });
    });

    // Sidebar
    $layout->sidebar(function(Sidebar $sb) {
        $sb->field('status');
        $sb->field('publish_date');
        $sb->field('author');
        $sb->actions(['save', 'delete']);
    });
});
```
