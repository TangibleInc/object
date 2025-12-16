<?php declare( strict_types=1 );

namespace Tangible\DataView;

/**
 * Configuration value object for DataView.
 */
class DataViewConfig {

    public readonly string $slug;
    public readonly string $label;
    public readonly array $fields;
    public readonly string $storage;
    public readonly string $mode;
    public readonly array $ui;
    public readonly string $capability;
    public readonly array $storage_options;

    /**
     * Create a new DataViewConfig instance.
     *
     * @param array $config Configuration array.
     * @throws \InvalidArgumentException If required config is missing.
     */
    public function __construct( array $config ) {
        $this->validate_required( $config );

        $this->slug            = $config['slug'];
        $this->label           = $config['label'];
        $this->fields          = $config['fields'];
        $this->storage         = $config['storage'] ?? 'cpt';
        $this->mode            = $config['mode'] ?? 'plural';
        $this->capability      = $config['capability'] ?? 'manage_options';
        $this->storage_options = $config['storage_options'] ?? [];

        $this->ui = array_merge(
            [
                'menu_page'  => $this->slug,
                'menu_label' => $this->label,
                'parent'     => null,
                'icon'       => 'dashicons-admin-generic',
                'position'   => null,
            ],
            $config['ui'] ?? []
        );

        $this->validate();
    }

    /**
     * Validate that required configuration keys are present.
     *
     * @param array $config Configuration array.
     * @throws \InvalidArgumentException If required key is missing.
     */
    protected function validate_required( array $config ): void {
        $required = [ 'slug', 'label', 'fields' ];
        foreach ( $required as $key ) {
            if ( ! isset( $config[ $key ] ) ) {
                throw new \InvalidArgumentException(
                    sprintf( 'DataView configuration must include "%s".', $key )
                );
            }
        }
    }

    /**
     * Validate configuration values.
     *
     * @throws \InvalidArgumentException If configuration is invalid.
     */
    protected function validate(): void {
        if ( ! preg_match( '/^[a-z_][a-z0-9_]*$/', $this->slug ) ) {
            throw new \InvalidArgumentException(
                'DataView slug must be lowercase alphanumeric with underscores, starting with a letter or underscore.'
            );
        }

        if ( empty( $this->fields ) ) {
            throw new \InvalidArgumentException( 'DataView must have at least one field.' );
        }

        if ( ! in_array( $this->storage, [ 'cpt', 'database', 'option' ], true ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Invalid storage type "%s". Must be one of: cpt, database, option.', $this->storage )
            );
        }

        if ( ! in_array( $this->mode, [ 'plural', 'singular' ], true ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Invalid mode "%s". Must be one of: plural, singular.', $this->mode )
            );
        }
    }

    /**
     * Check if this is a plural (multi-entity) DataView.
     *
     * @return bool True if plural mode.
     */
    public function is_plural(): bool {
        return $this->mode === 'plural';
    }

    /**
     * Check if this is a singular (single-entity) DataView.
     *
     * @return bool True if singular mode.
     */
    public function is_singular(): bool {
        return $this->mode === 'singular';
    }

    /**
     * Get the admin menu page slug.
     *
     * @return string Menu page slug.
     */
    public function get_menu_page(): string {
        return $this->ui['menu_page'];
    }

    /**
     * Get the admin menu label.
     *
     * @return string Menu label.
     */
    public function get_menu_label(): string {
        return $this->ui['menu_label'];
    }

    /**
     * Get the parent menu slug (if submenu).
     *
     * @return string|null Parent menu slug or null for top-level.
     */
    public function get_parent_menu(): ?string {
        return $this->ui['parent'];
    }

    /**
     * Get the menu icon.
     *
     * @return string Menu icon (dashicons class or base64 SVG).
     */
    public function get_icon(): string {
        return $this->ui['icon'];
    }

    /**
     * Get the menu position.
     *
     * @return int|null Menu position or null for default.
     */
    public function get_position(): ?int {
        return $this->ui['position'];
    }
}
