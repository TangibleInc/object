<?php declare( strict_types=1 );

namespace Tangible\DataView;

use Tangible\DataObject\DataSet;
use Tangible\DataObject\PluralObject;
use Tangible\DataObject\SingularObject;
use Tangible\DataObject\Storage\CustomPostTypeStorage;
use Tangible\DataObject\Storage\DatabaseModuleStorage;
use Tangible\DataObject\Storage\OptionStorage;
use Tangible\EditorLayout\Layout;
use Tangible\Renderer\Renderer;
use Tangible\Renderer\HtmlRenderer;
use Tangible\Renderer\TangibleFieldsRenderer;
use Tangible\RequestHandler\PluralHandler;
use Tangible\RequestHandler\SingularHandler;

/**
 * High-level facade for creating data views with minimal configuration.
 *
 * DataView orchestrates all Object toolset components to provide a simple API
 * for creating WordPress admin interfaces for custom data types.
 *
 * Example usage:
 * ```php
 * $view = new DataView([
 *     'slug' => 'my_data',
 *     'label' => 'Item',
 *     'fields' => [
 *         'name' => 'string',
 *         'email' => 'email',
 *         'active' => 'boolean',
 *     ],
 *     'storage' => 'database',
 *     'ui' => [
 *         'menu_page' => 'my_data_admin',
 *         'menu_label' => 'My Data',
 *     ],
 * ]);
 *
 * $view->get_handler()->add_validator('email', Validators::required());
 * $view->register();
 * ```
 */
class DataView {

    protected DataViewConfig $config;
    protected DataSet $dataset;
    protected PluralObject|SingularObject $object;
    protected PluralHandler|SingularHandler $handler;
    protected FieldTypeRegistry $registry;
    protected LabelGenerator $label_generator;
    protected SchemaGenerator $schema_generator;
    protected UrlBuilder $url_builder;
    protected RequestRouter $router;
    protected ?Renderer $renderer = null;

    /** @var callable|null */
    protected $layout_callback = null;

    /**
     * Create a new DataView instance.
     *
     * @param array $config Configuration array with keys:
     *   - slug: string (required) - unique identifier
     *   - label: string (required) - singular label (e.g., "View")
     *   - fields: array (required) - field_name => type mapping
     *   - storage: string - 'database', 'cpt', or 'option' (default: 'cpt')
     *   - mode: string - 'plural' or 'singular' (default: 'plural')
     *   - ui: array - WordPress admin UI configuration
     *   - capability: string - required capability (default: 'manage_options')
     */
    public function __construct( array $config ) {
        $this->registry         = new FieldTypeRegistry();
        $this->label_generator  = new LabelGenerator();
        $this->schema_generator = new SchemaGenerator( $this->registry );

        $this->config = new DataViewConfig( $config );

        $this->build_dataset();
        $this->build_object();
        $this->build_handler();

        $this->url_builder = new UrlBuilder( $this->config->get_menu_page() );
        $this->router      = new RequestRouter(
            $this->config,
            $this->dataset,
            $this->handler,
            $this->registry,
            $this->url_builder,
            $this->renderer,
            $this->label_generator
        );
    }

    /**
     * Build the DataSet from configuration.
     */
    protected function build_dataset(): void {
        $this->dataset = new DataSet();

        foreach ( $this->config->fields as $name => $type ) {
            $dataset_type = $this->registry->get_dataset_type( $type );

            match ( $dataset_type ) {
                DataSet::TYPE_STRING  => $this->dataset->add_string( $name ),
                DataSet::TYPE_INTEGER => $this->dataset->add_integer( $name ),
                DataSet::TYPE_BOOLEAN => $this->dataset->add_boolean( $name ),
            };
        }
    }

    /**
     * Build the data object with appropriate storage.
     */
    protected function build_object(): void {
        if ( $this->config->is_singular() ) {
            $this->build_singular_object();
        } else {
            $this->build_plural_object();
        }
    }

    /**
     * Build a plural object.
     */
    protected function build_plural_object(): void {
        $storage = $this->create_plural_storage();

        $this->object = new PluralObject( $this->config->slug, $storage );
        $this->object->set_dataset( $this->dataset );
    }

    /**
     * Build a singular object.
     */
    protected function build_singular_object(): void {
        $storage = $this->create_singular_storage();

        $this->object = new SingularObject( $this->config->slug, $storage );
        $this->object->set_dataset( $this->dataset );
    }

