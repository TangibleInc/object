<?php declare( strict_types=1 );

namespace Tangible\Renderer;

use Tangible\DataObject\DataSet;
use Tangible\EditorLayout\Layout;

/**
 * Renderer implementation using Tangible Fields framework.
 *
 * Provides rich UI components including repeaters, date pickers,
 * and other advanced field types.
 */
class TangibleFieldsRenderer implements Renderer {

    /**
     * The layout being rendered.
     */
    protected Layout $layout;

    /**
     * The entity data being rendered.
     */
    protected array $data;

    /**
     * Field configurations (including repeater sub-fields).
     */
    protected array $field_configs = [];

    /**
     * Whether assets have been enqueued.
     */
    protected bool $enqueued = false;

    /**
     * Type mapping: DataView type => Tangible Fields type.
     */
    protected array $type_map = [
        'string'   => 'text',
        'text'     => 'textarea',
        'email'    => 'text',
        'url'      => 'text',
        'integer'  => 'number',
        'boolean'  => 'switch',
        'date'     => 'date_picker',
        'datetime' => 'date_picker',
        'repeater' => 'repeater',
    ];

    /**
     * Sub-field type mapping for repeaters.
     * Limited to JSON-compatible primitives.
     */
    protected array $sub_field_type_map = [
        'string'  => 'text',
        'integer' => 'number',
        'boolean' => 'switch',
    ];

    /**
     * Set field configurations.
     *
     * @param array $configs Field configurations including repeater sub-fields.
     */
    public function set_field_configs( array $configs ): void {
        $this->field_configs = $configs;
    }

    /**
     * Render an editor form for an entity.
     *
     * @param Layout $layout The editor layout structure.
     * @param array  $data   The entity data to populate the form.
     * @return string The rendered HTML.
     */
    public function render_editor( Layout $layout, array $data ): string {
        $this->ensure_tangible_fields_loaded();

        $this->layout = $layout;
        $this->data   = $data;

        $structure = $layout->get_structure();
        $html      = '<div class="tangible-fields-editor">';

        // Render main content items.
        foreach ( $structure['items'] as $item ) {
            $html .= $this->render_item( $item );
        }

        // Render sidebar if present.
        if ( isset( $structure['sidebar'] ) ) {
            $html .= $this->render_sidebar( $structure['sidebar'] );
        }

        $html .= '</div>';

        // Schedule asset enqueueing.
        $this->schedule_enqueue();

        return $html;
    }

    /**
     * Render a structure item (section or tabs).
     *
     * @param array $item The item structure.
     * @return string The rendered HTML.
     */
    protected function render_item( array $item ): string {
        return match ( $item['type'] ) {
            'section' => $this->render_section( $item ),
            'tabs'    => $this->render_tabs( $item ),
            default   => '',
        };
    }

    /**
     * Render a section.
     *
     * @param array $section The section structure.
     * @return string The rendered HTML.
     */
    protected function render_section( array $section ): string {
        $fields = tangible_fields();

        $section_fields = [];
        foreach ( $section['fields'] as $field ) {
            $section_fields[] = $this->build_field_config( $field );
        }

        // Render nested items' fields as well.
        if ( isset( $section['items'] ) ) {
            foreach ( $section['items'] as $nested_item ) {
                $section_fields = array_merge(
                    $section_fields,
                    $this->extract_fields_from_item( $nested_item )
                );
            }
        }

        // Use accordion for collapsible sections.
        $section_id = sanitize_key( $section['label'] ) . '_' . uniqid();

        return $fields->render_field( $section_id, [
            'type'   => 'accordion',
            'label'  => $section['label'],
            'value'  => true, // Expanded by default.
            'fields' => $section_fields,
            ...$this->get_memory_store_callbacks(),
        ] );
    }

