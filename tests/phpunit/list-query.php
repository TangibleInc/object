<?php
namespace Tangible\Object\Tests;

use Tangible\DataObject\DataSet;
use Tangible\DataObject\ListQuery;
use Tangible\DataObject\PluralObject;
use Tangible\DataObject\PluralObject\Entity;
use Tangible\DataObject\QueryablePluralStorage;
use Tangible\DataObject\Storage\DatabaseModuleStorage;
use Tangible\DataView\DataView;
use Tangible\RequestHandler\PluralHandler;
use Tangible\RequestHandler\Result;

/**
 * Tests for ListQuery-driven listing: the value object's reference
 * semantics, the in-memory fallback paths (PluralObject over all(),
 * PluralHandler over adapter-style list() overrides), the native SQL
 * path in DatabaseModuleStorage, the DataViewConfig 'list' section,
 * and the RequestRouter's list rendering.
 *
 * @covers \Tangible\DataObject\ListQuery
 * @covers \Tangible\DataObject\QueryablePluralStorage
 * @covers \Tangible\DataObject\PluralObject
 * @covers \Tangible\DataObject\Storage\DatabaseModuleStorage
 * @covers \Tangible\RequestHandler\PluralHandler
 * @covers \Tangible\RequestHandler\Result
 * @covers \Tangible\DataView\DataViewConfig
 * @covers \Tangible\DataView\RequestRouter
 */
class ListQuery_TestCase extends \WP_UnitTestCase {

    /**
     * ==========================================================================
     * ListQuery: normalization
     * ==========================================================================
     */

    public function test_query_normalizes_out_of_range_values(): void {
        $query = new ListQuery( page: 0, per_page: -5, order: 'BOGUS' );

        $this->assertSame( 1, $query->page );
        $this->assertSame( 0, $query->per_page );
        $this->assertSame( 'asc', $query->order );
    }

    public function test_query_offset_derives_from_page_and_per_page(): void {
        $this->assertSame( 20, ( new ListQuery( page: 3, per_page: 10 ) )->offset() );
        $this->assertSame( 0, ( new ListQuery( page: 3, per_page: 0 ) )->offset() );
    }

    /**
     * ==========================================================================
     * ListQuery: in-memory reference semantics
     * ==========================================================================
     */

    private function sample_rows(): array {
        return [
            [ 'id' => 1, 'title' => 'Alpha video', 'type' => 'video', 'weight' => 10 ],
            [ 'id' => 2, 'title' => 'beta text', 'type' => 'text', 'weight' => 2 ],
            [ 'id' => 3, 'title' => 'Gamma VIDEO guide', 'type' => 'video', 'weight' => 30 ],
            [ 'id' => 4, 'title' => 'Delta text', 'type' => 'text', 'weight' => 2 ],
        ];
    }

    public function test_filters_are_string_loose_equality(): void {
        $rows  = $this->sample_rows();
        $query = new ListQuery( per_page: 0, filters: [ 'weight' => '2' ] );

        $this->assertSame( [ 2, 4 ], array_column( $query->apply( $rows ), 'id' ) );
        $this->assertSame( 2, $query->count_matching( $rows ) );
    }

    public function test_filter_on_missing_field_matches_nothing(): void {
        $query = new ListQuery( per_page: 0, filters: [ 'nonexistent' => 'x' ] );

        $this->assertSame( [], $query->apply( $this->sample_rows() ) );
        $this->assertSame( 0, $query->count_matching( $this->sample_rows() ) );
    }

    public function test_search_is_case_insensitive_over_declared_fields(): void {
        $rows  = $this->sample_rows();
        $query = new ListQuery( per_page: 0, search: 'video', search_fields: [ 'title' ] );

        $this->assertSame( [ 1, 3 ], array_column( $query->apply( $rows ), 'id' ) );
    }

