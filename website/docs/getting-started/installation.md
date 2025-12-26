---
sidebar_position: 1
title: Installation
description: How to install Tangible Object in your WordPress project
---

# Installation

## Via Composer

The recommended way to install Tangible Object is via Composer:

```bash
composer require tangible/object
```

Then include the autoloader in your plugin or theme:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

## Manual Installation

1. Download the latest release from the [GitHub repository](https://github.com/tangibleinc/object)
2. Extract to your plugin's directory
3. Include the main file:

```php
require_once __DIR__ . '/path/to/object/plugin.php';
```

## Verifying Installation

To verify the installation is working, you can create a simple DataView:

```php
use Tangible\DataView\DataView;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'test_item',
        'label'  => 'Test',
        'fields' => [
            'title' => 'string',
        ],
        'storage' => 'option',
        'mode'    => 'singular',
    ]);

    $view->register();
});
```

After activating your plugin, you should see a "Test" menu item in the WordPress admin.
