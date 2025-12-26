# Tangible Object

A WordPress tool suite for building data-driven admin interfaces with a clean, layered architecture.

**[View Full Documentation →](https://tangibleinc.github.io/object/)**

## Quick Start

The easiest way to use Tangible Object is through the **DataView** API:

```php
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

    $view->get_handler()
        ->add_validator('name', Validators::required())
        ->add_validator('email', Validators::required())
        ->add_validator('email', Validators::email());

    $view->register();
});
```

This creates:
- A Custom Post Type for your data
- An admin menu page with list, create, and edit views
- Form handling with validation and sanitization
- Full CRUD operations

## Features

- **Declarative configuration** - Define your data structure once, get forms and validation automatically
- **Multiple storage backends** - Custom Post Types, database tables, or WordPress options
- **Flexible layouts** - Sections, tabs, sidebars, and nested structures
- **Built-in validation** - Required fields, email, min/max, custom validators
- **Lifecycle hooks** - React to create, update, and delete operations
- **Repeater fields** - Manage collections of sub-items
- **Multiple renderers** - Plain HTML or rich Tangible Fields components

## Architecture

For advanced customization, Tangible Object exposes four underlying layers:

1. **DataSet** - Define field types and coercion rules
2. **EditorLayout** - Compose the editor structure (sections, tabs, fields)
3. **Renderer** - Generate HTML output from the layout
4. **RequestHandler** - Handle CRUD operations with validation

[Learn more about the architecture →](https://tangibleinc.github.io/object/advanced/architecture)

## Documentation

- [Getting Started](https://tangibleinc.github.io/object/getting-started/quick-start)
- [DataView Configuration](https://tangibleinc.github.io/object/dataview/configuration)
- [Field Types](https://tangibleinc.github.io/object/dataview/field-types)
- [Layouts](https://tangibleinc.github.io/object/layouts/overview)
- [Validation](https://tangibleinc.github.io/object/dataview/validation)
- [Examples](https://tangibleinc.github.io/object/examples/settings-page)

## Requirements

- PHP 8.0+
- WordPress 5.0+

## License

MIT