    public function test_search_without_declared_fields_matches_any_field(): void {
        $rows  = $this->sample_rows();
        $query = new ListQuery( per_page: 0, search: 'video' );

        // 'video' appears in the type field of rows 1 and 3, and their titles.
        $this->assertSame( [ 1, 3 ], array_column( $query->apply( $rows ), 'id' ) );
    }

    public function test_search_and_filters_combine_as_and(): void {
        $rows  = $this->sample_rows();
        $query = new ListQuery(
            per_page: 0,
            search: 'guide',
            search_fields: [ 'title' ],
            filters: [ 'type' => 'video' ]
        );

        $this->assertSame( [ 3 ], array_column( $query->apply( $rows ), 'id' ) );
    }

    public function test_ordering_compares_numeric_and_string_appropriately(): void {
        $rows = $this->sample_rows();

        $by_weight = new ListQuery( per_page: 0, orderby: 'weight', order: 'desc' );
        $this->assertSame( [ 3, 1, 2, 4 ], array_column( $by_weight->apply( $rows ), 'id' ) );

        // Case-insensitive string sort: 'Alpha' < 'beta' < 'Delta' < 'Gamma'.
        $by_title = new ListQuery( per_page: 0, orderby: 'title', order: 'asc' );
        $this->assertSame( [ 1, 2, 4, 3 ], array_column( $by_title->apply( $rows ), 'id' ) );
    }

    public function test_equal_order_keys_keep_original_order(): void {
        $rows  = $this->sample_rows();
        $query = new ListQuery( per_page: 0, orderby: 'type', order: 'asc' );

        // 'text' rows 2 and 4 tie; stable sort keeps 2 before 4.
        $this->assertSame( [ 2, 4, 1, 3 ], array_column( $query->apply( $rows ), 'id' ) );
    }

    public function test_pagination_slices_after_filter_and_sort(): void {
        $rows  = $this->sample_rows();
        $query = new ListQuery( page: 2, per_page: 2, orderby: 'weight', order: 'asc' );

        $this->assertSame( [ 1, 3 ], array_column( $query->apply( $rows ), 'id' ) );
        // count_matching ignores pagination.
        $this->assertSame( 4, $query->count_matching( $rows ) );
    }

    public function test_apply_accepts_an_accessor_for_non_row_items(): void {
        $entities = [];
        foreach ( $this->sample_rows() as $row ) {
            $entity = new Entity( $row );
            $entity->set_id( $row['id'] );
            $entities[] = $entity;
        }

        $query    = new ListQuery( per_page: 0, filters: [ 'type' => 'video' ] );
        $accessor = static fn( Entity $e ): array => $e->get_data();

        $matched = $query->apply( $entities, $accessor );

        $this->assertCount( 2, $matched );
        $this->assertContainsOnlyInstancesOf( Entity::class, $matched );
        $this->assertSame( 2, $query->count_matching( $entities, $accessor ) );
    }

    /**
     * ==========================================================================
     * PluralObject: in-memory fallback over a non-queryable storage
     * ==========================================================================
     */

    private function make_cpt_object( string $slug ): PluralObject {
        $dataset = new DataSet();
        $dataset->add_string( 'title' );
        $dataset->add_string( 'category' );

        $object = new PluralObject( $slug );
        $object->set_dataset( $dataset );
        $object->register( [ 'public' => false, 'show_ui' => true ] );

        return $object;
    }

    public function test_plural_object_query_falls_back_to_in_memory(): void {
        $object = $this->make_cpt_object( 'lq_cpt_fallback' );

        $object->create( [ 'title' => 'Charlie', 'category' => 'b' ] );
        $object->create( [ 'title' => 'Alice', 'category' => 'a' ] );
        $object->create( [ 'title' => 'Bob', 'category' => 'b' ] );

        $query = new ListQuery( per_page: 2, orderby: 'title', order: 'asc', filters: [ 'category' => 'b' ] );

        $titles = array_map(
            static fn( Entity $e ) => $e->get( 'title' ),
            $object->query( $query )
        );

        $this->assertSame( [ 'Bob', 'Charlie' ], $titles );
        $this->assertSame( 2, $object->count( $query ) );
    }

