<?php declare( strict_types=1 );

namespace Tangible\DataView;

use Tangible\DataObject\DataSet;
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;
use Tangible\Renderer\Renderer;
use Tangible\Renderer\HtmlRenderer;
use Tangible\RequestHandler\PluralHandler;
use Tangible\RequestHandler\SingularHandler;
use Tangible\RequestHandler\Result;

/**
 * Handles request routing and rendering for DataView admin pages.
 */
class RequestRouter {

    protected DataViewConfig $config;
    protected DataSet $dataset;
    protected PluralHandler|SingularHandler $handler;
    protected FieldTypeRegistry $registry;
    protected UrlBuilder $url_builder;
    protected Renderer $renderer;
    protected LabelGenerator $label_generator;
    protected Request $request;

    /** @var callable|null */
    protected $layout_callback = null;

    /** @var array Cached resolved labels. */
    protected array $resolved_labels = [];

    public function __construct(
        DataViewConfig $config,
        DataSet $dataset,
        PluralHandler|SingularHandler $handler,
        FieldTypeRegistry $registry,
        UrlBuilder $url_builder,
        ?Renderer $renderer = null,
        ?LabelGenerator $label_generator = null,
        ?Request $request = null
    ) {
        $this->config          = $config;
        $this->dataset         = $dataset;
        $this->handler         = $handler;
        $this->registry        = $registry;
        $this->url_builder     = $url_builder;
        $this->renderer        = $renderer ?? new HtmlRenderer();
        $this->label_generator = $label_generator ?? new LabelGenerator();
        $this->request         = $request ?? new Request();

        $this->resolve_labels();
    }

    /**
     * Resolve and cache all labels, merging user-provided with auto-generated.
     */
    protected function resolve_labels(): void {
        $singular = $this->config->get_singular_label();
        $plural   = $this->config->get_plural_label()
            ?? $this->label_generator->pluralize( $singular );

        // Generate default labels.
        $defaults = [
            'singular'        => $singular,
            'plural'          => $plural,
            'all_items'       => $plural,
            'add_new_item'    => sprintf( 'Add New %s', $singular ),
            'edit_item'       => sprintf( 'Edit %s', $singular ),
            'settings'        => sprintf( '%s Settings', $singular ),
            'item_created'    => 'Item created successfully.',
            'item_updated'    => 'Item updated successfully.',
            'item_deleted'    => 'Item deleted successfully.',
            'settings_saved'  => 'Settings saved successfully.',
        ];

        // Merge with user-provided labels.
        $this->resolved_labels = array_merge( $defaults, $this->config->labels );
    }

    /**
     * Get a resolved label.
     *
     * @param string $key Label key.
     * @return string Label value.
     */
    protected function get_label( string $key ): string {
        return $this->resolved_labels[ $key ] ?? $key;
    }

    /**
     * Set a custom layout callback.
     *
     * @param callable $callback Callback that receives Layout instance.
     */
    public function set_layout_callback( callable $callback ): void {
        $this->layout_callback = $callback;
    }

    /**
     * Set a custom renderer.
     *
     * @param Renderer $renderer Renderer instance.
     */
    public function set_renderer( Renderer $renderer ): void {
        $this->renderer = $renderer;
    }

    /**
     * Check capability.
     */
    protected function check_capability() {
        if ( ! current_user_can( $this->config->capability ) ) {
            wp_die( __( 'You do not have permission to access this page.' ) );
        }
    }

    /**
     * Route the current request to the appropriate handler.
     */
    public function route(): void {
        $this->check_capability();

        $action = $this->request->get_current_action();
        $id     = $this->request->get_current_id();

        // Handle singular mode differently.
        if ( $this->config->is_singular() ) {
            $this->route_singular();
            return;
        }

        // Handle plural mode.
        $this->route_plural( $action, $id );
    }

