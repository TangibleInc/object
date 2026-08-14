<?php declare( strict_types=1 );

namespace Tangible\DataView;

use Tangible\DataObject\DataSet;
use Tangible\DataObject\ListQuery;
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Section;
use Tangible\EditorLayout\Sidebar;
use Tangible\Renderer\Renderer;
use Tangible\Renderer\HtmlRenderer;
use Tangible\RequestHandler\PluralHandler;
use Tangible\RequestHandler\SingularHandler;

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

    /**
     * The query the current list view is rendering.
     *
     * Set by render_list() before any list markup renders, so helpers —
     * including render_list_table() overrides in subclasses — can read
     * the active search/sort/page state without a signature change.
     *
     * @var ListQuery|null
     */
    protected ?ListQuery $current_list_query = null;

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
            'search_items'    => sprintf( 'Search %s', $plural ),
            'not_found'       => 'No items found.',
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
     * Build the ListQuery for the current list request.
     *
     * Reads the WP-conventional list parameters (paged / orderby / order /
     * s / filter_<field>) and validates them against the config's list
     * declarations, so only declared-sortable fields can order the list
     * and only declared-filterable fields can filter it.
     *
     * @return ListQuery The query for the current request.
     */
    protected function build_list_query(): ListQuery {
        $orderby = sanitize_key( (string) $this->request->get_param( 'orderby', '' ) );
        if ( ! in_array( $orderby, $this->config->get_sortable_fields(), true ) ) {
            $orderby = '';
        }

        $filters = [];
        foreach ( $this->config->get_filterable_fields() as $field ) {
            $value = $this->request->get_param( 'filter_' . $field );
            if ( $value !== null && $value !== '' ) {
                $filters[ $field ] = sanitize_text_field( (string) $value );
            }
        }

        return new ListQuery(
            page: max( 1, (int) $this->request->get_param( 'paged', 1 ) ),
            per_page: $this->config->get_list_per_page(),
            orderby: $orderby,
            order: strtolower( (string) $this->request->get_param( 'order', 'asc' ) ),
            search: sanitize_text_field( (string) $this->request->get_param( 's', '' ) ),
            search_fields: $this->config->get_searchable_fields(),
            filters: $filters
        );
    }

    /**
     * Render the list view.
     */
    protected function render_list(): void {
        /** @var PluralHandler $handler */
        $handler = $this->handler;

        $query                    = $this->build_list_query();
        $this->current_list_query = $query;

        $result = $handler->query( $query );

        $entities = [];
        foreach ( $result->get_entities() as $entity ) {
            $data       = $entity->get_data();
            $data['id'] = $entity->get_id();
            $entities[] = $data;
        }

        $total = $result->get_total() ?? count( $entities );

        $this->render_page_header( $this->get_label( 'all_items' ), $this->url_builder->url( 'create' ) );
        $this->render_notices();

        $this->render_list_controls( $query );

        // The table always renders — headers (and their sort links) must
        // survive an empty page, e.g. a search that matched nothing.
        echo $this->render_list_table( $entities );
        $this->render_pagination( $total, $query );

        $this->render_page_footer();

        $this->current_list_query = null;
    }

    /**
     * Render the search box and filter controls above the list, wrapped
     * in a GET form that round-trips the page and sort state.
     *
     * Renders nothing when the config declares neither searchable nor
     * filterable fields, keeping zero-config list pages unchanged.
     *
     * @param ListQuery $query The current list query.
     */
    protected function render_list_controls( ListQuery $query ): void {
        $searchable = $this->config->get_searchable_fields();
        $filterable = $this->config->get_filterable_fields();

        if ( $searchable === [] && $filterable === [] ) {
            return;
        }

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr( $this->config->get_menu_page() ) . '">';
        if ( $query->orderby !== '' ) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr( $query->orderby ) . '">';
            echo '<input type="hidden" name="order" value="' . esc_attr( $query->order ) . '">';
        }

        if ( $searchable !== [] ) {
            $input_id = $this->config->get_menu_page() . '-search-input';
            echo '<p class="search-box">';
            echo '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . esc_html( $this->get_label( 'search_items' ) ) . '</label>';
            echo '<input type="search" id="' . esc_attr( $input_id ) . '" name="s" value="' . esc_attr( $query->search ) . '">';
            echo '<input type="submit" class="button" value="' . esc_attr( $this->get_label( 'search_items' ) ) . '">';
            echo '</p>';
        }

        if ( $filterable !== [] ) {
            echo '<div class="alignleft actions">';
            foreach ( $filterable as $field ) {
                $this->render_list_filter( $field, $query->filters[ $field ] ?? '' );
            }
            echo '<input type="submit" class="button" value="Filter">';
            echo '</div>';
        }

        echo '</form>';
    }

    /**
     * Render a single filter control.
     *
     * Fields whose config declares 'options' (value => label) render as a
     * dropdown with an "all" default; other filterable fields stay
     * URL-driven only.
     *
     * @param string $field Field name.
     * @param string $current Currently applied filter value.
     */
    protected function render_list_filter( string $field, string $current ): void {
        $field_config = $this->config->get_field_config( $field );
        $options      = $field_config['options'] ?? null;

        if ( ! is_array( $options ) || $options === [] ) {
            return;
        }

        $name  = 'filter_' . $field;
        $label = ucfirst( str_replace( '_', ' ', $field ) );

        echo '<label class="screen-reader-text" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
        echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
        echo '<option value="">' . esc_html( sprintf( 'All %s', $this->get_label( 'plural' ) ) ) . '</option>';
        foreach ( $options as $value => $option_label ) {
            echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( $current, (string) $value, false ) . '>'
                . esc_html( (string) $option_label ) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Build a list URL carrying the current query state, with overrides.
     *
     * Pass null as an override value to drop that parameter.
     *
     * @param array $overrides Parameter overrides.
     * @return string List URL.
     */
    protected function list_url( array $overrides = [] ): string {
        $args  = [];
        $query = $this->current_list_query;

        if ( $query !== null ) {
            if ( $query->search !== '' ) {
                $args['s'] = $query->search;
            }
            if ( $query->orderby !== '' ) {
                $args['orderby'] = $query->orderby;
                $args['order']   = $query->order;
            }
            if ( $query->page > 1 ) {
                $args['paged'] = $query->page;
            }
            foreach ( $query->filters as $field => $value ) {
                $args[ 'filter_' . $field ] = $value;
            }
        }

        foreach ( $overrides as $key => $value ) {
            if ( $value === null ) {
                unset( $args[ $key ] );
            } else {
                $args[ $key ] = $value;
            }
        }

        return $this->url_builder->url( 'list', null, $args );
    }

    /**
     * Render a list column header cell, as a sort link when the field is
     * declared sortable.
     *
     * Uses core list-table classes (sortable / sorted, asc / desc) so
     * wp-admin styles the indicators.
     *
     * @param string $field Field name.
     * @return string Header cell HTML.
     */
    protected function render_column_header( string $field ): string {
        $label = ucfirst( str_replace( '_', ' ', $field ) );

        if ( ! in_array( $field, $this->config->get_sortable_fields(), true ) ) {
            return '<th scope="col" class="manage-column">' . esc_html( $label ) . '</th>';
        }

        $query      = $this->current_list_query;
        $is_current = $query !== null && $query->orderby === $field;
        $next_order = $is_current && $query->order === 'asc' ? 'desc' : 'asc';
        $class      = $is_current ? 'sorted ' . $query->order : 'sortable ' . $next_order;

        // Sorting resets to page 1: the old page number is meaningless
        // under a new order.
        $url = $this->list_url( [ 'orderby' => $field, 'order' => $next_order, 'paged' => null ] );

        return '<th scope="col" class="manage-column ' . esc_attr( $class ) . '">'
            . '<a href="' . esc_url( $url ) . '">'
            . '<span>' . esc_html( $label ) . '</span>'
            . '<span class="sorting-indicator"></span>'
            . '</a></th>';
    }

    /**
     * Render the pagination tablenav under the list.
     *
     * @param int $total Unpaginated match count.
     * @param ListQuery $query The current list query.
     */
    protected function render_pagination( int $total, ListQuery $query ): void {
        if ( $query->per_page <= 0 ) {
            return;
        }

        $total_pages = (int) ceil( $total / $query->per_page );

        echo '<div class="tablenav bottom"><div class="tablenav-pages">';
        echo '<span class="displaying-num">' . esc_html( sprintf( '%d items', $total ) ) . '</span>';

        if ( $total_pages > 1 ) {
            $links = paginate_links( [
                'base'    => $this->list_url( [ 'paged' => '%#%' ] ),
                'format'  => '',
                'current' => $query->page,
                'total'   => $total_pages,
            ] );
            if ( is_string( $links ) ) {
                echo '<span class="pagination-links">' . $links . '</span>';
            }
        }

        echo '</div></div>';
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

        $params = $this->request->get_body_params();
        $nested = $params[ $this->config->slug ] ?? [];

        foreach ( $this->config->field_configs as $name => $config ) {
            $type = $config['type'];

            // Check nested array first (singular/settings mode), then flat POST
            $has_value = isset( $nested[ $name ] ) || isset( $params[ $name ] );

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
            $raw_value = $nested[ $name ] ?? $params[ $name ];
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
        $fields = $this->config->get_list_columns();
        $html   = '<table class="wp-list-table widefat fixed striped">';

        // Header.
        $html .= '<thead><tr>';
        foreach ( $fields as $field ) {
            $html .= $this->render_column_header( $field );
        }
        $html .= '<th scope="col" class="manage-column">Actions</th>';
        $html .= '</tr></thead>';

        // Body.
        $html .= '<tbody>';
        if ( $entities === [] ) {
            $html .= '<tr class="no-items"><td colspan="' . ( count( $fields ) + 1 ) . '">'
                . esc_html( $this->get_label( 'not_found' ) )
                . '</td></tr>';
        }
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
