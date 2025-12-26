---
sidebar_position: 6
title: Lifecycle Hooks
description: React to create, update, and delete operations
---

# Lifecycle Hooks

Lifecycle hooks let you execute custom logic before or after CRUD operations.

## Plural Mode Hooks

### before_create

Modify data before creating an entity:

```php
$handler->before_create(function(array $data) {
    $data['created_at'] = current_time('mysql');
    $data['created_by'] = get_current_user_id();
    return $data;
});
```

### after_create

React after an entity is created:

```php
$handler->after_create(function($entity) {
    // Send notification
    wp_mail(
        get_option('admin_email'),
        'New entry created',
        'A new ' . $entity->get('title') . ' was created.'
    );

    // Clear cache
    delete_transient('my_items_list');
});
```

### before_update

Modify data before updating. Receives the entity and new data:

```php
$handler->before_update(function($entity, array $data) {
    $data['updated_at'] = current_time('mysql');

    // Track who made changes
    $data['updated_by'] = get_current_user_id();

    return $data;
});
```

### after_update

React after an update:

```php
$handler->after_update(function($entity) {
    // Clear specific cache
    delete_transient('item_' . $entity->get_id());

    // Log the change
    do_action('my_plugin_item_updated', $entity);
});
```

### before_delete

Control whether deletion proceeds. Return `false` to cancel:

```php
$handler->before_delete(function($entity) {
    // Prevent deletion of protected items
    if ($entity->get('is_protected')) {
        return false;
    }

    // Or check user permissions
    if (!current_user_can('delete_others_posts')) {
        return false;
    }

    return true;
});
```

### after_delete

Clean up after deletion. Receives the deleted ID:

```php
$handler->after_delete(function($id) {
    // Clean up related data
    global $wpdb;
    $wpdb->delete('my_related_table', ['parent_id' => $id]);

    // Clear caches
    delete_transient('my_items_list');
});
```

## Singular Mode Hooks

Singular mode (settings pages) has different hook signatures since there's no entity concept.

### before_update

Receives current data and new data:

```php
$handler->before_update(function(array $current, array $data) {
    // Detect changes
    if ($current['api_key'] !== $data['api_key']) {
        // API key changed, invalidate tokens
        delete_transient('my_api_token');
    }

    // Clear cache if cache settings changed
    if ($current['cache_ttl'] !== $data['cache_ttl']) {
        wp_cache_flush();
    }

    return $data;
});
```

### after_update

Receives the updated data:

```php
$handler->after_update(function(array $data) {
    // Log settings change
    if ($data['debug_mode']) {
        error_log('Plugin settings updated: ' . json_encode($data));
    }

    // Trigger action for other plugins
    do_action('my_plugin_settings_updated', $data);
});
```

## Chaining Hooks

Hooks can be chained for cleaner code:

```php
$handler
    ->before_create(function($data) {
        $data['created_at'] = current_time('mysql');
        return $data;
    })
    ->after_create(function($entity) {
        delete_transient('items_list');
    })
    ->before_update(function($entity, $data) {
        $data['updated_at'] = current_time('mysql');
        return $data;
    })
    ->after_update(function($entity) {
        delete_transient('item_' . $entity->get_id());
    });
```

## Common Patterns

### Auto-generate Slugs

```php
$handler->before_create(function($data) {
    if (empty($data['slug'])) {
        $data['slug'] = sanitize_title($data['title']);
    }
    return $data;
});
```

### Initialize Counters

```php
$handler->before_create(function($data) {
    $data['view_count'] = 0;
    $data['like_count'] = 0;
    return $data;
});
```

### Send Notifications

```php
$handler->after_create(function($entity) {
    $user = get_user_by('id', $entity->get('user_id'));
    if ($user) {
        wp_mail(
            $user->user_email,
            'Your submission was received',
            'Thank you for submitting ' . $entity->get('title')
        );
    }
});
```

### Cascade Deletes

```php
$handler->after_delete(function($id) {
    // Delete child items
    $children = get_posts([
        'post_type'  => 'child_item',
        'meta_key'   => 'parent_id',
        'meta_value' => $id,
    ]);

    foreach ($children as $child) {
        wp_delete_post($child->ID, true);
    }
});
```