    /**
     * POST requests will trigger a redirect so they have to be processed
     * earlier than GET request, before any content is displayed
     *
     * @param string $action Current action.
     * @param int|null $id Entity ID.
     */
    public function maybe_redirect(): void {
        // This runs on the global admin_init hook, so it must ignore requests
        // that are not targeting this DataView's own admin page. Without this
        // guard it would intercept every admin POST (e.g. core or other-plugin
        // settings) and fail their nonce check with "Security check failed.".
        if ( $this->request->get_current_page() !== $this->config->get_menu_page() ) {
            return;
        }

        if ( ! $this->request->is_post() ) return;

        $this->check_capability();

        if ( $this->config->is_singular() ) {
            $this->handle_settings_submit();
            return;
        }

        $action = $this->request->get_current_action();
        $id     = $this->request->get_current_id();

        switch ( $action ) {
            case 'create':
                $this->handle_create_submit();
                return;
            case 'edit':
                if ( $id !== null ) {
                    $this->handle_edit_submit( $id );
                    return;
                }
                break;
            case 'delete':
                if ( $id !== null ) {
                    $this->handle_delete( $id );
                    return;
                }
                break;
        }
    }

    /**
     * Route plural (multi-entity) requests.
     *
     * @param string $action Current action.
     * @param int|null $id Entity ID.
     */
    protected function route_plural( string $action, ?int $id ): void {
        // Handle GET requests.
        switch ( $action ) {
            case 'create':
                $this->render_create_form();
                break;
            case 'edit':
                if ( $id !== null ) {
                    $this->render_edit_form( $id );
                } else {
                    $this->render_list();
                }
                break;
            case 'delete':
                if ( $id !== null ) {
                    $this->handle_delete( $id );
                } else {
                    $this->render_list();
                }
                break;
            default:
                $this->render_list();
                break;
        }
    }

    /**
     * Route singular (single-entity) requests.
     */
    protected function route_singular(): void {
        $this->render_settings_form();
    }

    /**
     * Render the list view.
     */
    protected function render_list(): void {
        /** @var PluralHandler $handler */
        $handler = $this->handler;
        $result  = $handler->list();

        $entities = [];
        foreach ( $result->get_entities() as $entity ) {
            $data       = $entity->get_data();
            $data['id'] = $entity->get_id();
            $entities[] = $data;
        }

        $this->render_page_header( $this->get_label( 'all_items' ), $this->url_builder->url( 'create' ) );
        $this->render_notices();

        if ( empty( $entities ) ) {
            echo '<p>No items found.</p>';
        } else {
            echo $this->render_list_table( $entities );
        }

        $this->render_page_footer();
    }

    /**
     * Render the create form.
     *
     * @param array $errors Validation errors.
     * @param array $data Pre-filled data.
     */
    protected function render_create_form( array $errors = [], array $data = [] ): void {
        $layout = $this->build_layout();

        $this->render_page_header( $this->get_label( 'add_new_item' ) );

        if ( ! empty( $errors ) ) {
            $this->render_errors( $errors );
        }

        echo '<form method="post" action="' . esc_url( $this->url_builder->url( 'create' ) ) . '">';
        $this->nonce_field( 'create' );
        echo $this->renderer->render_editor( $layout, $data );
        echo '</form>';

        $this->render_back_link();
        $this->render_page_footer();
    }

    /**
     * Handle create form submission.
     */
    protected function handle_create_submit(): void {
        if ( ! $this->verify_nonce( 'create' ) ) {
            wp_die( 'Security check failed.' );
        }

        $data = $this->extract_post_data();

        /** @var PluralHandler $handler */
        $handler = $this->handler;
        $result  = $handler->create( $data );

        if ( $result->is_error() ) {
            $this->render_create_form( $result->get_errors(), $data );
            return;
        }

        $new_id = $result->get_entity()->get_id();
        wp_redirect( $this->url_builder->url( 'edit', $new_id, [ 'created' => '1' ] ) );
        exit;
    }

    /**
     * Render the edit form.
     *
     * @param int $id Entity ID.
     * @param array $errors Validation errors.
     */
    protected function render_edit_form( int $id, array $errors = [] ): void {
        /** @var PluralHandler $handler */
        $handler = $this->handler;
        $result  = $handler->read( $id );

        if ( $result->is_error() ) {
            wp_die( 'Item not found.' );
        }

        $entity = $result->get_entity();
        $data   = $entity->get_data();
        $layout = $this->build_layout();

        $this->render_page_header( $this->get_label( 'edit_item' ) );
        $this->render_notices();

        if ( ! empty( $errors ) ) {
            $this->render_errors( $errors );
        }

        echo '<form method="post" action="' . esc_url( $this->url_builder->url( 'edit', $id ) ) . '">';
        $this->nonce_field( 'edit', $id );
        $this->nonce_field( 'delete', $id );
        echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">';

        echo $this->renderer->render_editor( $layout, $data );
        echo '</form>';

        $this->render_back_link();
        $this->render_page_footer();
    }

