---
sidebar_position: 2
title: Quick Start
description: Create your first admin interface in minutes
---

# Quick Start

This guide will walk you through creating a complete admin interface for managing contact form entries.

## Step 1: Define Your DataView

Create a new PHP file in your plugin (e.g., `includes/contact-entries.php`):

```php
<?php
use Tangible\DataView\DataView;
use Tangible\RequestHandler\Validators;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'contact_entry',
        'label'  => 'Contact Entry',
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

    // Add validation
    $view->get_handler()
        ->add_validator('name', Validators::required())
        ->add_validator('email', Validators::required())
        ->add_validator('email', Validators::email());

    $view->register();
});
```

## Step 2: Include in Your Plugin

Add the file to your main plugin file:

```php
require_once __DIR__ . '/includes/contact-entries.php';
```

## Step 3: That's It!

Visit your WordPress admin. You'll see a new "Contact Entries" menu item with:

- A **list view** showing all entries
- A **create form** for adding new entries
- An **edit form** for updating entries
- **Delete** functionality
- **Validation** that enforces required fields and valid email format

## What Just Happened?

With about 20 lines of code, DataView:

1. Registered a Custom Post Type for your data
2. Set up automatic type coercion (strings, booleans, etc.)
3. Generated a responsive admin menu page
4. Built list, create, and edit views with proper WordPress styling
5. Added form handling with sanitization and validation
6. Implemented full CRUD operations

## Next Steps

- [Configure field types](/dataview/field-types) for more control
- [Add custom layouts](/layouts/overview) with sections and tabs
- [Set up lifecycle hooks](/dataview/lifecycle-hooks) for custom logic
- [Use different storage backends](/dataview/storage) like CPT or options
