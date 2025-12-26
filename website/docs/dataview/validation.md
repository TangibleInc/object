---
sidebar_position: 5
title: Validation
description: Validating data with built-in and custom validators
---

# Validation

DataView provides a flexible validation system through the request handler.

## Adding Validators

Access the handler and add validators:

```php
use Tangible\RequestHandler\Validators;

$view = new DataView([...]);

$handler = $view->get_handler();

$handler
    ->add_validator('title', Validators::required())
    ->add_validator('email', Validators::email());
```

Multiple validators can be added to the same field:

```php
$handler
    ->add_validator('email', Validators::required())
    ->add_validator('email', Validators::email());
```

## Built-in Validators

### Required

Ensures the field has a non-empty value:

```php
$handler->add_validator('name', Validators::required());
```

### Email

Validates email format:

```php
$handler->add_validator('email', Validators::email());
```

### String Length

```php
$handler->add_validator('username', Validators::min_length(3));
$handler->add_validator('username', Validators::max_length(20));
```

### Numeric Range

```php
$handler->add_validator('age', Validators::min(0));
$handler->add_validator('age', Validators::max(120));
```

### Allowed Values

Ensures the value is one of the allowed options:

```php
$handler->add_validator('status', Validators::in(['draft', 'published', 'archived']));
```

## Custom Validators

Create custom validation logic by passing a callable:

```php
use Tangible\RequestHandler\ValidationError;

$handler->add_validator('slug', function($value) {
    if (!preg_match('/^[a-z0-9-]+$/', $value)) {
        return new ValidationError(
            'Slug can only contain lowercase letters, numbers, and hyphens'
        );
    }
    return true;
});
```

### Validator Signature

Custom validators receive:
- `$value` - The field value being validated

They should return:
- `true` - Validation passed
- `ValidationError` - Validation failed with a message

### Example: Unique Value

```php
$handler->add_validator('email', function($value) use ($view) {
    // Check if email already exists
    $existing = $view->get_handler()->list();
    foreach ($existing->get_entities() as $entity) {
        if ($entity->get('email') === $value) {
            return new ValidationError('This email is already registered');
        }
    }
    return true;
});
```

## Validation Errors

When validation fails, errors are available on the result:

```php
$result = $handler->create([
    'name' => '',  // Required field is empty
    'email' => 'invalid',  // Invalid email format
]);

if ($result->is_error()) {
    $errors = $result->get_errors();

    foreach ($errors as $error) {
        echo $error->get_field() . ': ' . $error->get_message();
    }
}
```

In the admin UI, validation errors are automatically displayed above the form.

## Validation Order

Validators run in the order they were added. If a validator fails, subsequent validators for that field still run, and all errors are collected.

```php
$handler
    ->add_validator('password', Validators::required())
    ->add_validator('password', Validators::min_length(8))
    ->add_validator('password', function($value) {
        if (!preg_match('/[A-Z]/', $value)) {
            return new ValidationError('Must contain an uppercase letter');
        }
        return true;
    });
```

All three validators run, and all failing validators contribute to the error list.