    public function test_plural_object_delegates_to_queryable_storage(): void {
        $storage = new class() implements QueryablePluralStorage {
            public array $received = [];
            public function register( string $slug, array $settings ): void {}
            public function insert( array $data ): int {
                return 1; }
            public function update( int $id, array $data ): void {}
            public function delete( int $id ): void {}
            public function find( int $id ): ?array {
                return null; }
            public function all(): array {
                $this->received[] = 'all';
                return [];
            }
            public function query( ListQuery $query ): array {
                $this->received[] = 'query';
                return [ [ 'id' => 42, 'title' => 'native' ] ];
            }
            public function count( ListQuery $query ): int {
                $this->received[] = 'count';
                return 7;
            }
        };

        $object = new PluralObject( 'lq_native', $storage );

        $query    = new ListQuery();
        $entities = $object->query( $query );

        $this->assertSame( 42, $entities[0]->get_id() );
        $this->assertSame( 7, $object->count( $query ) );
        // The storage executed natively; all() was never consulted.
        $this->assertSame( [ 'query', 'count' ], $storage->received );
    }

    /**
     * ==========================================================================
     * PluralHandler: query() with an object, and the adapter-wrapper fallback
     * ==========================================================================
     */

    public function test_handler_query_returns_page_and_total(): void {
        $object = $this->make_cpt_object( 'lq_handler' );
        for ( $i = 1; $i <= 5; $i++ ) {
            $object->create( [ 'title' => 'Item ' . $i, 'category' => 'x' ] );
        }

        $handler = new PluralHandler( $object );
        $result  = $handler->query( new ListQuery( page: 2, per_page: 2, orderby: 'title' ) );

        $this->assertTrue( $result->is_success() );
        $this->assertCount( 2, $result->get_entities() );
        $this->assertSame( 5, $result->get_total() );
        $this->assertSame(
            [ 'Item 3', 'Item 4' ],
            array_map( static fn( Entity $e ) => $e->get( 'title' ), $result->get_entities() )
        );
    }

    /**
     * Adapter wrappers (LMS/Quiz) extend PluralHandler WITHOUT a
     * PluralObject and only override list(). The inherited query() must
     * fall back to applying the query in memory over their list().
     */
    public function test_handler_query_falls_back_over_overridden_list(): void {
        $handler = new class() extends PluralHandler {
            public function __construct() {
                // Deliberately no parent constructor — no PluralObject,
                // mirroring the downstream adapter wrappers.
            }
            public function list(): Result {
                $entities = [];
                foreach ( [
                    [ 'title' => 'Zeta', 'type' => 'video' ],
                    [ 'title' => 'Alpha', 'type' => 'text' ],
                    [ 'title' => 'Mid', 'type' => 'video' ],
                ] as $i => $row ) {
                    $entity = new Entity( $row );
                    $entity->set_id( $i + 1 );
                    $entities[] = $entity;
                }
                return ( new Result() )->set_entities( $entities )->set_is_success( true );
            }
        };

        $result = $handler->query( new ListQuery(
            per_page: 1,
            orderby: 'title',
            order: 'asc',
            filters: [ 'type' => 'video' ]
        ) );

        $this->assertTrue( $result->is_success() );
        $this->assertSame( 2, $result->get_total() );
        $this->assertCount( 1, $result->get_entities() );
        $this->assertSame( 'Mid', $result->get_entities()[0]->get( 'title' ) );
    }

    public function test_result_total_defaults_to_null(): void {
        $this->assertNull( ( new Result() )->get_total() );
    }

    /**
     * ==========================================================================
     * DatabaseModuleStorage: native SQL execution
     * ==========================================================================
     */

