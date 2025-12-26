---
sidebar_position: 3
title: Invoice Manager
description: Complex example with repeater fields
---

# Invoice Manager Example

This example demonstrates a complete invoice management system using TangibleFieldsRenderer with repeater fields for line items.

## Complete Implementation

```php
use Tangible\DataView\DataView;
use Tangible\Renderer\TangibleFieldsRenderer;
use Tangible\RequestHandler\Validators;
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'invoice',
        'label'  => [
            'singular' => __('Invoice', 'my-plugin'),
            'plural'   => __('Invoices', 'my-plugin'),
        ],
        'fields' => [
            // Customer info
            'customer_name' => [
                'type'        => 'string',
                'label'       => __('Customer Name', 'my-plugin'),
                'placeholder' => 'Enter customer name',
            ],
            'customer_email' => [
                'type'        => 'email',
                'label'       => __('Customer Email', 'my-plugin'),
                'placeholder' => 'customer@example.com',
            ],

            // Invoice details
            'invoice_date' => [
                'type'  => 'date',
                'label' => __('Invoice Date', 'my-plugin'),
            ],
            'due_date' => [
                'type'  => 'date',
                'label' => __('Due Date', 'my-plugin'),
            ],

            // Line items (repeater)
            'line_items' => [
                'type'         => 'repeater',
                'label'        => __('Line Items', 'my-plugin'),
                'description'  => __('Add products or services to this invoice', 'my-plugin'),
                'layout'       => 'table',
                'min_rows'     => 1,
                'max_rows'     => 100,
                'button_label' => __('Add Line Item', 'my-plugin'),
                'sub_fields'   => [
                    [
                        'name'        => 'description',
                        'type'        => 'string',
                        'label'       => __('Description', 'my-plugin'),
                        'placeholder' => 'Product or service',
                    ],
                    [
                        'name'  => 'quantity',
                        'type'  => 'integer',
                        'label' => __('Qty', 'my-plugin'),
                        'min'   => 1,
                    ],
                    [
                        'name'  => 'unit_price',
                        'type'  => 'integer',
                        'label' => __('Unit Price (cents)', 'my-plugin'),
                        'min'   => 0,
                    ],
                    [
                        'name'  => 'taxable',
                        'type'  => 'boolean',
                        'label' => __('Tax', 'my-plugin'),
                    ],
                ],
                'default' => [
                    ['description' => '', 'quantity' => 1, 'unit_price' => 0, 'taxable' => true],
                ],
            ],

            // Notes
            'notes' => [
                'type'        => 'text',
                'label'       => __('Notes', 'my-plugin'),
                'rows'        => 4,
                'description' => 'Additional notes to include on the invoice',
            ],

            // Calculated fields (read-only in UI)
            'subtotal'  => 'integer',
            'tax_total' => 'integer',
            'total'     => 'integer',

            // Status
            'is_paid' => [
                'type'  => 'boolean',
                'label' => __('Paid', 'my-plugin'),
            ],
        ],
        'ui' => [
            'menu_label' => __('Invoices', 'my-plugin'),
            'icon'       => 'dashicons-media-spreadsheet',
        ],
    ]);

    // Use TangibleFieldsRenderer for rich UI
    $view->set_renderer(new TangibleFieldsRenderer());

    // Custom layout
    $view->set_layout(function(Layout $layout) {
        $layout->section('Customer Information', function(Section $s) {
            $s->columns(2);
            $s->field('customer_name');
            $s->field('customer_email');
        });

        $layout->section('Invoice Details', function(Section $s) {
            $s->columns(2);
            $s->field('invoice_date');
            $s->field('due_date');
        });

        $layout->section('Line Items', function(Section $s) {
            $s->field('line_items');
        });

        $layout->section('Summary', function(Section $s) {
            $s->columns(3);
            $s->field('subtotal')->readonly();
            $s->field('tax_total')->readonly();
            $s->field('total')->readonly();
        });

        $layout->section('Additional Info', function(Section $s) {
            $s->field('notes');
        });

        $layout->sidebar(function(Sidebar $sb) {
            $sb->field('is_paid');
            $sb->actions(['save', 'delete']);
        });
    });

    // Validation
    $view->get_handler()
        ->add_validator('customer_name', Validators::required())
        ->add_validator('customer_email', Validators::required())
        ->add_validator('customer_email', Validators::email())
        ->add_validator('invoice_date', Validators::required());

    // Calculate totals before saving
    $view->get_handler()
        ->before_create(function($data) {
            return calculate_invoice_totals($data);
        })
        ->before_update(function($entity, $data) {
            return calculate_invoice_totals($data);
        });

    $view->register();
});

/**
 * Calculate invoice totals from line items.
 */
function calculate_invoice_totals(array $data): array {
    $line_items = json_decode($data['line_items'] ?? '[]', true);
    $subtotal = 0;
    $tax_total = 0;
    $tax_rate = 0.08; // 8% tax rate

    foreach ($line_items as $item) {
        $line_total = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
        $subtotal += $line_total;

        if (!empty($item['taxable'])) {
            $tax_total += (int) round($line_total * $tax_rate);
        }
    }

    $data['subtotal'] = $subtotal;
    $data['tax_total'] = $tax_total;
    $data['total'] = $subtotal + $tax_total;

    return $data;
}
```