    /**
     * Build field configs from a nested item, preserving structure.
     *
     * For sections, this creates an accordion config that includes the label.
     * For tabs, this extracts the fields from each tab.
     *
     * @param array $item The item structure.
     * @return array Array of field configs (may include accordion wrappers).
     */
    protected function extract_fields_from_item( array $item ): array {
        $fields = [];

        if ( $item['type'] === 'section' ) {
            // Build section fields including any nested items.
            $section_fields = [];
            foreach ( $item['fields'] ?? [] as $field ) {
                $section_fields[] = $this->build_field_config( $field );
            }
            foreach ( $item['items'] ?? [] as $nested ) {
                $section_fields = array_merge( $section_fields, $this->extract_fields_from_item( $nested ) );
            }

            // Wrap in an accordion to preserve the section label.
            $fields[] = [
                'type'   => 'accordion',
                'label'  => $item['label'],
                'value'  => true, // Expanded by default.
                'fields' => $section_fields,
            ];
        } elseif ( $item['type'] === 'tabs' ) {
            foreach ( $item['tabs'] ?? [] as $tab ) {
                foreach ( $tab['fields'] ?? [] as $field ) {
                    $fields[] = $this->build_field_config( $field );
                }
                // Include nested items from tabs.
                foreach ( $tab['items'] ?? [] as $nested ) {
                    $fields = array_merge( $fields, $this->extract_fields_from_item( $nested ) );
                }
            }
        }

        return $fields;
    }

    /**
     * Render a tabs container.
     *
     * @param array $tabs_structure The tabs structure.
     * @return string The rendered HTML.
     */
    protected function render_tabs( array $tabs_structure ): string {
        $fields = tangible_fields();

        $tabs = [];
        foreach ( $tabs_structure['tabs'] as $tab ) {
            $tab_key    = sanitize_key( $tab['label'] );
            $tab_fields = [];

            foreach ( $tab['fields'] as $field ) {
                $tab_fields[] = $this->build_field_config( $field );
            }

            // Include nested items.
            if ( isset( $tab['items'] ) ) {
                foreach ( $tab['items'] as $nested_item ) {
                    $tab_fields = array_merge(
                        $tab_fields,
                        $this->extract_fields_from_item( $nested_item )
                    );
                }
            }

            $tabs[ $tab_key ] = [
                'label'  => $tab['label'],
                'fields' => $tab_fields,
            ];
        }

        $tabs_id = 'tabs_' . uniqid();

        return $fields->render_field( $tabs_id, [
            'type' => 'tab',
            'tabs' => $tabs,
            ...$this->get_memory_store_callbacks(),
        ] );
    }

    /**
     * Render the sidebar.
     *
     * @param array $sidebar The sidebar structure.
     * @return string The rendered HTML.
     */
    protected function render_sidebar( array $sidebar ): string {
        $html = '<aside class="tangible-fields-sidebar">';

        // Render sidebar fields.
        foreach ( $sidebar['fields'] as $field ) {
            $html .= $this->render_field( $field );
        }

        // Render actions.
        if ( isset( $sidebar['actions'] ) ) {
            $html .= '<div class="tangible-fields-actions">';
            foreach ( $sidebar['actions'] as $action ) {
                $html .= $this->render_action( $action );
            }
            $html .= '</div>';
        }

        $html .= '</aside>';

        return $html;
    }

    /**
     * Render an action button.
     *
     * Action buttons are rendered as plain HTML for server-side functionality,
     * ensuring they work regardless of JavaScript state.
     *
     * @param string $action The action identifier.
     * @return string The rendered HTML.
     */
    protected function render_action( string $action ): string {
        $config = match ( $action ) {
            'save' => [
                'label'   => __( 'Save', 'tangible-object' ),
                'type'    => 'submit',
                'name'    => 'action',
                'value'   => 'save',
                'class'   => 'button button-primary',
                'onclick' => '',
            ],
            'delete' => [
                'label'   => __( 'Delete', 'tangible-object' ),
                'type'    => 'submit',
                'name'    => 'action',
                'value'   => 'delete',
                'class'   => 'button button-link-delete',
                'onclick' => "return confirm('" . esc_js( __( 'Are you sure you want to delete this item?', 'tangible-object' ) ) . "');",
            ],
            default => [
                'label'   => ucfirst( $action ),
                'type'    => 'button',
                'name'    => 'action',
                'value'   => $action,
                'class'   => 'button',
                'onclick' => '',
            ],
        };

        $onclick_attr = ! empty( $config['onclick'] )
            ? ' onclick="' . esc_attr( $config['onclick'] ) . '"'
            : '';

        return sprintf(
            '<button type="%s" name="%s" value="%s" class="%s"%s>%s</button>',
            esc_attr( $config['type'] ),
            esc_attr( $config['name'] ),
            esc_attr( $config['value'] ),
            esc_attr( $config['class'] ),
            $onclick_attr,
            esc_html( $config['label'] )
        );
    }

