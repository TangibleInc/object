---
sidebar_position: 1
title: Overview
description: Understanding renderers in DataView
---

# Renderers Overview

Renderers convert the EditorLayout structure into HTML. DataView includes two built-in renderers and supports custom implementations.

## What Renderers Do

A renderer takes:
- **Layout** - The structured definition of sections, tabs, and fields
- **Data** - The current values to populate the form

And produces:
- **HTML** - The rendered form elements

## Built-in Renderers

### HtmlRenderer (Default)

Simple, lightweight renderer using standard HTML form elements:

```php
use Tangible\Renderer\HtmlRenderer;

$view->set_renderer(new HtmlRenderer());
```

Best for:
- Simple forms
- Minimal dependencies
- Maximum compatibility

### TangibleFieldsRenderer

Rich, React-powered form components:

```php
use Tangible\Renderer\TangibleFieldsRenderer;

$view->set_renderer(new TangibleFieldsRenderer());
```

Best for:
- Complex forms
- Repeater fields
- Enhanced UX (date pickers, switches, etc.)

Requires the Tangible Fields framework.

## Setting a Renderer

Use `set_renderer()` before calling `register()`:

```php
$view = new DataView([
    'slug'   => 'product',
    'label'  => 'Product',
    'fields' => [...],
]);

$view->set_renderer(new TangibleFieldsRenderer());
$view->register();
```

## Renderer Interface

All renderers implement the `Renderer` interface:

```php
interface Renderer {
    public function render_editor(Layout $layout, array $data = []): string;
    public function render_list(DataSet $dataset, array $entities): string;
}
```

### render_editor

Renders the edit/create form:

```php
$html = $renderer->render_editor($layout, [
    'title' => 'My Product',
    'price' => 1999,
]);
```

### render_list

Renders the list view:

```php
$html = $renderer->render_list($dataset, $entities);
```

## Choosing a Renderer

| Feature | HtmlRenderer | TangibleFieldsRenderer |
|---------|--------------|----------------------|
| Dependencies | None | Tangible Fields |
| File size | Minimal | Larger (React) |
| Repeaters | Basic | Full support |
| Date pickers | Native HTML5 | Enhanced |
| Switches | Checkbox | Toggle switch |
| Styling | WordPress native | Custom theme |

## Next Steps

- [HtmlRenderer details](/renderers/html-renderer)
- [TangibleFieldsRenderer details](/renderers/tangible-fields)
- [Building custom renderers](/renderers/custom-renderers)