    /**
     * Handle edit form submission.
     *
     * @param int $id Entity ID.
     */
    protected function handle_edit_submit( int $id ): void {
        if ( ! $this->verify_nonce( 'edit', $id ) ) {
            wp_die( 'Security check failed.' );
        }

        $data = $this->extract_post_data();

        /** @var PluralHandler $handler */
        $handler = $this->handler;
        $result  = $handler->update( $id, $data );

        if ( $result->is_error() ) {
            $this->render_edit_form( $id, $result->get_errors() );
            return;
        }

        wp_redirect( $this->url_builder->url( 'edit', $id, [ 'updated' => '1' ] ) );
        exit;
    }

    /**
     * Handle delete action.
     *
     * @param int $id Entity ID.
     */
    protected function handle_delete( int $id ): void {
        if ( ! $this->verify_nonce( 'delete', $id ) ) {
            wp_die( 'Security check failed.' );
        }

        /** @var PluralHandler $handler */
        $handler = $this->handler;
        $handler->delete( $id );

        wp_redirect( $this->url_builder->url( 'list', null, [ 'deleted' => '1' ] ) );
        exit;
    }

    /**
     * Render the settings form (singular mode).
     *
     * @param array $errors Validation errors.
     */
    protected function render_settings_form( array $errors = [] ): void {
        /** @var SingularHandler $handler */
        $handler = $this->handler;
        $result  = $handler->read();
        $data    = $result->is_success() ? $result->get_data() : [];
        $layout  = $this->build_layout();

        $this->render_page_header( $this->get_label( 'settings' ) );
        $this->render_notices();

        if ( ! empty( $errors ) ) {
            $this->render_errors( $errors );
        }

        echo '<form method="post">';
        $this->nonce_field( 'update' );
        echo $this->renderer->render_editor( $layout, $data );
        echo '</form>';

        $this->render_page_footer();
    }

    /**
     * Handle settings form submission (singular mode).
     */
    protected function handle_settings_submit(): void {
        if ( ! $this->verify_nonce( 'update' ) ) {
            wp_die( 'Security check failed.' );
        }

        $data = $this->extract_post_data();

        /** @var SingularHandler $handler */
        $handler = $this->handler;
        $result  = $handler->update( $data );

        if ( $result->is_error() ) {
            $this->render_settings_form( $result->get_errors() );
            return;
        }

        wp_redirect( $this->url_builder->url( 'list', null, [ 'updated' => '1' ] ) );
        exit;
    }

    /**
     * Extract and sanitize POST data based on field types.
     *
     * In singular mode, the SettingsRenderer nests field names under the
     * settings key (e.g., `settings_slug[field_name]`), so POST data arrives
     * as `$_POST['settings_slug']['field_name']`. We check the nested array
     * first, then fall back to flat `$_POST['field_name']` for compatibility.
     *
     * @return array Sanitized data.
     */
    protected function extract_post_data(): array {
        $data = [];

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nested = $_POST[ $this->config->slug ] ?? [];

        foreach ( $this->config->field_configs as $name => $config ) {
            $type = $config['type'];

            // Check nested array first (singular/settings mode), then flat POST
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $has_value = isset( $nested[ $name ] ) || isset( $_POST[ $name ] );

            if ( ! $has_value ) {
                // Handle missing boolean fields (unchecked checkboxes).
                if ( $this->registry->get_dataset_type( $type ) === DataSet::TYPE_BOOLEAN ) {
                    $data[ $name ] = false;
                }
                // Handle missing repeater fields (default to empty array).
                if ( $type === 'repeater' ) {
                    $data[ $name ] = '[]';
                }
                continue;
            }

            $sanitizer = $this->registry->get_sanitizer( $type );
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $raw_value = $nested[ $name ] ?? $_POST[ $name ];
            $data[ $name ] = $sanitizer( $raw_value );
        }

        return $data;
    }

    /**
     * Build the editor layout.
     *
     * @return Layout The built layout.
     */
    protected function build_layout(): Layout {
        $layout = new Layout( $this->dataset );

        if ( $this->layout_callback !== null ) {
            ( $this->layout_callback )( $layout );
        } else {
            $this->build_default_layout( $layout );
        }

        return $layout;
    }