    /**
     * Render a single field.
     *
     * @param array $field The field structure from Layout.
     * @return string The rendered HTML.
     */
    protected function render_field( array $field ): string {
        $slug   = $field['slug'];
        $type   = $this->get_field_type( $slug );
        $value  = $this->data[ $slug ] ?? $this->get_default_value( $slug );
        $config = $this->field_configs[ $slug ] ?? [];

        if ( $type === 'repeater' ) {
            return $this->render_repeater_field( $field, $value, $config );
        }

        return $this->render_simple_field( $field, $type, $value );
    }

    /**
     * Render a simple (non-repeater) field.
     *
     * @param array  $field The field structure.
     * @param string $type  The DataView field type.
     * @param mixed  $value The field value.
     * @return string The rendered HTML.
     */
    protected function render_simple_field( array $field, string $type, mixed $value ): string {
        $fields         = tangible_fields();
        $slug           = $field['slug'];
        $tf_type        = $this->get_tangible_fields_type( $type );
        $config         = $this->field_configs[ $slug ] ?? [];
        $label          = $config['label'] ?? $field['label'] ?? ucfirst( str_replace( '_', ' ', $slug ) );

        $field_args = [
            'type'        => $tf_type,
            'name'        => $slug,
            'label'       => $label,
            'value'       => $this->format_value_for_field( $value, $type ),
            'description' => $field['help'] ?? $config['description'] ?? '',
            'placeholder' => $field['placeholder'] ?? $config['placeholder'] ?? '',
            ...$this->get_memory_store_callbacks(),
        ];

        // Add type-specific options.
        $field_args = $this->add_type_specific_options( $field_args, $type, $field, $config );

        // Handle readonly.
        if ( ! empty( $field['readonly'] ) ) {
            $field_args['read_only'] = true;
        }

        return $fields->render_field( $slug, $field_args );
    }

    /**
     * Render a repeater field.
     *
     * @param array $field  The field structure.
     * @param mixed $value  The field value (JSON string or array).
     * @param array $config The field configuration.
     * @return string The rendered HTML.
     */
    protected function render_repeater_field( array $field, mixed $value, array $config ): string {
        $fields     = tangible_fields();
        $slug       = $field['slug'];
        $label      = $config['label'] ?? $field['label'] ?? ucfirst( str_replace( '_', ' ', $slug ) );
        $sub_fields = $this->map_sub_fields( $config['sub_fields'] ?? [] );

        // Ensure value is a JSON string.
        if ( is_array( $value ) ) {
            $value = wp_json_encode( $value );
        } elseif ( empty( $value ) ) {
            // Use default value if provided.
            $default = $config['default'] ?? [];
            $value   = wp_json_encode( $default );
        }

        $field_args = [
            'type'       => 'repeater',
            'name'       => $slug,
            'label'      => $label,
            'value'      => $value,
            'sub_fields' => $sub_fields,
            'layout'     => $config['layout'] ?? 'table',
            ...$this->get_memory_store_callbacks(),
        ];

        // Optional repeater settings.
        if ( isset( $config['max_rows'] ) ) {
            $field_args['maxlength'] = $config['max_rows'];
        }

        if ( isset( $config['min_rows'] ) ) {
            $field_args['minlength'] = $config['min_rows'];
        }

        if ( isset( $config['button_label'] ) ) {
            $field_args['new_item'] = $config['button_label'];
        }

        if ( isset( $config['description'] ) ) {
            $field_args['description'] = $config['description'];
        }

        return $fields->render_field( $slug, $field_args );
    }

    /**
     * Map sub-field definitions to Tangible Fields format.
     *
     * @param array $sub_fields Sub-field definitions.
     * @return array Tangible Fields sub-field configs.
     */
    protected function map_sub_fields( array $sub_fields ): array {
        $mapped = [];

        foreach ( $sub_fields as $sub_field ) {
            $type    = $sub_field['type'] ?? 'string';
            $tf_type = $this->sub_field_type_map[ $type ] ?? 'text';

            $mapped_field = [
                'type'  => $tf_type,
                'name'  => $sub_field['name'],
                'label' => $sub_field['label'] ?? ucfirst( str_replace( '_', ' ', $sub_field['name'] ) ),
            ];

            // Add optional properties.
            if ( isset( $sub_field['placeholder'] ) ) {
                $mapped_field['placeholder'] = $sub_field['placeholder'];
            }

            if ( isset( $sub_field['description'] ) ) {
                $mapped_field['description'] = $sub_field['description'];
            }

            // Type-specific options.
            if ( $tf_type === 'number' ) {
                if ( isset( $sub_field['min'] ) ) {
                    $mapped_field['min'] = $sub_field['min'];
                }
                if ( isset( $sub_field['max'] ) ) {
                    $mapped_field['max'] = $sub_field['max'];
                }
            }

            if ( $tf_type === 'switch' ) {
                $mapped_field['value_on']  = true;
                $mapped_field['value_off'] = false;
            }

            $mapped[] = $mapped_field;
        }

        return $mapped;
    }

