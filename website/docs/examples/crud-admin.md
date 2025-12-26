---
sidebar_position: 2
title: CRUD Admin
description: Building a full CRUD admin interface
---

# CRUD Admin Example

This example shows how to create a complete admin interface for managing data with create, read, update, and delete operations.

## Basic CRUD

```php
use Tangible\DataView\DataView;
use Tangible\RequestHandler\Validators;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'contact_entry',
        'label'  => [
            'singular' => 'Contact Entry',
            'plural'   => 'Contact Entries',
        ],
        'fields' => [
            'name'      => 'string',
            'email'     => 'email',
            'message'   => 'text',
            'subscribe' => 'boolean',
        ],
        'ui' => [
            'menu_label' => 'Contact Entries',
            'icon'       => 'dashicons-email',
        ],
    ]);

    $view->get_handler()
        ->add_validator('name', Validators::required())
        ->add_validator('email', Validators::required())
        ->add_validator('email', Validators::email());

    $view->register();
});
```

## Complete Blog Post Manager

A more complex example with custom layout, validation, and hooks:

```php
use Tangible\DataView\DataView;
use Tangible\RequestHandler\Validators;
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;
use Tangible\EditorLayout\Tabs;
use Tangible\EditorLayout\Tab;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'blog_post',
        'label'  => [
            'singular'     => __('Blog Post', 'my-theme'),
            'plural'       => __('Blog Posts', 'my-theme'),
            'add_new_item' => __('Write New Post', 'my-theme'),
            'edit_item'    => __('Edit Post', 'my-theme'),
        ],
        'fields' => [
            'title'        => 'string',
            'slug'         => 'string',
            'content'      => 'text',
            'excerpt'      => 'text',
            'author_email' => 'email',
            'published_at' => 'datetime',
            'is_featured'  => 'boolean',
            'view_count'   => 'integer',
        ],
        'ui' => [
            'menu_label' => __('Blog Posts', 'my-theme'),
            'icon'       => 'dashicons-edit',
            'position'   => 5,
        ],
    ]);

    // Custom layout with tabs
    $view->set_layout(function(Layout $layout) {
        $layout->tabs(function(Tabs $tabs) {
            $tabs->tab('Content', function(Tab $tab) {
                $tab->field('title')
                    ->placeholder('Post title')
                    ->help('The main title of the post');
                $tab->field('slug')
                    ->placeholder('post-url-slug')
                    ->help('URL-friendly identifier');
                $tab->field('content')
                    ->help('Main post content');
                $tab->field('excerpt')
                    ->help('Short summary for listings');
            });

            $tabs->tab('Meta', function(Tab $tab) {
                $tab->field('author_email');
                $tab->field('published_at');
                $tab->field('view_count')->readonly();
            });
        });

        $layout->sidebar(function(Sidebar $sidebar) {
            $sidebar->field('is_featured');
            $sidebar->actions(['save', 'delete']);
        });
    });

    // Validation
    $view->get_handler()
        ->add_validator('title', Validators::required())
        ->add_validator('title', Validators::max_length(200))
        ->add_validator('slug', Validators::required())
        ->add_validator('author_email', Validators::email())
        ->add_validator('slug', function($value) {
            if (!preg_match('/^[a-z0-9-]+$/', $value)) {
                return new \Tangible\RequestHandler\ValidationError(
                    'Slug must contain only lowercase letters, numbers, and hyphens'
                );
            }
            return true;
        });

    // Lifecycle hooks
    $view->get_handler()
        ->before_create(function($data) {
            $data['view_count'] = 0;
            if (empty($data['slug'])) {
                $data['slug'] = sanitize_title($data['title']);
            }
            return $data;
        })
        ->after_create(function($entity) {
            delete_transient('blog_posts_list');
        })
        ->before_update(function($entity, $data) {
            // Auto-generate slug if empty
            if (empty($data['slug']) && !empty($data['title'])) {
                $data['slug'] = sanitize_title($data['title']);
            }
            return $data;
        })
        ->after_update(function($entity) {
            delete_transient('blog_post_' . $entity->get_id());
            delete_transient('blog_posts_list');
        })
        ->after_delete(function($id) {
            delete_transient('blog_post_' . $id);
            delete_transient('blog_posts_list');
        });

    $view->register();
});
```

## Product Catalog

An e-commerce product example:

```php
add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'product',
        'label'  => [
            'singular' => 'Product',
            'plural'   => 'Products',
        ],
        'fields' => [
            'name'           => 'string',
            'sku'            => 'string',
            'description'    => 'text',
            'price'          => 'integer',
            'sale_price'     => 'integer',
            'stock_quantity' => 'integer',
            'is_active'      => 'boolean',
            'is_featured'    => 'boolean',
        ],
        'ui' => [
            'menu_label' => 'Products',
            'icon'       => 'dashicons-cart',
        ],
    ]);

    $view->set_layout(function(Layout $layout) {
        $layout->section('Product Information', function(Section $s) {
            $s->field('name')
              ->placeholder('Product name');
            $s->field('sku')
              ->placeholder('SKU-001')
              ->help('Stock Keeping Unit');
            $s->field('description');
        });

        $layout->section('Pricing', function(Section $s) {
            $s->columns(2);
            $s->field('price')
              ->help('Regular price in cents');
            $s->field('sale_price')
              ->help('Sale price in cents (leave empty for no sale)');
        });

        $layout->section('Inventory', function(Section $s) {
            $s->field('stock_quantity');
        });

        $layout->sidebar(function(Sidebar $sb) {
            $sb->field('is_active')
               ->help('Show on storefront');
            $sb->field('is_featured')
               ->help('Display on homepage');
            $sb->actions(['save', 'delete']);
        });
    });

    $view->get_handler()
        ->add_validator('name', Validators::required())
        ->add_validator('sku', Validators::required())
        ->add_validator('price', Validators::required())
        ->add_validator('price', Validators::min(0))
        ->add_validator('stock_quantity', Validators::min(0));

    $view->register();
});
```

## Submenu Under CPT

Add a related data manager under a custom post type:

```php
$view = new DataView([
    'slug'   => 'product_review',
    'label'  => 'Review',
    'fields' => [
        'product_id' => 'integer',
        'rating'     => 'integer',
        'comment'    => 'text',
        'approved'   => 'boolean',
    ],
    'ui' => [
        'menu_label' => 'Reviews',
        'parent'     => 'edit.php?post_type=product',
    ],
]);
```
