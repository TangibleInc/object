---
sidebar_position: 2
title: HtmlRenderer
description: The default HTML form renderer
---

# HtmlRenderer

HtmlRenderer is the default renderer that produces standard HTML form elements with WordPress admin styling.

## Usage

HtmlRenderer is used by default, but you can set it explicitly:

```php
use Tangible\Renderer\HtmlRenderer;

$view = new DataView([...]);
$view->set_renderer(new HtmlRenderer());
```

## Field Rendering

Each field type renders to a specific HTML element:

| Field Type | HTML Element |
|------------|--------------|
| `string` | `<input type="text">` |
| `text` | `<textarea>` |
| `email` | `<input type="email">` |
| `url` | `<input type="url">` |
| `integer` | `<input type="number">` |
| `boolean` | `<input type="checkbox">` |
| `date` | `<input type="date">` |
| `datetime` | `<input type="datetime-local">` |
| `repeater` | Basic table with add/remove |

## Form Structure

The renderer produces a form structure like:

```html
<form method="post" class="tangible-object-form">
    <div class="form-section">
        <h2>Section Title</h2>
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="...">
            <p class="description">Help text</p>
        </div>
    </div>

    <div class="form-sidebar">
        <div class="form-field">...</div>
        <div class="form-actions">
            <button type="submit" name="action" value="save">Save</button>
            <button type="submit" name="action" value="delete">Delete</button>
        </div>
    </div>
</form>
```

## WordPress Styling

HtmlRenderer uses WordPress admin CSS classes for consistent styling:

- Form fields use `.regular-text`, `.large-text` classes
- Buttons use `.button`, `.button-primary` classes
- Tables use `.wp-list-table` classes

## Advantages

- **No dependencies** - Works with vanilla WordPress
- **Lightweight** - Minimal JavaScript required
- **Compatible** - Works with all browsers
- **Accessible** - Standard HTML form elements

## Limitations

- **Basic repeaters** - Simple table-based repeater UI
- **No rich inputs** - Uses native browser date pickers, etc.
- **Limited interactivity** - Conditional logic requires page reload

## When to Use

Use HtmlRenderer when:
- You want minimal dependencies
- Your forms are relatively simple
- Browser compatibility is critical
- You don't need repeater fields
