---
sidebar_position: 4
title: Custom Renderers
description: Building your own renderer
---

# Custom Renderers

You can create custom renderers to control exactly how forms are displayed.

## Implementing the Interface

Create a class that implements the `Renderer` interface:

```php
use Tangible\Renderer\Renderer;
use Tangible\DataObject\DataSet;
use Tangible\EditorLayout\Layout;

class MyCustomRenderer implements Renderer {

    public function render_editor(Layout $layout, array $data = []): string {
        // Render the edit/create form
    }

    public function render_list(DataSet $dataset, array $entities): string {
        // Render the list view
    }
}
```

## Using Your Renderer

```php
$view = new DataView([...]);
$view->set_renderer(new MyCustomRenderer());
$view->register();
```

## Accessing Layout Structure

The layout provides its structure for rendering:

```php
public function render_editor(Layout $layout, array $data = []): string {
    $structure = $layout->get_structure();
    $dataset = $layout->get_dataset();

    // Structure format:
    // [
    //     'items' => [
    //         ['type' => 'section', 'label' => 'General', 'fields' => [...]],
    //         ['type' => 'tabs', 'tabs' => [...]],
    //     ],
    //     'sidebar' => [
    //         'fields' => [...],
    //         'actions' => ['save', 'delete'],
    //     ],
    // ]

    $html = '<form method="post" class="my-custom-form">';

    foreach ($structure['items'] as $item) {
        $html .= $this->render_item($item, $data, $dataset);
    }

    if (!empty($structure['sidebar'])) {
        $html .= $this->render_sidebar($structure['sidebar'], $data, $dataset);
    }

    $html .= '</form>';

    return $html;
}
```

## Rendering Items

Handle different item types:

```php
protected function render_item(array $item, array $data, DataSet $dataset): string {
    switch ($item['type']) {
        case 'section':
            return $this->render_section($item, $data, $dataset);
        case 'tabs':
            return $this->render_tabs($item, $data, $dataset);
        case 'field':
            return $this->render_field($item, $data, $dataset);
        default:
            return '';
    }
}

protected function render_section(array $section, array $data, DataSet $dataset): string {
    $html = '<div class="section">';
    $html .= '<h2>' . esc_html($section['label']) . '</h2>';

    foreach ($section['items'] ?? [] as $item) {
        $html .= $this->render_item($item, $data, $dataset);
    }

    foreach ($section['fields'] ?? [] as $field) {
        $html .= $this->render_field($field, $data, $dataset);
    }

    $html .= '</div>';
    return $html;
}
```

## Rendering Fields

Get field info from the dataset:

```php
protected function render_field(array $field, array $data, DataSet $dataset): string {
    $slug = $field['slug'];
    $value = $data[$slug] ?? '';
    $type = $dataset->get_type($slug);

    $html = '<div class="field">';
    $html .= '<label for="' . esc_attr($slug) . '">';
    $html .= esc_html($field['label'] ?? ucfirst($slug));
    $html .= '</label>';

    // Render input based on type
    switch ($type) {
        case DataSet::TYPE_BOOLEAN:
            $checked = $value ? 'checked' : '';
            $html .= '<input type="checkbox" id="' . esc_attr($slug) . '" ';
            $html .= 'name="' . esc_attr($slug) . '" value="1" ' . $checked . '>';
            break;

        case DataSet::TYPE_INTEGER:
            $html .= '<input type="number" id="' . esc_attr($slug) . '" ';
            $html .= 'name="' . esc_attr($slug) . '" ';
            $html .= 'value="' . esc_attr($value) . '">';
            break;

        default:
            $html .= '<input type="text" id="' . esc_attr($slug) . '" ';
            $html .= 'name="' . esc_attr($slug) . '" ';
            $html .= 'value="' . esc_attr($value) . '">';
    }

    if (!empty($field['help'])) {
        $html .= '<p class="help">' . esc_html($field['help']) . '</p>';
    }

    $html .= '</div>';
    return $html;
}
```

## Complete Example

A Bootstrap-styled renderer:

```php
class BootstrapRenderer implements Renderer {

    public function render_editor(Layout $layout, array $data = []): string {
        $structure = $layout->get_structure();
        $dataset = $layout->get_dataset();

        $html = '<form method="post" class="needs-validation">';

        foreach ($structure['items'] as $item) {
            $html .= $this->render_item($item, $data, $dataset);
        }

        $html .= '<div class="mt-4">';
        $html .= '<button type="submit" name="action" value="save" ';
        $html .= 'class="btn btn-primary">Save</button>';
        $html .= '</div>';

        $html .= '</form>';
        return $html;
    }

    public function render_list(DataSet $dataset, array $entities): string {
        $html = '<table class="table table-striped">';
        $html .= '<thead><tr>';

        foreach ($dataset->get_fields() as $name => $type) {
            $html .= '<th>' . esc_html(ucfirst($name)) . '</th>';
        }
        $html .= '<th>Actions</th>';
        $html .= '</tr></thead>';

        $html .= '<tbody>';
        foreach ($entities as $entity) {
            $html .= '<tr>';
            foreach ($dataset->get_fields() as $name => $type) {
                $html .= '<td>' . esc_html($entity->get($name)) . '</td>';
            }
            $html .= '<td><a href="?action=edit&id=' . $entity->get_id() . '">Edit</a></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    // ... implement render_item, render_section, render_field, etc.
}
```
