---
sidebar_position: 1
title: Overview
description: Understanding the DataView high-level API
---

# DataView Overview

The DataView layer is a high-level facade that orchestrates all Tangible Object components to provide a simple, declarative API for creating WordPress admin interfaces.

## What DataView Does

Instead of manually wiring together DataSet, EditorLayout, Renderer, and RequestHandler, DataView lets you define everything in a single configuration:

```php
use Tangible\DataView\DataView;

$view = new DataView([
    'slug'   => 'book',
    'label'  => 'Book',
    'fields' => [
        'title'     => 'string',
        'author'    => 'string',
        'isbn'      => 'string',
        'published' => 'date',
        'in_stock'  => 'boolean',
    ],
    'ui' => [
        'menu_label' => 'Books',
        'icon'       => 'dashicons-book',
    ],
]);

$view->register();
```

This single declaration:
- Creates a DataSet with the specified fields
- Sets up the appropriate storage adapter (CPT by default)
- Creates a request handler with proper sanitization
- Registers an admin menu page
- Handles all CRUD operations with forms and validation

## Modes

DataView supports two modes:

### Plural Mode (Default)

For managing multiple items with full CRUD operations:

```php
$view = new DataView([
    'slug'   => 'product',
    'label'  => 'Product',
    'mode'   => 'plural', // This is the default
    'fields' => [...],
]);
```

Provides:
- List view with all items
- Create form
- Edit form
- Delete action

### Singular Mode

For single-instance data like plugin settings:

```php
$view = new DataView([
    'slug'    => 'my_plugin_settings',
    'label'   => 'Settings',
    'mode'    => 'singular',
    'storage' => 'option',
    'fields'  => [...],
]);
```

Provides:
- Single form for reading/updating settings
- No create/delete operations

## Accessing Components

After creating a DataView, you can access the underlying components:

```php
$view = new DataView([...]);

// Get the request handler for validation and hooks
$handler = $view->get_handler();

// Get the data object
$object = $view->get_object();

// Get the dataset
$dataset = $view->get_dataset();

// Get the configuration
$config = $view->get_config();

// Get the field registry
$registry = $view->get_field_registry();
```

## Programmatic Usage

DataView can be used outside the admin context for programmatic data access:

```php
$view = new DataView([
    'slug'   => 'subscriber',
    'label'  => 'Subscriber',
    'fields' => [
        'email'      => 'email',
        'subscribed' => 'boolean',
    ],
]);

$handler = $view->get_handler();

// Create
$result = $handler->create([
    'email'      => 'user@example.com',
    'subscribed' => true,
]);

// Read
$result = $handler->read($id);
$entity = $result->get_entity();

// Update
$handler->update($id, ['subscribed' => false]);

// Delete
$handler->delete($id);

// List all
$result = $handler->list();
foreach ($result->get_entities() as $entity) {
    echo $entity->get('email');
}
```