    /**
     * Build field config for Tangible Fields from Layout field.
     *
     * @param array $field The field structure from Layout.
     * @return array Tangible Fields field config.
     */
    protected function build_field_config( array $field ): array {
        $slug   = $field['slug'];
        $type   = $this->get_field_type( $slug );
        $value  = $this->data[ $slug ] ?? $this->get_default_value( $slug );
        $config = $this->field_configs[ $slug ] ?? [];
        $label  = $config['label'] ?? $field['label'] ?? ucfirst( str_replace( '_', ' ', $slug ) );

        if ( $type === 'repeater' ) {
            $sub_fields = $this->map_sub_fields( $config['sub_fields'] ?? [] );

            if ( is_array( $value ) ) {
                $value = wp_json_encode( $value );
            } elseif ( empty( $value ) ) {
                $default = $config['default'] ?? [];
                $value   = wp_json_encode( $default );
            }

            $field_config = [
                'type'       => 'repeater',
                'name'       => $slug,
                'label'      => $label,
                'value'      => $value,
                'sub_fields' => $sub_fields,
                'layout'     => $config['layout'] ?? 'table',
            ];

            if ( isset( $config['max_rows'] ) ) {
                $field_config['maxlength'] = $config['max_rows'];
            }

            return $field_config;
        }

        $tf_type = $this->get_tangible_fields_type( $type );

        $field_config = [
            'type'        => $tf_type,
            'name'        => $slug,
            'label'       => $label,
            'value'       => $this->format_value_for_field( $value, $type ),
            'description' => $field['help'] ?? $config['description'] ?? '',
            'placeholder' => $field['placeholder'] ?? $config['placeholder'] ?? '',
        ];

        // Add type-specific options.
        $field_config = $this->add_type_specific_options( $field_config, $type, $field, $config );

        if ( ! empty( $field['readonly'] ) ) {
            $field_config['read_only'] = true;
        }

        return $field_config;
    }

    /**
     * Add type-specific options to field config.
     *
     * @param array  $field_args The base field args.
     * @param string $type       The DataView field type.
     * @param array  $field      The field structure from Layout.
     * @param array  $config     The field configuration.
     * @return array Modified field args.
     */
    protected function add_type_specific_options( array $field_args, string $type, array $field, array $config ): array {
        switch ( $type ) {
            case 'integer':
                if ( isset( $config['min'] ) ) {
                    $field_args['min'] = $config['min'];
                }
                if ( isset( $config['max'] ) ) {
                    $field_args['max'] = $config['max'];
                }
                break;

            case 'boolean':
                $field_args['value_on']  = true;
                $field_args['value_off'] = false;
                break;

            case 'date':
            case 'datetime':
                if ( isset( $config['future_only'] ) ) {
                    $field_args['future_only'] = $config['future_only'];
                }
                break;

            case 'text':
                // Textarea might have rows config.
                if ( isset( $config['rows'] ) ) {
                    $field_args['rows'] = $config['rows'];
                }
                break;
        }

        return $field_args;
    }

