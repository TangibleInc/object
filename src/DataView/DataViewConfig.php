<?php declare( strict_types=1 );

namespace Tangible\DataView;

/**
 * Configuration value object for DataView.
 */
class DataViewConfig {

    public readonly string $slug;
    public readonly array $fields;
    public readonly string $storage;
    public readonly string $mode;
    public readonly array $ui;
    public readonly string $capability;
    public readonly array $storage_options;

    /**
     * Singular label (for backward compatibility).
     *
     * @var string
     * @deprecated Use $labels['singular'] or get_singular_label() instead.
     */
    public readonly string $label;

    /**
     * Labels configuration.
     *
     * Always contains at least 'singular' key. May also contain:
     * - plural: Plural form (auto-generated if not provided)
     * - add_new: "Add New" button text
     * - add_new_item: "Add New {Item}" page title
     * - edit_item: "Edit {Item}" page title
     * - new_item: "New {Item}" text
     * - view_item: "View {Item}" text
     * - view_items: "View {Items}" text
     * - search_items: "Search {Items}" text
     * - not_found: "No {items} found" text
     * - not_found_in_trash: "No {items} found in Trash" text
     * - all_items: "All {Items}" text
     * - archives: "{Item} Archives" text
     * - menu_name: Menu name (defaults to plural)
     * - settings: "{Item} Settings" text (for singular mode)
     *
     * @var array<string, string>
     */
    public readonly array $labels;

    /**
     * Create a new DataViewConfig instance.
     *
     * @param array $config Configuration array.
     * @throws \InvalidArgumentException If required config is missing.
     */
    public function __construct( array $config ) {
        $this->validate_required( $config );

        $this->slug            = $config['slug'];
        $this->fields          = $config['fields'];
        $this->storage         = $config['storage'] ?? 'cpt';
        $this->mode            = $config['mode'] ?? 'plural';
        $this->capability      = $config['capability'] ?? 'manage_options';
        $this->storage_options = $config['storage_options'] ?? [];

        // Normalize label to labels array.
        $this->labels = $this->normalize_labels( $config['label'] );
        $this->label  = $this->labels['singular']; // Backward compatibility.

        $this->ui = array_merge(
            [
                'menu_page'  => $this->slug,
                'menu_label' => $this->labels['plural'] ?? $this->labels['singular'],
                'parent'     => null,
                'icon'       => 'dashicons-admin-generic',
                'position'   => null,
            ],
            $config['ui'] ?? []
        );

        $this->validate();
    }

    /**
     * Normalize label configuration to labels array.
     *
     * Accepts either:
     * - A string: Used as singular, plural is auto-generated
     * - An array with 'singular' key, optionally 'plural' and other label overrides
     *
     * @param string|array $label Label configuration.
     * @return array Normalized labels array.
     */
    protected function normalize_labels( string|array $label ): array {
        if ( is_string( $label ) ) {
            return [ 'singular' => $label ];
        }

        if ( ! isset( $label['singular'] ) ) {
            throw new \InvalidArgumentException(
                'DataView label array must include "singular" key.'
            );
        }

        return $label;
    }

    /**
     * Get the singular label.
     *
     * @return string Singular label.
     */
    public function get_singular_label(): string {
        return $this->labels['singular'];
    }

    /**
     * Get the plural label.
     *
     * If not explicitly set, returns null (caller should auto-generate).
     *
     * @return string|null Plural label or null if not set.
     */
    public function get_plural_label(): ?string {
        return $this->labels['plural'] ?? null;
    }

    /**
     * Get a specific label with fallback.
     *
     * @param string $key Label key.
     * @param string|null $fallback Fallback value if not set.
     * @return string|null Label value or fallback.
     */
    public function get_label( string $key, ?string $fallback = null ): ?string {
        return $this->labels[ $key ] ?? $fallback;
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
