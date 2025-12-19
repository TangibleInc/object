<?php declare( strict_types=1 );

namespace Tangible\DataView;

use Tangible\DataObject\DataSet;

/**
 * Maps extended field types to DataSet types, sanitizers, and database schema.
 */
class FieldTypeRegistry {

    /**
     * Default type mappings.
     *
     * @var array<string, array{dataset: string, sanitizer: callable|string, schema: array, input: string}>
     */
    protected array $types = [];

    public function __construct() {
        $this->register_default_types();
    }

    /**
     * Register the default field types.
     */
    protected function register_default_types(): void {
        $this->types = [
            'string' => [
                'dataset'   => DataSet::TYPE_STRING,
                'sanitizer' => 'sanitize_text_field',
                'schema'    => [ 'type' => 'varchar', 'length' => 255 ],
                'input'     => 'text',
            ],
            'text' => [
                'dataset'   => DataSet::TYPE_STRING,
                'sanitizer' => 'sanitize_textarea_field',
                'schema'    => [ 'type' => 'text' ],
                'input'     => 'textarea',
            ],
            'email' => [
                'dataset'   => DataSet::TYPE_STRING,
                'sanitizer' => 'sanitize_email',
                'schema'    => [ 'type' => 'varchar', 'length' => 255 ],
                'input'     => 'email',
            ],
            'url' => [
                'dataset'   => DataSet::TYPE_STRING,
                'sanitizer' => 'esc_url_raw',
                'schema'    => [ 'type' => 'varchar', 'length' => 512 ],
                'input'     => 'url',
            ],
            'integer' => [
                'dataset'   => DataSet::TYPE_INTEGER,
                'sanitizer' => 'intval',
                'schema'    => [ 'type' => 'int', 'length' => 11 ],
                'input'     => 'number',
            ],
            'boolean' => [
                'dataset'   => DataSet::TYPE_BOOLEAN,
                'sanitizer' => [ $this, 'sanitize_boolean' ],
                'schema'    => [ 'type' => 'tinyint', 'length' => 1, 'default' => 0 ],
                'input'     => 'checkbox',
            ],
            'date' => [
                'dataset'   => DataSet::TYPE_STRING,
                'sanitizer' => 'sanitize_text_field',
                'schema'    => [ 'type' => 'date' ],
                'input'     => 'date',
            ],
            'datetime' => [
                'dataset'   => DataSet::TYPE_STRING,
                'sanitizer' => 'sanitize_text_field',
                'schema'    => [ 'type' => 'datetime' ],
                'input'     => 'datetime-local',
            ],
            'repeater' => [
                'dataset'   => DataSet::TYPE_STRING,
                'sanitizer' => [ $this, 'sanitize_repeater' ],
                'schema'    => [ 'type' => 'longtext' ],
                'input'     => 'repeater',
            ],
        ];
    }

    /**
     * Get the DataSet type for a field type.
     *
     * @param string $type Field type name.
     * @return string DataSet type constant.
     * @throws \InvalidArgumentException If type is not registered.
     */
    public function get_dataset_type( string $type ): string {
        $this->validate_type( $type );
        return $this->types[ $type ]['dataset'];
    }

    /**
     * Get the sanitizer for a field type.
     *
     * @param string $type Field type name.
     * @return callable Sanitizer function.
     * @throws \InvalidArgumentException If type is not registered.
     */
    public function get_sanitizer( string $type ): callable {
        $this->validate_type( $type );
        return $this->types[ $type ]['sanitizer'];
    }

    /**
     * Get the database schema definition for a field type.
     *
     * @param string $type Field type name.
     * @return array Schema array for DatabaseModuleStorage.
     * @throws \InvalidArgumentException If type is not registered.
     */
    public function get_schema( string $type ): array {
        $this->validate_type( $type );
        return $this->types[ $type ]['schema'];
    }

    /**
     * Get the HTML input type for a field type.
     *
     * @param string $type Field type name.
     * @return string HTML input type.
     * @throws \InvalidArgumentException If type is not registered.
     */
    public function get_input_type( string $type ): string {
        $this->validate_type( $type );
        return $this->types[ $type ]['input'];
    }

    /**
     * Check if a type is registered.
     *
     * @param string $type Field type name.
     * @return bool True if type is registered.
     */
    public function has_type( string $type ): bool {
        return isset( $this->types[ $type ] );
    }

    /**
     * Register a custom field type.
     *
     * @param string $name Type name.
     * @param array $config Type configuration with keys: dataset, sanitizer, schema, input.
     */
    public function register_type( string $name, array $config ): void {
        $required = [ 'dataset', 'sanitizer', 'schema', 'input' ];
        foreach ( $required as $key ) {
            if ( ! isset( $config[ $key ] ) ) {
                throw new \InvalidArgumentException(
                    sprintf( 'Field type configuration must include "%s".', $key )
                );
            }
        }
        $this->types[ $name ] = $config;
    }

    /**
     * Validate that a type exists.
     *
     * @param string $type Field type name.
     * @throws \InvalidArgumentException If type is not registered.
     */
    protected function validate_type( string $type ): void {
        if ( ! $this->has_type( $type ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Unknown field type "%s". Available types: %s', $type, implode( ', ', array_keys( $this->types ) ) )
            );
        }
    }

    /**
     * Sanitize boolean value from POST data.
     *
     * @param mixed $value Value to sanitize.
     * @return bool Sanitized boolean.
     */
    public function sanitize_boolean( mixed $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_string( $value ) ) {
            return in_array( strtolower( $value ), [ '1', 'true', 'yes', 'on' ], true );
        }
        return (bool) $value;
    }

    /**
     * Sanitize repeater JSON value from POST data.
     *
     * @param mixed $value Value to sanitize (JSON string or array).
     * @return string Sanitized JSON string.
     */
    public function sanitize_repeater( mixed $value ): string {
        // Handle JSON string input (from Tangible Fields hidden input).
        if ( is_string( $value ) ) {
            $decoded = json_decode( stripslashes( $value ), true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                return wp_json_encode( $this->sanitize_repeater_rows( $decoded ) );
            }
            return '[]';
        }

        // Handle array input.
        if ( is_array( $value ) ) {
            return wp_json_encode( $this->sanitize_repeater_rows( $value ) );
        }

        return '[]';
    }

    /**
     * Sanitize repeater row values.
     *
     * Limited to JSON-compatible primitive types: string, integer, boolean, null.
     *
     * @param array $rows Array of row data.
     * @return array Sanitized rows.
     */
    protected function sanitize_repeater_rows( array $rows ): array {
        return array_values( array_map( function ( $row ) {
            if ( ! is_array( $row ) ) {
                return [];
            }

            $sanitized = [];
            foreach ( $row as $key => $value ) {
                // Preserve the 'key' field used by Tangible Fields for row identification.
                if ( $key === 'key' && is_string( $value ) ) {
                    $sanitized[ $key ] = sanitize_text_field( $value );
                    continue;
                }

                // Sanitize based on value type.
                if ( is_string( $value ) ) {
                    $sanitized[ $key ] = sanitize_text_field( $value );
                } elseif ( is_int( $value ) ) {
                    $sanitized[ $key ] = $value;
                } elseif ( is_float( $value ) ) {
                    $sanitized[ $key ] = $value;
                } elseif ( is_bool( $value ) ) {
                    $sanitized[ $key ] = $value;
                } elseif ( is_null( $value ) ) {
                    $sanitized[ $key ] = null;
                }
                // Skip any other types (arrays, objects) for security.
            }

            return $sanitized;
        }, $rows ) );
    }
}
