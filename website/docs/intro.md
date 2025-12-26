---
sidebar_position: 1
slug: /
title: Introduction
description: A WordPress tool suite for building data-driven admin interfaces
---

# Tangible Object

A WordPress tool suite for building data-driven admin interfaces with a clean, layered architecture.

## What is Tangible Object?

Tangible Object provides a structured approach to building WordPress admin interfaces. Whether you need a simple settings page or a full CRUD interface for custom data, this framework handles the complexity while keeping your code clean and maintainable.

## Two Ways to Build

### The Easy Way: DataView

For most use cases, **DataView** is all you need. It's a high-level API that lets you define your entire admin interface in a single configuration array:

```php
use Tangible\DataView\DataView;

$view = new DataView([
    'slug'   => 'contact_entry',
    'label'  => 'Contact',
    'fields' => [
        'name'    => 'string',
        'email'   => 'email',
        'message' => 'text',
    ],
    'ui' => [
        'menu_label' => 'Contact Entries',
    ],
]);

$view->register();
```

This single declaration creates:
- A Custom Post Type to store your data
- An admin menu page
- List, create, and edit views
- Form handling with validation
- Proper sanitization

[Get started with DataView →](/getting-started/quick-start)

### The Flexible Way: Four-Layer Architecture

For advanced customization, you can work directly with the four underlying layers:

1. **DataSet** - Define field types and coercion rules
2. **EditorLayout** - Compose the editor structure (sections, tabs, fields)
3. **Renderer** - Generate HTML output from the layout
4. **RequestHandler** - Handle CRUD operations with validation

[Learn about the architecture →](/advanced/architecture)

## Key Features

- **Declarative configuration** - Define your data structure once, get forms and validation automatically
- **Multiple storage backends** - Custom Post Types, database tables, or WordPress options
- **Flexible layouts** - Sections, tabs, sidebars, and nested structures
- **Built-in validation** - Required fields, email, min/max, custom validators
- **Lifecycle hooks** - React to create, update, and delete operations
- **Repeater fields** - Manage collections of sub-items
- **Multiple renderers** - Plain HTML or rich Tangible Fields components

## Requirements

- PHP 8.0+
- WordPress 5.0+