## Display Invoice on Frontend

```php
function display_invoice($invoice_id) {
    // Get the DataView handler
    $view = get_invoice_dataview();
    $result = $view->get_handler()->read($invoice_id);

    if ($result->is_error()) {
        return '<p>Invoice not found.</p>';
    }

    $entity = $result->get_entity();
    $line_items = json_decode($entity->get('line_items'), true);

    ob_start();
    ?>
    <div class="invoice">
        <h1>Invoice</h1>

        <div class="customer">
            <strong><?php echo esc_html($entity->get('customer_name')); ?></strong><br>
            <?php echo esc_html($entity->get('customer_email')); ?>
        </div>

        <div class="dates">
            <p>Invoice Date: <?php echo esc_html($entity->get('invoice_date')); ?></p>
            <p>Due Date: <?php echo esc_html($entity->get('due_date')); ?></p>
        </div>

        <table class="line-items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($line_items as $item): ?>
                    <?php $line_total = $item['quantity'] * $item['unit_price']; ?>
                    <tr>
                        <td><?php echo esc_html($item['description']); ?></td>
                        <td><?php echo esc_html($item['quantity']); ?></td>
                        <td><?php echo format_price($item['unit_price']); ?></td>
                        <td><?php echo format_price($line_total); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Subtotal</td>
                    <td><?php echo format_price($entity->get('subtotal')); ?></td>
                </tr>
                <tr>
                    <td colspan="3">Tax</td>
                    <td><?php echo format_price($entity->get('tax_total')); ?></td>
                </tr>
                <tr class="total">
                    <td colspan="3"><strong>Total</strong></td>
                    <td><strong><?php echo format_price($entity->get('total')); ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if ($entity->get('notes')): ?>
            <div class="notes">
                <h3>Notes</h3>
                <p><?php echo esc_html($entity->get('notes')); ?></p>
            </div>
        <?php endif; ?>

        <div class="status">
            <?php if ($entity->get('is_paid')): ?>
                <span class="paid">PAID</span>
            <?php else: ?>
                <span class="unpaid">UNPAID</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function format_price($cents) {
    return '$' . number_format($cents / 100, 2);
}
```

## Simple FAQ with Repeaters

A simpler repeater example:

```php
use Tangible\DataView\DataView;
use Tangible\Renderer\TangibleFieldsRenderer;

add_action('admin_menu', function() {
    $view = new DataView([
        'slug'   => 'faq',
        'label'  => 'FAQ',
        'fields' => [
            'title' => 'string',
            'questions' => [
                'type'       => 'repeater',
                'layout'     => 'block',
                'sub_fields' => [
                    ['name' => 'question', 'type' => 'string', 'label' => 'Question'],
                    ['name' => 'answer', 'type' => 'string', 'label' => 'Answer'],
                ],
            ],
        ],
    ]);

    $view->set_renderer(new TangibleFieldsRenderer());
    $view->register();
});
```