    /**
     * Build the default layout.
     *
     * @param Layout $layout The layout to build.
     */
    protected function build_default_layout( Layout $layout ): void {
        $layout->section( 'Details', function ( Section $section ) {
            foreach ( array_keys( $this->config->fields ) as $field_name ) {
                $section->field( $field_name );
            }
        } );

        $layout->sidebar( function ( Sidebar $sidebar ) {
            $this->request->get_current_action() === 'create'
                ? $sidebar->actions( [ 'create' ] )
                : $sidebar->actions( [ 'save', 'delete' ] );
        } );
    }

    /**
     * Render a list table with action links.
     *
     * @param array $entities Entity data arrays.
     * @return string HTML table.
     */
    protected function render_list_table( array $entities ): string {
        $fields = array_keys( $this->config->fields );
        $html   = '<table class="wp-list-table widefat fixed striped">';

        // Header.
        $html .= '<thead><tr>';
        foreach ( $fields as $field ) {
            $html .= '<th>' . esc_html( ucfirst( $field ) ) . '</th>';
        }
        $html .= '<th>Actions</th>';
        $html .= '</tr></thead>';

        // Body.
        $html .= '<tbody>';
        foreach ( $entities as $entity ) {
            $html .= '<tr>';
            foreach ( $fields as $field ) {
                $value = $entity[ $field ] ?? '';
                if ( is_bool( $value ) ) {
                    $value = $value ? 'Yes' : 'No';
                }
                $html .= '<td>' . esc_html( (string) $value ) . '</td>';
            }

            // Actions column.
            $id = $entity['id'] ?? 0;
            $html .= '<td>';
            $html .= '<a href="' . esc_url( $this->url_builder->url( 'edit', $id ) ) . '">Edit</a>';
            $html .= ' | ';
            $html .= '<a href="' . esc_url( $this->url_builder->url_with_nonce( 'delete', $id, $this->config->get_nonce_action( 'delete', $id ) ) ) . '" onclick="return confirm(\'Are you sure?\');">Delete</a>';
            $html .= '</td>';

            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Output a nonce field for an action.
     *
     * @param string $action Action name.
     * @param int|null $id Entity ID.
     */
    protected function nonce_field( string $action, ?int $id = null ): void {
        wp_nonce_field(
            $this->config->get_nonce_action( $action, $id ),
            $this->config->get_nonce_name( $action )
        );
    }

    /**
     * Verify nonce for an action.
     *
     * @param string $action Action name.
     * @param int|null $id Entity ID.
     * @return bool True if nonce is valid.
     */
    protected function verify_nonce( string $action, ?int $id = null ): bool {
        $nonce_action = $this->config->get_nonce_action( $action, $id );
        $nonce = $this->request->get_nonce( $this->config->get_nonce_name( $action ) );
        return wp_verify_nonce( $nonce, $nonce_action ) !== false;
    }

    /**
     * Render page header.
     *
     * @param string $title Page title.
     * @param string|null $add_new_url Optional "Add New" button URL.
     */
    protected function render_page_header( string $title, ?string $add_new_url = null ): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $title );
        if ( $add_new_url !== null ) {
            echo ' <a href="' . esc_url( $add_new_url ) . '" class="page-title-action">Add New</a>';
        }
        echo '</h1>';
    }

    /**
     * Render page footer.
     */
    protected function render_page_footer(): void {
        echo '</div>';
    }

    /**
     * Render back link.
     */
    protected function render_back_link(): void {
        echo '<p><a href="' . esc_url( $this->url_builder->url( 'list' ) ) . '">&larr; Back to list</a></p>';
    }

    /**
     * Render success/error notices from query params.
     */
    protected function render_notices(): void {
        if ( ! $this->config->notices ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['created'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $this->get_label( 'item_created' ) ) . '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['updated'] ) ) {
            $label = $this->config->is_singular()
                ? $this->get_label( 'settings_saved' )
                : $this->get_label( 'item_updated' );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $label ) . '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $this->get_label( 'item_deleted' ) ) . '</p></div>';
        }
    }

    /**
     * Render validation errors.
     *
     * @param array $errors Validation error objects.
     */
    protected function render_errors( array $errors ): void {
        echo '<div class="notice notice-error"><ul>';
        foreach ( $errors as $error ) {
            $message = $error->get_field() . ': ' . $error->get_message();
            echo '<li>' . esc_html( $message ) . '</li>';
        }
        echo '</ul></div>';
    }
}
