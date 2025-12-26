---
sidebar_position: 1
title: Settings Page
description: Building a plugin settings page
---

# Settings Page Example

This example shows how to create a comprehensive plugin settings page with multiple sections.

## Basic Settings

```php
use Tangible\DataView\DataView;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'my_plugin_settings',
        'label'  => 'Settings',
        'fields' => [
            'api_key'    => 'string',
            'debug_mode' => 'boolean',
        ],
        'storage' => 'option',
        'mode'    => 'singular',
        'ui' => [
            'menu_label' => 'My Plugin',
            'parent'     => 'options-general.php',
        ],
    ]);

    $view->register();
});
```

## Complete Settings Page

A full-featured settings page with sections and validation:

```php
use Tangible\DataView\DataView;
use Tangible\RequestHandler\Validators;
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'my_plugin_settings',
        'label'  => [
            'singular'       => __('Settings', 'my-plugin'),
            'settings'       => __('My Plugin Settings', 'my-plugin'),
            'settings_saved' => __('Settings saved successfully.', 'my-plugin'),
        ],
        'fields' => [
            // API Settings
            'api_key'      => 'string',
            'api_endpoint' => 'url',
            'api_timeout'  => 'integer',

            // Cache Settings
            'cache_enabled' => 'boolean',
            'cache_ttl'     => 'integer',

            // Display Settings
            'items_per_page' => 'integer',
            'date_format'    => 'string',

            // Debug
            'debug_mode' => 'boolean',
            'log_level'  => 'string',
        ],
        'storage' => 'option',
        'mode'    => 'singular',
        'ui' => [
            'menu_label' => __('My Plugin', 'my-plugin'),
            'parent'     => 'options-general.php',
        ],
    ]);

    // Custom layout with sections
    $view->set_layout(function(Layout $layout) {
        $layout->section(__('API Configuration', 'my-plugin'), function(Section $s) {
            $s->field('api_key')
              ->placeholder('Enter your API key')
              ->help('Get your API key from the dashboard');
            $s->field('api_endpoint')
              ->placeholder('https://api.example.com/v1');
            $s->field('api_timeout')
              ->help('Request timeout in seconds');
        });

        $layout->section(__('Caching', 'my-plugin'), function(Section $s) {
            $s->field('cache_enabled')
              ->help('Enable response caching');
            $s->field('cache_ttl')
              ->help('Cache time-to-live in seconds');
        });

        $layout->section(__('Display', 'my-plugin'), function(Section $s) {
            $s->field('items_per_page');
            $s->field('date_format')
              ->placeholder('Y-m-d H:i:s');
        });

        $layout->section(__('Development', 'my-plugin'), function(Section $s) {
            $s->field('debug_mode')
              ->help('Enable detailed logging');
            $s->field('log_level')
              ->help('Options: debug, info, warning, error');
        });

        $layout->sidebar(function(Sidebar $sb) {
            $sb->actions(['save']);
        });
    });

    // Validation
    $view->get_handler()
        ->add_validator('api_key', Validators::required())
        ->add_validator('api_timeout', Validators::min(1))
        ->add_validator('api_timeout', Validators::max(60))
        ->add_validator('cache_ttl', Validators::min(0))
        ->add_validator('items_per_page', Validators::min(1))
        ->add_validator('items_per_page', Validators::max(100))
        ->add_validator('log_level', Validators::in(['debug', 'info', 'warning', 'error']));

    // Clear cache when settings change
    $view->get_handler()
        ->before_update(function($current, $data) {
            if (($current['cache_ttl'] ?? 0) !== ($data['cache_ttl'] ?? 0) ||
                ($current['cache_enabled'] ?? false) !== ($data['cache_enabled'] ?? false)) {
                wp_cache_flush();
            }
            return $data;
        });

    $view->register();
});
```

## Accessing Settings Elsewhere

Read settings values in your plugin:

```php
function my_plugin_get_setting($key, $default = null) {
    $settings = get_option('my_plugin_settings', []);
    return $settings[$key] ?? $default;
}

// Usage
$api_key = my_plugin_get_setting('api_key');
$debug = my_plugin_get_setting('debug_mode', false);
```

## Settings with Tabs

For many settings, use tabs to organize:

```php
use Tangible\EditorLayout\Tabs;
use Tangible\EditorLayout\Tab;

$view->set_layout(function(Layout $layout) {
    $layout->tabs(function(Tabs $tabs) {
        $tabs->tab('General', function(Tab $t) {
            $t->field('site_title');
            $t->field('tagline');
        });

        $tabs->tab('API', function(Tab $t) {
            $t->field('api_key');
            $t->field('api_endpoint');
        });

        $tabs->tab('Advanced', function(Tab $t) {
            $t->field('debug_mode');
            $t->field('log_level');
        });
    });

    $layout->sidebar(function(Sidebar $sb) {
        $sb->actions(['save']);
    });
});
```

## Top-Level Menu

For a top-level settings menu instead of under Settings:

```php
'ui' => [
    'menu_label' => 'My Plugin',
    'icon'       => 'dashicons-admin-generic',
    'position'   => 80,
    // No 'parent' key = top-level menu
]
```