    /**
     * Create plural storage based on configuration.
     *
     * @return \Tangible\DataObject\PluralStorage
     */
    protected function create_plural_storage() {
        switch ( $this->config->storage ) {
            case 'database':
                $storage = new DatabaseModuleStorage( $this->config->slug );
                $settings = $this->schema_generator->generate_settings( $this->config->fields );
                // Merge storage_options, allowing overrides (e.g., version number).
                $settings = array_merge( $settings, $this->config->storage_options );
                $storage->register( $this->config->slug, $settings );
                return $storage;

            case 'cpt':
            default:
                return new CustomPostTypeStorage( $this->config->slug );
        }
    }

    /**
     * Create singular storage based on configuration.
     *
     * @return \Tangible\DataObject\SingularStorage
     */
    protected function create_singular_storage() {
        return new OptionStorage( $this->config->slug );
    }

    /**
     * Build the request handler.
     */
    protected function build_handler(): void {
        if ( $this->config->is_singular() ) {
            /** @var SingularObject $object */
            $object = $this->object;
            $this->handler = new SingularHandler( $object );
        } else {
            /** @var PluralObject $object */
            $object = $this->object;
            $this->handler = new PluralHandler( $object );
        }
    }

    /**
     * Get the request handler for adding validators and hooks.
     *
     * @return PluralHandler|SingularHandler
     */
    public function get_handler(): PluralHandler|SingularHandler {
        return $this->handler;
    }

    /**
     * Get the underlying data object.
     *
     * @return PluralObject|SingularObject
     */
    public function get_object(): PluralObject|SingularObject {
        return $this->object;
    }

    /**
     * Get the DataSet instance.
     *
     * @return DataSet
     */
    public function get_dataset(): DataSet {
        return $this->dataset;
    }

    /**
     * Get the configuration.
     *
     * @return DataViewConfig
     */
    public function get_config(): DataViewConfig {
        return $this->config;
    }

    /**
     * Get the field type registry.
     *
     * @return FieldTypeRegistry
     */
    public function get_field_registry(): FieldTypeRegistry {
        return $this->registry;
    }

    /**
     * Generate a URL for a specific action.
     *
     * @param string $action One of: 'list', 'create', 'edit', 'delete'.
     * @param int|null $id Entity ID (required for 'edit' and 'delete').
     * @return string Admin URL.
     */
    public function url( string $action = 'list', ?int $id = null ): string {
        return $this->url_builder->url( $action, $id );
    }

    /**
     * Set a custom layout callback.
     *
     * @param callable $callback Callback receives Layout instance.
     * @return static
     */
    public function set_layout( callable $callback ): static {
        $this->layout_callback = $callback;
        $this->router->set_layout_callback( $callback );
        return $this;
    }

    /**
     * Set a custom renderer.
     *
     * @param Renderer $renderer
     * @return static
     */
    public function set_renderer( Renderer $renderer ): static {
        $this->renderer = $renderer;

        // Pass field configs to TangibleFieldsRenderer for repeater support.
        if ( $renderer instanceof TangibleFieldsRenderer ) {
            $renderer->set_field_configs( $this->config->field_configs );
        }

        $this->router->set_renderer( $renderer );
        return $this;
    }

    /**
     * Register the admin menu and hooks.
     *
     * Should be called during 'admin_menu' action or later.
     *
     * @return static
     */
    public function register(): static {
        // Register the object (CPT or table).
        if ( $this->config->is_plural() && $this->config->storage === 'cpt' ) {
            $labels = $this->label_generator->generate(
                $this->config->get_singular_label(),
                $this->config->get_plural_label(),
                $this->config->labels
            );
            $this->object->register( [
                'labels'      => $labels,
                'public'      => false,
                'show_ui'     => false, // We handle UI ourselves.
                'show_in_rest' => false,
            ] );
        }

        // Register admin menu.
        $this->register_admin_menu();

        return $this;
    }

    /**
     * Register the admin menu page.
     */
    protected function register_admin_menu(): void {
        $parent = $this->config->get_parent_menu();

        if ( $parent !== null ) {
            add_submenu_page(
                $parent,
                $this->config->get_menu_label(),
                $this->config->get_menu_label(),
                $this->config->capability,
                $this->config->get_menu_page(),
                [ $this, 'handle_request' ]
            );
        } else {
            add_menu_page(
                $this->config->get_menu_label(),
                $this->config->get_menu_label(),
                $this->config->capability,
                $this->config->get_menu_page(),
                [ $this, 'handle_request' ],
                $this->config->get_icon(),
                $this->config->get_position()
            );
        }
    }

    /**
     * Handle the current admin page request.
     */
    public function handle_request(): void {
        $this->router->route();
    }
}