    /**
     * Format a value for field rendering.
     *
     * @param mixed  $value The raw value.
     * @param string $type  The DataView field type.
     * @return mixed The formatted value.
     */
    protected function format_value_for_field( mixed $value, string $type ): mixed {
        if ( $value === null ) {
            return '';
        }

        return match ( $type ) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            default   => $value,
        };
    }

    /**
     * Get the field type from the DataSet.
     *
     * @param string $slug The field slug.
     * @return string The field type.
     */
    protected function get_field_type( string $slug ): string {
        // First check field_configs for complex types like repeater.
        if ( isset( $this->field_configs[ $slug ]['type'] ) ) {
            return $this->field_configs[ $slug ]['type'];
        }

        // Fall back to DataSet.
        $fields = $this->layout->get_dataset()->get_fields();
        return $fields[ $slug ]['type'] ?? 'string';
    }

    /**
     * Get the Tangible Fields type for a DataView type.
     *
     * @param string $type The DataView field type.
     * @return string The Tangible Fields type.
     */
    protected function get_tangible_fields_type( string $type ): string {
        return $this->type_map[ $type ] ?? 'text';
    }

    /**
     * Get default value for a field.
     *
     * @param string $slug The field slug.
     * @return mixed The default value.
     */
    protected function get_default_value( string $slug ): mixed {
        $config = $this->field_configs[ $slug ] ?? [];
        return $config['default'] ?? null;
    }

    /**
     * Get memory store callbacks for Tangible Fields.
     *
     * We use memory store because DataView handles persistence.
     *
     * @return array Store and permission callbacks.
     */
    protected function get_memory_store_callbacks(): array {
        $fields = tangible_fields();

        return [
            ...$fields->_store_callbacks['memory'](),
            ...$fields->_permission_callbacks( [
                'store' => [ 'always_allow' ],
                'fetch' => [ 'always_allow' ],
            ] ),
        ];
    }

    /**
     * Render a list of entities.
     *
     * For list rendering, we use simple HTML tables since Tangible Fields
     * is primarily designed for form editing, not data display.
     *
     * @param DataSet $dataset  The dataset defining the fields.
     * @param array   $entities The entities to display.
     * @return string The rendered HTML.
     */
    public function render_list( DataSet $dataset, array $entities ): string {
        $fields      = $dataset->get_fields();
        $field_slugs = array_keys( $fields );

        $html = '<table class="wp-list-table widefat fixed striped">';

        // Header row.
        $html .= '<thead><tr>';
        foreach ( $field_slugs as $slug ) {
            $label = $this->field_configs[ $slug ]['label']
                ?? ucfirst( str_replace( '_', ' ', $slug ) );
            $html .= '<th>' . esc_html( $label ) . '</th>';
        }
        $html .= '</tr></thead>';

        // Data rows.
        $html .= '<tbody>';
        foreach ( $entities as $entity ) {
            $html .= '<tr>';
            foreach ( $field_slugs as $slug ) {
                $value = $entity[ $slug ] ?? '';
                $type  = $fields[ $slug ]['type'] ?? 'string';
                $html .= '<td>' . esc_html( $this->format_value_for_display( $value, $type, $slug ) ) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        $html .= '</table>';

        return $html;
    }

    /**
     * Format a value for display in list view.
     *
     * @param mixed  $value The raw value.
     * @param string $type  The DataSet field type.
     * @param string $slug  The field slug.
     * @return string The formatted value for display.
     */
    protected function format_value_for_display( mixed $value, string $type, string $slug ): string {
        if ( $value === null || $value === '' ) {
            return '';
        }

        // Check if this is a repeater field.
        $config = $this->field_configs[ $slug ] ?? [];
        if ( ( $config['type'] ?? '' ) === 'repeater' ) {
            $items = is_string( $value ) ? json_decode( $value, true ) : $value;
            if ( is_array( $items ) ) {
                return sprintf( '%d item(s)', count( $items ) );
            }
            return '';
        }

        return match ( $type ) {
            DataSet::TYPE_BOOLEAN => $value ? 'Yes' : 'No',
            DataSet::TYPE_INTEGER => (string) $value,
            default               => (string) $value,
        };
    }

    /**
     * Enqueue Tangible Fields assets.
     */
    public function enqueue_assets(): void {
        if ( $this->enqueued ) {
            return;
        }

        $this->ensure_tangible_fields_loaded();

        $fields = tangible_fields();
        $fields->enqueue();

        $this->enqueued = true;
    }

    /**
     * Schedule asset enqueueing for the footer.
     */
    protected function schedule_enqueue(): void {
        if ( $this->enqueued ) {
            return;
        }

        // Enqueue in footer to ensure all fields are registered.
        add_action( 'admin_footer', [ $this, 'enqueue_assets' ], 5 );
    }

    /**
     * Ensure Tangible Fields framework is loaded.
     *
     * @throws \RuntimeException If Tangible Fields is not available.
     */
    protected function ensure_tangible_fields_loaded(): void {
        if ( ! function_exists( 'tangible_fields' ) ) {
            throw new \RuntimeException(
                'TangibleFieldsRenderer requires the Tangible Fields framework. ' .
                'Please ensure it is installed and loaded before using this renderer.'
            );
        }
    }
}
