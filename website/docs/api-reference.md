---
sidebar_position: 100
title: API Reference
description: Complete API reference for all classes
---

# API Reference

Quick reference for all public APIs.

## DataView

### Constructor

```php
$view = new DataView(array $config);
```

### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `register()` | `static` | Registers admin menu and hooks |
| `get_handler()` | `PluralHandler\|SingularHandler` | Request handler |
| `get_object()` | `PluralObject\|SingularObject` | Data object |
| `get_dataset()` | `DataSet` | Dataset instance |
| `get_config()` | `DataViewConfig` | Configuration |
| `get_field_registry()` | `FieldTypeRegistry` | Field registry |
| `url(string $action, ?int $id)` | `string` | Admin URL |
| `set_layout(callable $callback)` | `static` | Custom layout |
| `set_renderer(Renderer $renderer)` | `static` | Custom renderer |
| `handle_request()` | `void` | Handle current request |

## DataViewConfig

### Properties (readonly)

| Property | Type | Description |
|----------|------|-------------|
| `$slug` | string | Unique identifier |
| `$label` | string | Singular label |
| `$labels` | array | Full labels array |
| `$fields` | array | Field definitions |
| `$field_configs` | array | Full field configs |
| `$storage` | string | Storage type |
| `$mode` | string | plural/singular |
| `$capability` | string | Required capability |
| `$storage_options` | array | Storage options |
| `$ui` | array | UI configuration |

### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `is_plural()` | `bool` | Is plural mode |
| `is_singular()` | `bool` | Is singular mode |
| `get_menu_page()` | `string` | Menu slug |
| `get_menu_label()` | `string` | Menu label |
| `get_parent_menu()` | `?string` | Parent menu |
| `get_icon()` | `string` | Menu icon |
| `get_position()` | `?int` | Menu position |
| `get_singular_label()` | `string` | Singular label |
| `get_plural_label()` | `?string` | Plural label |
| `get_label(string $key, ?string $fallback)` | `?string` | Specific label |
| `get_field_config(string $name)` | `?array` | Field config |

## PluralHandler

### CRUD Methods

```php
$result = $handler->create(array $data);
$result = $handler->read(int $id);
$result = $handler->update(int $id, array $data);
$result = $handler->delete(int $id);
$result = $handler->list();
```

### Validation

```php
$handler->add_validator(string $field, callable $validator);
```

### Lifecycle Hooks

```php
$handler->before_create(callable $callback);
$handler->after_create(callable $callback);
$handler->before_update(callable $callback);
$handler->after_update(callable $callback);
$handler->before_delete(callable $callback);
$handler->after_delete(callable $callback);
```

## SingularHandler

### Methods

```php
$result = $handler->read();
$result = $handler->update(array $data);
$handler->add_validator(string $field, callable $validator);
$handler->before_update(callable $callback);
$handler->after_update(callable $callback);
```

## Validators

```php
use Tangible\RequestHandler\Validators;

Validators::required();
Validators::email();
Validators::min_length(int $length);
Validators::max_length(int $length);
Validators::min(int $value);
Validators::max(int $value);
Validators::in(array $values);
```

## Result Object

```php
$result->is_success();
$result->is_error();
$result->get_entity();      // PluralHandler
$result->get_entities();    // PluralHandler::list()
$result->get_data();        // SingularHandler
$result->get_errors();
```

## ValidationError

```php
use Tangible\RequestHandler\ValidationError;

$error = new ValidationError(string $message, ?string $field = null);
$error->get_message();
$error->get_field();
```

## Layout Classes

### Layout

```php
$layout = new Layout(DataSet $dataset);
$layout->section(string $label, callable $callback);
$layout->tabs(callable $callback);
$layout->sidebar(callable $callback);
$layout->get_structure();
$layout->get_dataset();
```

### Section

```php
$section->field(string $name);
$section->section(string $label, callable $callback);
$section->tabs(callable $callback);
$section->columns(int $count);
$section->condition(string $field, mixed $value);
```

### Field (in Section)

```php
$section->field('name')
    ->placeholder(string $text)
    ->help(string $text)
    ->readonly()
    ->width(string $width);
```

### Tabs

```php
$tabs->tab(string $label, callable $callback);
```

### Tab

```php
$tab->field(string $name);
$tab->section(string $label, callable $callback);
$tab->tabs(callable $callback);
```

### Sidebar

```php
$sidebar->field(string $name);
$sidebar->actions(array $actions);
```

## FieldTypeRegistry

```php
$registry->has_type(string $type);
$registry->get_dataset_type(string $type);
$registry->get_sanitizer(string $type);
$registry->get_schema(string $type);
$registry->get_input_type(string $type);
$registry->register_type(string $name, array $config);
```

## Renderer Interface

```php
interface Renderer {
    public function render_editor(Layout $layout, array $data = []): string;
    public function render_list(DataSet $dataset, array $entities): string;
}
```

## UrlBuilder

```php
$builder = new UrlBuilder(DataViewConfig $config);
$builder->url(string $action, ?int $id, array $extra);
$builder->url_with_nonce(string $action, ?int $id, string $nonce_action);
$builder->get_current_action();
$builder->get_current_id();
$builder->get_nonce_action(string $action, ?int $id);
```