    private function make_tdb_storage( string $slug ): DatabaseModuleStorage {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $storage = new DatabaseModuleStorage( $slug );
        $storage->register( $slug, [
            'schema' => [
                'id' => [
                    'type'           => 'bigint',
                    'length'         => '20',
                    'auto_increment' => true,
                    'primary_key'    => true,
                ],
                'title' => [
                    'type'   => 'varchar',
                    'length' => '255',
                ],
                'category' => [
                    'type'   => 'varchar',
                    'length' => '64',
                ],
                'weight' => [
                    'type'   => 'bigint',
                    'length' => '20',
                ],
            ],
        ] );

        return $storage;
    }

    private function seed_tdb( DatabaseModuleStorage $storage ): void {
        $storage->insert( [ 'title' => 'Alpha video', 'category' => 'video', 'weight' => 10 ] );
        $storage->insert( [ 'title' => 'beta text', 'category' => 'text', 'weight' => 2 ] );
        $storage->insert( [ 'title' => 'Gamma VIDEO guide', 'category' => 'video', 'weight' => 30 ] );
        $storage->insert( [ 'title' => 'Delta text', 'category' => 'text', 'weight' => 2 ] );
    }

    public function test_tdb_storage_implements_queryable_interface(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $this->assertInstanceOf(
            QueryablePluralStorage::class,
            new DatabaseModuleStorage( 'lq_tdb_iface' )
        );
    }

    public function test_tdb_query_matches_in_memory_reference_semantics(): void {
        $storage = $this->make_tdb_storage( 'lq_tdb_parity' );
        $this->seed_tdb( $storage );

        $scenarios = [
            new ListQuery( per_page: 0 ),
            new ListQuery( per_page: 0, filters: [ 'category' => 'text' ] ),
            new ListQuery( per_page: 0, search: 'video', search_fields: [ 'title' ] ),
            new ListQuery( per_page: 0, search: 'video', search_fields: [ 'title' ], filters: [ 'category' => 'video' ] ),
            new ListQuery( per_page: 0, orderby: 'weight', order: 'desc' ),
            new ListQuery( page: 2, per_page: 2, orderby: 'title', order: 'asc' ),
            new ListQuery( per_page: 0, filters: [ 'nonexistent' => 'x' ] ),
            new ListQuery( per_page: 0, search: 'anything', search_fields: [ 'nonexistent' ] ),
        ];

        $all = $storage->all();

        foreach ( $scenarios as $i => $query ) {
            $this->assertSame(
                array_column( $query->apply( $all ), 'id' ),
                array_column( $storage->query( $query ), 'id' ),
                "Scenario {$i}: native rows must match the in-memory reference"
            );
            $this->assertSame(
                $query->count_matching( $all ),
                $storage->count( $query ),
                "Scenario {$i}: native count must match the in-memory reference"
            );
        }
    }

    public function test_tdb_query_orders_by_id_alias(): void {
        $storage = $this->make_tdb_storage( 'lq_tdb_id_alias' );
        $this->seed_tdb( $storage );

        $rows = $storage->query( new ListQuery( per_page: 2, orderby: 'id', order: 'desc' ) );

        $ids = array_column( $rows, 'id' );
        $this->assertCount( 2, $ids );
        $this->assertSame( max( array_column( $storage->all(), 'id' ) ), $ids[0] );
    }

    /**
     * ==========================================================================
     * DataViewConfig: the 'list' section
     * ==========================================================================
     */

    private function base_config( array $overrides = [] ): array {
        return array_merge( [
            'slug'   => 'lq_config',
            'label'  => 'Item',
            'fields' => [
                'title'    => 'string',
                'category' => 'string',
            ],
        ], $overrides );
    }

    public function test_config_list_defaults(): void {
        $config = new \Tangible\DataView\DataViewConfig( $this->base_config() );

        $this->assertSame( [ 'title', 'category' ], $config->get_list_columns() );
        $this->assertSame( [], $config->get_sortable_fields() );
        $this->assertSame( [], $config->get_searchable_fields() );
        $this->assertSame( [], $config->get_filterable_fields() );
        $this->assertSame( 20, $config->get_list_per_page() );
    }

