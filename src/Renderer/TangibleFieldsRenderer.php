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
            'type'         => 'accordion',
            'label'        => $section['label'],
            'isOpen'       => true, // Expanded by default.
            'uncontrolled' => true,
            'fields'       => $section_fields,
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
                'type'         => 'accordion',
                'label'        => $item['label'],
                'title'        => $item['label'],
                'uncontrolled' => true,
                'fields'       => $section_fields,
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
                'title'  => $tab['label'],
                'fields' => $tab_fields,
            ];
        }

        $tabs_id = 'tabs_' . uniqid();

        return $fields->render_field( $tabs_id, [
            'type'         => 'tab',
            'tabs'         => $tabs,
            'uncontrolled' => true,
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
            'create' => [
                'label'   => __( 'Create', 'tangible-object' ),
                'type'    => 'submit',
                'name'    => 'action',
                'value'   => 'create',
                'class'   => 'button button-primary',
                'onclick' => '',
            ],
            'save' => [
                'label'   => __( 'Save', 'tangible-object' ),
                'type'    => 'submit',
                'name'    => 'action',
                'value'   => 'edit',
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
        return tangible_fields()->render_field(
            $field['slug'],
            $this->build_field_config( $field )
        );
    }

    /**
     * Map sub-field definitions to Tangible Fields format.
     *
     * @param array $sub_fields Sub-field definitions.
     * @return array Tangible Fields sub-field configs.
     */
    protected function map_sub_fields( array $sub_fields ): array {
        return array_map(
            function ( array $sub_field ): array {
                $type = $sub_field['type'] ?? 'string';

                if ( ! isset( $this->sub_field_type_map[ $type ] ) ) {
                    throw new \InvalidArgumentException( sprintf(
                        'Unsupported repeater sub-field type "%s" for "%s".',
                        $type,
                        $sub_field['name'] ?? ''
                    ) );
                }

                return $this->format_field_args(
                    $type,
                    $sub_field['name'],
                    $sub_field
                );
            },
            $sub_fields
        );
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
        $config = $this->resolve_field_config( $field );
        $value  = $this->data[ $slug ] ?? $this->get_default_value( $slug );

        $args = $this->format_field_args( $type, $slug, $config, $field );

        $args['value'] = $type === 'repeater'
            ? $this->format_repeater_value( $value, $config )
            : $this->format_value_for_field( $value, $type );

        return $args;
    }

    /**
     * Merge a Layout field with its registered config into one config array.
     *
     * The Layout structure and the registered field config both carry display
     * keys (label, help/description, placeholder, readonly); this resolves their
     * precedence so format_field_args has a single source to read from.
     *
     * @param array $field The field structure from Layout.
     * @return array The merged config.
     */
    protected function resolve_field_config( array $field ): array {
        $config = $this->field_configs[ $field['slug'] ] ?? [];

        return array_merge( $config, [
            'label'       => $config['label'] ?? $field['label'] ?? null,
            'description' => $field['help'] ?? $config['description'] ?? null,
            'placeholder' => $field['placeholder'] ?? $config['placeholder'] ?? null,
            'readonly'    => $field['readonly'] ?? $config['readonly'] ?? false,
        ] );
    }

    /**
     * Build the field-definition args for a field or repeater sub-field, without its value.
     *
     * The caller attaches the value, since repeater sub-fields have none.
     *
     * @param string $type   The DataView field type.
     * @param string $name   The field name.
     * @param array  $config The field config / options source.
     * @param array  $field  The Layout field structure (empty for sub-fields).
     * @return array The Tangible Fields args.
     */
    protected function format_field_args(
        string $type,
        string $name,
        array $config,
        array $field = []
    ): array {
        $args = [
            'type'        => $this->get_tangible_fields_type( $type ),
            'name'        => $name,
            'label'       => $config['label'] ?? ucfirst( str_replace( '_', ' ', $name ) ),
            'description' => $config['description'] ?? '',
            'placeholder' => $config['placeholder'] ?? '',
        ];

        $args = $this->add_type_specific_options( $args, $type, $field, $config );

        if ( ! empty( $config['readonly'] ) ) {
            $args['read_only'] = true;
        }

        return $args;
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

            case 'repeater':
                $field_args['sub_fields'] = $this->map_sub_fields( $config['sub_fields'] ?? [] );
                $field_args['layout']     = $config['layout'] ?? 'table';

                if ( isset( $config['max_rows'] ) ) {
                    $field_args['maxlength'] = $config['max_rows'];
                }
                if ( isset( $config['min_rows'] ) ) {
                    $field_args['minlength'] = $config['min_rows'];
                }
                if ( isset( $config['button_label'] ) ) {
                    $field_args['new_item'] = $config['button_label'];
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
     * Format a repeater value as the JSON string the field expects.
     *
     * @param mixed $value  The raw value.
     * @param array $config The field configuration.
     * @return string The JSON-encoded value.
     */
    protected function format_repeater_value( mixed $value, array $config ): string {
        if ( is_array( $value ) ) {
            return wp_json_encode( $value );
        }
        if ( empty( $value ) ) {
            return wp_json_encode( $config['default'] ?? [] );
        }

        return (string) $value;
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
     * Required by the interface, but enqueue logic will be handled by
     * the fields module
     *
     * @see https://github.com/TangibleInc/fields/blob/main/enqueue.php
     */
    public function enqueue_assets(): void {
        $this->ensure_tangible_fields_loaded();
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
