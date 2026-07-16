<?php
namespace Tangible\Object\Tests;

use Tangible\DataView\DataView;

/**
 * Simulate POST request to save entity, and validate stored value
 * for each storage
 *
 * Requests are staged with wp_slash(), as wordpress applies
 * add_magic_quotes on $_POST for every real request
 *
 * @covers \Tangible\DataView\DataView
 * @covers \Tangible\DataView\Request
 * @covers \Tangible\DataView\RequestRouter
 * @covers \Tangible\DataView\FieldTypeRegistry
 * @covers \Tangible\DataObject\Storage\CustomPostTypeStorage
 * @covers \Tangible\DataObject\Storage\DatabaseModuleStorage
 * @covers \Tangible\DataObject\Storage\OptionStorage
 */
class SaveData_TestCase extends \WP_UnitTestCase {

    // Single quote, double quote and backslash: everything slashing can corrupt
    private const HOSTILE_STRING = 'hostile string: "it\'s" back\slash';

    public function setUp(): void {
        parent::setUp();

        // The test suite doesn't set a request method, unlike WP on a real request
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $administrator = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $administrator );

        if ( function_exists( 'tangible_fields' ) ) {
            tangible_fields()->registered_fields = [];
        }
    }

    /**
     * ==========================================================================
     * Cases
     * ==========================================================================
     */

    /**
     * Shared by the plural and single entity providers, so both modes cover
     * the same values
     * -> field type
     * -> submitted value
     * -> expected stored value
     */
    private function cases(): array {
        return [

            'string'      => [
                'string',
                self::HOSTILE_STRING,
                self::HOSTILE_STRING,
            ],

            'xss attempt' => [
                'string',
                'hello <script>alert("xss")</script><b>world</b>',
                'hello world',
            ],

            'html tags'   => [
                'string',
                '<div onclick="alert(1)">hello</div> world',
                'hello world',
            ],

            'boolean'     => [
                'boolean',
                '1',
                true,
            ],

            'map'         => [
                'map',
                [ 'nested' => [ 'path' => self::HOSTILE_STRING ] ],
                [ 'nested' => [ 'path' => self::HOSTILE_STRING ] ],
            ],

            'repeater'    => [
                [ 'type' => 'repeater', 'sub_fields' => [ [ 'name' => 'label', 'type' => 'string' ] ] ],
                wp_json_encode( [ [ 'label' => self::HOSTILE_STRING ] ] ),
                wp_json_encode( [ [ 'label' => self::HOSTILE_STRING ] ] ),
            ],
        ];
    }

    /**
     * ==========================================================================
     * Helpers
     * ==========================================================================
     */

    /**
     * Reach the protected RequestRouter on a DataView for direct invocation.
     */
    private function get_router( DataView $view ): \Tangible\DataView\RequestRouter {
        $property = new \ReflectionProperty( DataView::class, 'router' );
        $property->setAccessible( true );
        return $property->getValue( $view );
    }

    /**
     * Build the DataView on the staged request and fire maybe_redirect()
     * (the admin_init entry point).
     *
     * A successful submit ends in wp_redirect() + exit: the wp_redirect filter
     * runs first, so throwing there keeps PHPUnit alive and captures the location.
     *
     * @return array{0: DataView, 1: string} The view and the redirect location.
     */
    private function submit_request( array $config ): array {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        add_filter( 'wp_redirect', static function ( string $location ): string {
            throw new \Exception( $location );
        } );

        try {
            $view = new DataView( $config );
            $this->get_router( $view )->maybe_redirect();
        } catch ( \Exception $e ) {
            return [ $view, $e->getMessage() ];
        }

        $this->fail( 'Expected the success redirect after submit' );
    }

    /**
     * ==========================================================================
     * Plural entity
     * ==========================================================================
     */

    public function plural_submit_round_trip_provider(): array {
        /**
         * Database storage has no array columns (map), and returns
         * booleans as the string "1" since nothing coerces on read
         */
        $unsupported = [
            'database' => [ 'map', 'boolean' ],
        ];

        $rows = [];
        foreach ( [ 'cpt', 'database' ] as $storage ) {
            foreach ( $this->cases() as $name => $case ) {
                if ( in_array( $name, $unsupported[ $storage ] ?? [], true ) ) {
                    continue;
                }
                $rows[ "{$storage}: {$name}" ] = [ $storage, ...$case ];
            }
        }

        return $rows;
    }

    /**
     * @dataProvider plural_submit_round_trip_provider
     */
    public function test_plural_submit_round_trips_value(
        string $storage,
        string|array $field_type,
        string|array $submitted,
        mixed $expected
    ): void {
        if ( $storage === 'database' && ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $type_slug = is_array( $field_type ) ? $field_type['type'] : $field_type;
        $slug      = "dv_e2e_{$storage}_{$type_slug}";
        $config    = [
            'slug'    => $slug,
            'label'   => 'Item',
            'storage' => $storage,
            'fields'  => [
                'title' => 'string',
                'value' => $field_type,
            ],
        ];

        $id = ( new DataView( $config ) )
            ->get_handler()
            ->create( ['title' => 'before', 'value' => 'before' ] )
            ->get_entity()
            ->get_id();

        $_GET['page']           = $slug;
        $_GET['action']         = 'edit';
        $_GET['id']             = (string) $id;
        $_POST['title']         = wp_slash( self::HOSTILE_STRING );
        $_POST['value']         = wp_slash( $submitted );
        $_POST['_wpnonce_edit'] = wp_create_nonce( "{$slug}_edit_{$id}" );

        [ $view, $location ] = $this->submit_request( $config );

        $this->assertStringContainsString( 'updated=1', $location );

        $entity = $view->get_handler()->read( $id )->get_entity();
        $this->assertSame( $expected, $entity->get( 'value' ) );
        $this->assertSame( self::HOSTILE_STRING, $entity->get( 'title' ) );

        // CPT storage also writes the title as post_title
        if ( $storage === 'cpt' ) {
            $this->assertSame( self::HOSTILE_STRING, get_post( $id )->post_title );
        }
    }

    public function test_plural_create_submit_round_trips_value(): void {
        $config = [
            'slug'   => 'dv_e2e_create',
            'label'  => 'Item',
            'fields' => [ 'title' => 'string' ],
        ];

        $_GET['page']             = 'dv_e2e_create';
        $_GET['action']           = 'create';
        $_POST['title']           = wp_slash( self::HOSTILE_STRING );
        $_POST['_wpnonce_create'] = wp_create_nonce( 'dv_e2e_create_create' );

        [ $view, $location ] = $this->submit_request( $config );

        $this->assertStringContainsString( 'created=1', $location );

        // The redirect targets the new entity's edit page
        parse_str( (string) parse_url( $location, PHP_URL_QUERY ), $query );
        $id = (int) $query['id'];

        $this->assertSame(
            self::HOSTILE_STRING,
            $view
                ->get_handler()
                ->read( $id )
                ->get_entity()
                ->get( 'title' )
        );

        $this->assertSame(
            self::HOSTILE_STRING,
            get_post( $id )->post_title
        );
    }

    /**
     * ==========================================================================
     * Single entity
     * ==========================================================================
     */

    public function singular_submit_round_trip_provider(): array {
        return $this->cases();
    }

    /**
     * @dataProvider singular_submit_round_trip_provider
     */
    public function test_singular_submit_round_trips_value(
        string|array $field_type,
        string|array $submitted,
        mixed $expected
    ): void {
        $type_slug = is_array( $field_type ) ? $field_type['type'] : $field_type;
        $slug      = "dv_e2e_option_{$type_slug}";
        $config    = [
            'slug'    => $slug,
            'label'   => 'Settings',
            'mode'    => 'singular',
            'storage' => 'option',
            'fields'  => [ 'value' => $field_type ],
        ];

        $_GET['page']             = $slug;
        $_POST['value']           = wp_slash( $submitted );
        $_POST['_wpnonce_update'] = wp_create_nonce( "{$slug}_update" );

        [ $view, $location ] = $this->submit_request( $config );

        $this->assertStringContainsString( 'updated=1', $location );

        $data = $view->get_handler()->read()->get_data();
        $this->assertSame( $expected, $data['value'] );
    }
}