    public function test_config_list_declarations_are_honored(): void {
        $config = new \Tangible\DataView\DataViewConfig( $this->base_config( [
            'list' => [
                'columns'    => [ 'title' ],
                'sortable'   => [ 'title', 'id' ],
                'searchable' => [ 'title' ],
                'filterable' => [ 'category' ],
                'per_page'   => 5,
            ],
        ] ) );

        $this->assertSame( [ 'title' ], $config->get_list_columns() );
        $this->assertSame( [ 'title', 'id' ], $config->get_sortable_fields() );
        $this->assertSame( [ 'category' ], $config->get_filterable_fields() );
        $this->assertSame( 5, $config->get_list_per_page() );
    }

    public function test_config_rejects_unknown_list_fields(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'list.sortable' );

        new \Tangible\DataView\DataViewConfig( $this->base_config( [
            'list' => [ 'sortable' => [ 'bogus' ] ],
        ] ) );
    }

    public function test_config_rejects_negative_per_page(): void {
        $this->expectException( \InvalidArgumentException::class );

        new \Tangible\DataView\DataViewConfig( $this->base_config( [
            'list' => [ 'per_page' => -1 ],
        ] ) );
    }

    /**
     * ==========================================================================
     * RequestRouter: list rendering
     * ==========================================================================
     */

    private array $saved_get = [];

    private function render_list_output( array $config_overrides = [], array $get = [] ): string {
        wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

        $this->saved_get = $_GET;
        $_GET            = array_merge( $_GET, $get );

        try {
            // Request snapshots superglobals at construction, so the view
            // (and its router) must be built after $_GET is staged.
            $view = new DataView( array_merge( $this->base_config( [
                'slug' => 'lq_router_' . md5( serialize( [ $config_overrides, $get ] ) ),
            ] ), $config_overrides ) );

            $router   = new \ReflectionProperty( DataView::class, 'router' );
            $router->setAccessible( true );

            ob_start();
            $router->getValue( $view )->route();
            return (string) ob_get_clean();
        } finally {
            $_GET = $this->saved_get;
        }
    }

    public function test_router_renders_sortable_header_link(): void {
        $html = $this->render_list_output( [
            'list' => [ 'sortable' => [ 'title' ] ],
        ] );

        $this->assertStringContainsString( 'sortable', $html );
        $this->assertStringContainsString( 'orderby=title', $html );
        $this->assertStringContainsString( 'sorting-indicator', $html );
        // The non-sortable column stays a plain header.
        $this->assertStringContainsString( '<th scope="col" class="manage-column">Category</th>', $html );
    }

    public function test_router_renders_search_box_only_when_declared(): void {
        $without = $this->render_list_output();
        $this->assertStringNotContainsString( 'search-box', $without );

        $with = $this->render_list_output( [
            'list' => [ 'searchable' => [ 'title' ] ],
        ] );
        $this->assertStringContainsString( 'search-box', $with );
        $this->assertStringContainsString( 'name="s"', $with );
    }

    public function test_router_renders_filter_dropdown_from_field_options(): void {
        $html = $this->render_list_output( [
            'fields' => [
                'title'    => 'string',
                'category' => [
                    'type'    => 'string',
                    'options' => [ 'video' => 'Video', 'text' => 'Text' ],
                ],
            ],
            'list'   => [ 'filterable' => [ 'category' ] ],
        ] );

        $this->assertStringContainsString( 'name="filter_category"', $html );
        $this->assertStringContainsString( '>Video<', $html );
    }

    public function test_router_list_respects_orderby_search_and_pagination(): void {
        $slug   = 'lq_router_data';
        $object = null;

        $config = $this->base_config( [
            'slug' => $slug,
            'list' => [
                'sortable'   => [ 'title' ],
                'searchable' => [ 'title' ],
                'per_page'   => 2,
            ],
        ] );

        // Seed through a handler-equivalent object so rows exist for the
        // router's CPT-backed DataView (same slug, same storage).
        $dataset = new DataSet();
        $dataset->add_string( 'title' );
        $dataset->add_string( 'category' );
        $object = new PluralObject( $slug );
        $object->set_dataset( $dataset );
        $object->register( [ 'public' => false, 'show_ui' => true ] );
        foreach ( [ 'Bravo', 'Alpha', 'Charlie' ] as $title ) {
            $object->create( [ 'title' => $title, 'category' => 'x' ] );
        }

        $this->saved_get = $_GET;
        $_GET            = array_merge( $_GET, [ 'orderby' => 'title', 'order' => 'asc' ] );
        try {
            $view   = new DataView( $config );
            $router = new \ReflectionProperty( DataView::class, 'router' );
            $router->setAccessible( true );

            wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
            ob_start();
            $router->getValue( $view )->route();
            $html = (string) ob_get_clean();
        } finally {
            $_GET = $this->saved_get;
        }

        // Page 1 of 2-per-page, sorted: Alpha and Bravo visible, Charlie not.
        $this->assertStringContainsString( 'Alpha', $html );
        $this->assertStringContainsString( 'Bravo', $html );
        $this->assertStringNotContainsString( 'Charlie', $html );
        // Pagination reports the full count and links to page 2.
        $this->assertStringContainsString( '3 items', $html );
        $this->assertStringContainsString( 'paged=2', $html );
        // The active sort column flips its link to descending.
        $this->assertStringContainsString( 'order=desc', $html );
    }

    /**
     * ==========================================================================
     * UrlBuilder: parented menu pages
     * ==========================================================================
     */

    public function test_url_builder_defaults_to_admin_php(): void {
        $builder = new \Tangible\DataView\UrlBuilder( 'my_page' );

        $this->assertStringContainsString( 'admin.php', $builder->url( 'list' ) );
        $this->assertSame( [ 'page' => 'my_page' ], $builder->base_params() );
    }

    public function test_url_builder_builds_on_the_parent_file(): void {
        $builder = new \Tangible\DataView\UrlBuilder( 'my_page', 'edit.php?post_type=book' );

        $url = $builder->url( 'list' );
        $this->assertStringContainsString( 'edit.php', $url );
        $this->assertStringContainsString( 'post_type=book', $url );
        $this->assertStringContainsString( 'page=my_page', $url );
        $this->assertStringNotContainsString( 'admin.php', $url );

        $this->assertSame(
            [ 'post_type' => 'book', 'page' => 'my_page' ],
            $builder->base_params()
        );
    }

    public function test_url_builder_treats_plugin_slug_parent_as_top_level(): void {
        // A parent that is another plugin page's slug (no .php) keeps the
        // admin.php base — matching WordPress's own submenu URL rule.
        $builder = new \Tangible\DataView\UrlBuilder( 'my_page', 'some-plugin-menu' );

        $this->assertStringContainsString( 'admin.php', $builder->url( 'list' ) );
        $this->assertSame( [ 'page' => 'my_page' ], $builder->base_params() );
    }

    public function test_router_list_form_carries_parent_params_as_hidden_inputs(): void {
        $html = $this->render_list_output( [
            'ui'   => [ 'parent' => 'edit.php?post_type=book' ],
            'list' => [ 'searchable' => [ 'title' ] ],
        ] );

        $this->assertStringContainsString( 'name="post_type" value="book"', $html );
        $this->assertStringContainsString( 'name="page"', $html );
    }

    public function test_router_list_ignores_undeclared_orderby(): void {
        $html = $this->render_list_output( [], [ 'orderby' => 'title', 'order' => 'asc' ] );

        // 'title' is not declared sortable in the base config, so no header
        // renders as currently-sorted.
        $this->assertStringNotContainsString( 'class="manage-column sorted', $html );
    }
}
