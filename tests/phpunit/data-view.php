<?php
namespace Tangible\Object\Tests;

use Tangible\DataObject\DataSet;
use Tangible\DataObject\PluralObject;
use Tangible\DataObject\SingularObject;
use Tangible\DataObject\Storage\CustomPostTypeStorage;
use Tangible\DataObject\Storage\DatabaseModuleStorage;
use Tangible\DataObject\Storage\OptionStorage;
use Tangible\DataView\DataView;
use Tangible\DataView\DataViewConfig;
use Tangible\DataView\FieldTypeRegistry;
use Tangible\DataView\LabelGenerator;
use Tangible\DataView\SchemaGenerator;
use Tangible\DataView\UrlBuilder;
use Tangible\RequestHandler\PluralHandler;
use Tangible\RequestHandler\SingularHandler;
use Tangible\RequestHandler\Validators;

/**
 * Tests for the DataView high-level API.
 *
 * @covers \Tangible\DataView\DataView
 * @covers \Tangible\DataView\DataViewConfig
 * @covers \Tangible\DataView\FieldTypeRegistry
 * @covers \Tangible\DataView\LabelGenerator
 * @covers \Tangible\DataView\SchemaGenerator
 * @covers \Tangible\DataView\UrlBuilder
 * @covers \Tangible\DataView\RequestRouter
 */
class DataView_TestCase extends \WP_UnitTestCase {

    /**
     * ==========================================================================
     * FieldTypeRegistry Tests
     * ==========================================================================
     */

    public function test_field_type_registry_can_be_instantiated(): void {
        $registry = new FieldTypeRegistry();
        $this->assertInstanceOf( FieldTypeRegistry::class, $registry );
    }

    public function test_field_type_registry_has_default_types(): void {
        $registry = new FieldTypeRegistry();

        $this->assertTrue( $registry->has_type( 'string' ) );
        $this->assertTrue( $registry->has_type( 'text' ) );
        $this->assertTrue( $registry->has_type( 'email' ) );
        $this->assertTrue( $registry->has_type( 'url' ) );
        $this->assertTrue( $registry->has_type( 'integer' ) );
        $this->assertTrue( $registry->has_type( 'boolean' ) );
        $this->assertTrue( $registry->has_type( 'date' ) );
        $this->assertTrue( $registry->has_type( 'datetime' ) );
    }

    public function test_field_type_registry_maps_to_dataset_types(): void {
        $registry = new FieldTypeRegistry();

        $this->assertEquals( DataSet::TYPE_STRING, $registry->get_dataset_type( 'string' ) );
        $this->assertEquals( DataSet::TYPE_STRING, $registry->get_dataset_type( 'text' ) );
        $this->assertEquals( DataSet::TYPE_STRING, $registry->get_dataset_type( 'email' ) );
        $this->assertEquals( DataSet::TYPE_INTEGER, $registry->get_dataset_type( 'integer' ) );
        $this->assertEquals( DataSet::TYPE_BOOLEAN, $registry->get_dataset_type( 'boolean' ) );
    }

    public function test_field_type_registry_returns_sanitizers(): void {
        $registry = new FieldTypeRegistry();

        $this->assertEquals( 'sanitize_text_field', $registry->get_sanitizer( 'string' ) );
        $this->assertEquals( 'sanitize_textarea_field', $registry->get_sanitizer( 'text' ) );
        $this->assertEquals( 'sanitize_email', $registry->get_sanitizer( 'email' ) );
        $this->assertEquals( 'esc_url_raw', $registry->get_sanitizer( 'url' ) );
        $this->assertEquals( 'intval', $registry->get_sanitizer( 'integer' ) );
    }

    public function test_field_type_registry_returns_schema(): void {
        $registry = new FieldTypeRegistry();

        $string_schema = $registry->get_schema( 'string' );
        $this->assertEquals( 'varchar', $string_schema['type'] );
        $this->assertEquals( 255, $string_schema['length'] );

        $text_schema = $registry->get_schema( 'text' );
        $this->assertEquals( 'text', $text_schema['type'] );

        $boolean_schema = $registry->get_schema( 'boolean' );
        $this->assertEquals( 'tinyint', $boolean_schema['type'] );
        $this->assertEquals( 1, $boolean_schema['length'] );
    }

    public function test_field_type_registry_returns_input_types(): void {
        $registry = new FieldTypeRegistry();

        $this->assertEquals( 'text', $registry->get_input_type( 'string' ) );
        $this->assertEquals( 'textarea', $registry->get_input_type( 'text' ) );
        $this->assertEquals( 'email', $registry->get_input_type( 'email' ) );
        $this->assertEquals( 'number', $registry->get_input_type( 'integer' ) );
        $this->assertEquals( 'checkbox', $registry->get_input_type( 'boolean' ) );
    }

    public function test_field_type_registry_throws_for_unknown_type(): void {
        $registry = new FieldTypeRegistry();

        $this->expectException( \InvalidArgumentException::class );
        $registry->get_dataset_type( 'unknown_type' );
    }

    public function test_field_type_registry_can_register_custom_type(): void {
        $registry = new FieldTypeRegistry();

        $registry->register_type( 'phone', [
            'dataset'   => DataSet::TYPE_STRING,
            'sanitizer' => 'sanitize_text_field',
            'schema'    => [ 'type' => 'varchar', 'length' => 20 ],
            'input'     => 'tel',
        ] );

        $this->assertTrue( $registry->has_type( 'phone' ) );
        $this->assertEquals( 'tel', $registry->get_input_type( 'phone' ) );
    }

    public function test_boolean_sanitizer_works_correctly(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'boolean' );

        $this->assertTrue( $sanitizer( true ) );
        $this->assertTrue( $sanitizer( '1' ) );
        $this->assertTrue( $sanitizer( 'true' ) );
        $this->assertTrue( $sanitizer( 'yes' ) );
        $this->assertTrue( $sanitizer( 'on' ) );

        $this->assertFalse( $sanitizer( false ) );
        $this->assertFalse( $sanitizer( '0' ) );
        $this->assertFalse( $sanitizer( '' ) );
        $this->assertFalse( $sanitizer( 'no' ) );
    }

    public function test_field_type_registry_has_repeater_type(): void {
        $registry = new FieldTypeRegistry();

        $this->assertTrue( $registry->has_type( 'repeater' ) );
        $this->assertEquals( DataSet::TYPE_STRING, $registry->get_dataset_type( 'repeater' ) );
        $this->assertEquals( 'repeater', $registry->get_input_type( 'repeater' ) );
    }

    public function test_repeater_schema_is_longtext(): void {
        $registry = new FieldTypeRegistry();
        $schema = $registry->get_schema( 'repeater' );

        $this->assertEquals( 'longtext', $schema['type'] );
    }

    public function test_repeater_sanitizer_handles_json_string(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'repeater' );

        $input = '[{"key":"abc","name":"Test","count":5}]';
        $result = $sanitizer( $input );
        $decoded = json_decode( $result, true );

        $this->assertIsArray( $decoded );
        $this->assertCount( 1, $decoded );
        $this->assertEquals( 'abc', $decoded[0]['key'] );
        $this->assertEquals( 'Test', $decoded[0]['name'] );
        $this->assertEquals( 5, $decoded[0]['count'] );
    }

    public function test_repeater_sanitizer_handles_escaped_json(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'repeater' );

        // Simulates WordPress POST data with escaped quotes.
        $input = addslashes( '[{"key":"abc","name":"Test"}]' );
        $result = $sanitizer( $input );
        $decoded = json_decode( $result, true );

        $this->assertIsArray( $decoded );
        $this->assertCount( 1, $decoded );
        $this->assertEquals( 'Test', $decoded[0]['name'] );
    }

    public function test_repeater_sanitizer_handles_array_input(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'repeater' );

        $input = [
            [ 'key' => 'abc', 'name' => 'Test 1' ],
            [ 'key' => 'def', 'name' => 'Test 2' ],
        ];
        $result = $sanitizer( $input );
        $decoded = json_decode( $result, true );

        $this->assertIsArray( $decoded );
        $this->assertCount( 2, $decoded );
        $this->assertEquals( 'Test 1', $decoded[0]['name'] );
        $this->assertEquals( 'Test 2', $decoded[1]['name'] );
    }

    public function test_repeater_sanitizer_returns_empty_array_for_invalid_json(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'repeater' );

        $result = $sanitizer( 'not valid json' );
        $this->assertEquals( '[]', $result );
    }

    public function test_repeater_sanitizer_returns_empty_array_for_null(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'repeater' );

        $result = $sanitizer( null );
        $this->assertEquals( '[]', $result );
    }

    public function test_repeater_sanitizer_preserves_primitive_types(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'repeater' );

        $input = '[{"string":"text","int":42,"float":3.14,"bool":true,"null":null}]';
        $result = $sanitizer( $input );
        $decoded = json_decode( $result, true );

        $this->assertEquals( 'text', $decoded[0]['string'] );
        $this->assertSame( 42, $decoded[0]['int'] );
        $this->assertSame( 3.14, $decoded[0]['float'] );
        $this->assertSame( true, $decoded[0]['bool'] );
        $this->assertNull( $decoded[0]['null'] );
    }

    public function test_repeater_sanitizer_strips_nested_arrays(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'repeater' );

        // Nested arrays should be stripped for security.
        $input = '[{"name":"Test","nested":{"evil":"data"}}]';
        $result = $sanitizer( $input );
        $decoded = json_decode( $result, true );

        $this->assertEquals( 'Test', $decoded[0]['name'] );
        $this->assertArrayNotHasKey( 'nested', $decoded[0] );
    }

    public function test_repeater_sanitizer_sanitizes_string_values(): void {
        $registry = new FieldTypeRegistry();
        $sanitizer = $registry->get_sanitizer( 'repeater' );

        $input = '[{"name":"<script>alert(1)</script>"}]';
        $result = $sanitizer( $input );
        $decoded = json_decode( $result, true );

        // sanitize_text_field strips tags.
        $this->assertStringNotContainsString( '<script>', $decoded[0]['name'] );
    }

    /**
     * ==========================================================================
     * DataViewConfig Tests
     * ==========================================================================
     */

    public function test_config_can_be_instantiated(): void {
        $config = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
        ] );

        $this->assertInstanceOf( DataViewConfig::class, $config );
    }

    public function test_config_requires_slug(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'slug' );

        new DataViewConfig( [
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
        ] );
    }

    public function test_config_requires_label(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'label' );

        new DataViewConfig( [
            'slug'   => 'test_view',
            'fields' => [ 'name' => 'string' ],
        ] );
    }

    public function test_config_requires_fields(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'fields' );

        new DataViewConfig( [
            'slug'  => 'test_view',
            'label' => 'Test',
        ] );
    }

    public function test_config_validates_slug_format(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'slug' );

        new DataViewConfig( [
            'slug'   => 'Invalid-Slug!',
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
        ] );
    }

    public function test_config_validates_storage_type(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'storage' );

        new DataViewConfig( [
            'slug'    => 'test_view',
            'label'   => 'Test',
            'fields'  => [ 'name' => 'string' ],
            'storage' => 'invalid_storage',
        ] );
    }

    public function test_config_validates_mode(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'mode' );

        new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
            'mode'   => 'invalid_mode',
        ] );
    }

    public function test_config_has_default_values(): void {
        $config = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
        ] );

        $this->assertEquals( 'cpt', $config->storage );
        $this->assertEquals( 'plural', $config->mode );
        $this->assertEquals( 'manage_options', $config->capability );
        $this->assertEquals( 'test_view', $config->get_menu_page() );
        $this->assertEquals( 'Test', $config->get_menu_label() );
    }

    public function test_config_is_plural_and_is_singular(): void {
        $plural = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
            'mode'   => 'plural',
        ] );

        $this->assertTrue( $plural->is_plural() );
        $this->assertFalse( $plural->is_singular() );

        $singular = new DataViewConfig( [
            'slug'   => 'test_settings',
            'label'  => 'Settings',
            'fields' => [ 'name' => 'string' ],
            'mode'   => 'singular',
        ] );

        $this->assertFalse( $singular->is_plural() );
        $this->assertTrue( $singular->is_singular() );
    }

    public function test_config_ui_options(): void {
        $config = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
            'ui'     => [
                'menu_page'  => 'custom_page',
                'menu_label' => 'Custom Label',
                'parent'     => 'options-general.php',
                'icon'       => 'dashicons-star-filled',
                'position'   => 25,
            ],
        ] );

        $this->assertEquals( 'custom_page', $config->get_menu_page() );
        $this->assertEquals( 'Custom Label', $config->get_menu_label() );
        $this->assertEquals( 'options-general.php', $config->get_parent_menu() );
        $this->assertEquals( 'dashicons-star-filled', $config->get_icon() );
        $this->assertEquals( 25, $config->get_position() );
    }

    public function test_config_parses_simple_field_definitions(): void {
        $config = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [
                'name'  => 'string',
                'email' => 'email',
                'count' => 'integer',
            ],
        ] );

        // fields should be normalized to name => type.
        $this->assertEquals( 'string', $config->fields['name'] );
        $this->assertEquals( 'email', $config->fields['email'] );
        $this->assertEquals( 'integer', $config->fields['count'] );

        // field_configs should have full config arrays.
        $this->assertEquals( [ 'type' => 'string' ], $config->field_configs['name'] );
        $this->assertEquals( [ 'type' => 'email' ], $config->field_configs['email'] );
    }

    public function test_config_parses_complex_field_definitions(): void {
        $config = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [
                'name'  => 'string',
                'items' => [
                    'type'       => 'repeater',
                    'layout'     => 'table',
                    'sub_fields' => [
                        [ 'name' => 'title', 'type' => 'string' ],
                        [ 'name' => 'count', 'type' => 'integer' ],
                    ],
                ],
            ],
        ] );

        // fields should still be normalized.
        $this->assertEquals( 'string', $config->fields['name'] );
        $this->assertEquals( 'repeater', $config->fields['items'] );

        // field_configs should preserve full repeater config.
        $this->assertEquals( 'repeater', $config->field_configs['items']['type'] );
        $this->assertEquals( 'table', $config->field_configs['items']['layout'] );
        $this->assertCount( 2, $config->field_configs['items']['sub_fields'] );
    }

    public function test_config_get_field_config_returns_full_config(): void {
        $config = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [
                'items' => [
                    'type'       => 'repeater',
                    'layout'     => 'block',
                    'sub_fields' => [
                        [ 'name' => 'title', 'type' => 'string' ],
                    ],
                    'default' => [
                        [ 'title' => 'Default Item' ],
                    ],
                ],
            ],
        ] );

        $field_config = $config->get_field_config( 'items' );

        $this->assertEquals( 'repeater', $field_config['type'] );
        $this->assertEquals( 'block', $field_config['layout'] );
        $this->assertCount( 1, $field_config['default'] );
        $this->assertEquals( 'Default Item', $field_config['default'][0]['title'] );
    }

    public function test_config_get_field_config_returns_null_for_unknown(): void {
        $config = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
        ] );

        $this->assertNull( $config->get_field_config( 'unknown_field' ) );
    }

    public function test_config_throws_for_invalid_field_definition(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Invalid field definition' );

        new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [
                'bad_field' => [ 'no_type_key' => 'value' ], // Missing 'type' key.
            ],
        ] );
    }

    /**
     * ==========================================================================
     * LabelGenerator Tests
     * ==========================================================================
     */

    public function test_label_generator_can_be_instantiated(): void {
        $generator = new LabelGenerator();
        $this->assertInstanceOf( LabelGenerator::class, $generator );
    }

    public function test_label_generator_generates_labels(): void {
        $generator = new LabelGenerator();
        $labels = $generator->generate( 'Book' );

        $this->assertEquals( 'Books', $labels['name'] );
        $this->assertEquals( 'Book', $labels['singular_name'] );
        $this->assertEquals( 'Add New', $labels['add_new'] );
        $this->assertEquals( 'Add New Book', $labels['add_new_item'] );
        $this->assertEquals( 'Edit Book', $labels['edit_item'] );
        $this->assertEquals( 'Search Books', $labels['search_items'] );
        $this->assertEquals( 'No books found', $labels['not_found'] );
    }

    public function test_label_generator_accepts_custom_plural(): void {
        $generator = new LabelGenerator();
        $labels = $generator->generate( 'Person', 'People' );

        $this->assertEquals( 'People', $labels['name'] );
        $this->assertEquals( 'Person', $labels['singular_name'] );
    }

    public function test_label_generator_pluralizes_common_words(): void {
        $generator = new LabelGenerator();

        $this->assertEquals( 'Books', $generator->pluralize( 'Book' ) );
        $this->assertEquals( 'Categories', $generator->pluralize( 'Category' ) );
        $this->assertEquals( 'Boxes', $generator->pluralize( 'Box' ) );
        $this->assertEquals( 'Churches', $generator->pluralize( 'Church' ) );
        $this->assertEquals( 'Leaves', $generator->pluralize( 'Leaf' ) );
    }

    public function test_label_generator_handles_irregular_plurals(): void {
        $generator = new LabelGenerator();

        $this->assertEquals( 'Children', $generator->pluralize( 'Child' ) );
        $this->assertEquals( 'People', $generator->pluralize( 'Person' ) );
        $this->assertEquals( 'Men', $generator->pluralize( 'Man' ) );
        $this->assertEquals( 'Women', $generator->pluralize( 'Woman' ) );
    }

    /**
     * ==========================================================================
     * SchemaGenerator Tests
     * ==========================================================================
     */

    public function test_schema_generator_can_be_instantiated(): void {
        $registry = new FieldTypeRegistry();
        $generator = new SchemaGenerator( $registry );

        $this->assertInstanceOf( SchemaGenerator::class, $generator );
    }

    public function test_schema_generator_generates_schema(): void {
        $registry = new FieldTypeRegistry();
        $generator = new SchemaGenerator( $registry );

        $schema = $generator->generate( [
            'name'    => 'string',
            'email'   => 'email',
            'count'   => 'integer',
            'active'  => 'boolean',
        ] );

        // Should have id field auto-added.
        $this->assertArrayHasKey( 'id', $schema );
        $this->assertEquals( 'bigint', $schema['id']['type'] );
        $this->assertTrue( $schema['id']['primary_key'] );

        // Check field schemas.
        $this->assertArrayHasKey( 'name', $schema );
        $this->assertEquals( 'varchar', $schema['name']['type'] );

        $this->assertArrayHasKey( 'email', $schema );
        $this->assertEquals( 'varchar', $schema['email']['type'] );

        $this->assertArrayHasKey( 'count', $schema );
        $this->assertEquals( 'int', $schema['count']['type'] );

        $this->assertArrayHasKey( 'active', $schema );
        $this->assertEquals( 'tinyint', $schema['active']['type'] );
    }

    public function test_schema_generator_generates_settings(): void {
        $registry = new FieldTypeRegistry();
        $generator = new SchemaGenerator( $registry );

        $settings = $generator->generate_settings( [ 'name' => 'string' ], 2 );

        $this->assertEquals( 2, $settings['version'] );
        $this->assertArrayHasKey( 'schema', $settings );
    }

    /**
     * ==========================================================================
     * UrlBuilder Tests
     * ==========================================================================
     */

    public function test_url_builder_can_be_instantiated(): void {
        $builder = new UrlBuilder( 'my_page' );
        $this->assertInstanceOf( UrlBuilder::class, $builder );
    }

    public function test_url_builder_generates_list_url(): void {
        $builder = new UrlBuilder( 'my_page' );
        $url = $builder->url( 'list' );

        $this->assertStringContainsString( 'page=my_page', $url );
        $this->assertStringNotContainsString( 'action=', $url );
    }

    public function test_url_builder_generates_create_url(): void {
        $builder = new UrlBuilder( 'my_page' );
        $url = $builder->url( 'create' );

        $this->assertStringContainsString( 'page=my_page', $url );
        $this->assertStringContainsString( 'action=create', $url );
    }

    public function test_url_builder_generates_edit_url_with_id(): void {
        $builder = new UrlBuilder( 'my_page' );
        $url = $builder->url( 'edit', 42 );

        $this->assertStringContainsString( 'page=my_page', $url );
        $this->assertStringContainsString( 'action=edit', $url );
        $this->assertStringContainsString( 'id=42', $url );
    }

    public function test_url_builder_generates_nonce_action(): void {
        $builder = new UrlBuilder( 'my_page' );

        $this->assertEquals( 'my_page_create', $builder->get_nonce_action( 'create' ) );
        $this->assertEquals( 'my_page_edit_42', $builder->get_nonce_action( 'edit', 42 ) );
        $this->assertEquals( 'my_page_delete_5', $builder->get_nonce_action( 'delete', 5 ) );
    }

    /**
     * ==========================================================================
     * DataView with CPT Storage Tests
     * ==========================================================================
     */

    public function test_dataview_can_be_instantiated_with_cpt(): void {
        $view = new DataView( [
            'slug'    => 'dv_test_cpt',
            'label'   => 'Item',
            'fields'  => [ 'name' => 'string' ],
            'storage' => 'cpt',
        ] );

        $this->assertInstanceOf( DataView::class, $view );
    }

    public function test_dataview_creates_dataset(): void {
        $view = new DataView( [
            'slug'   => 'dv_test_dataset',
            'label'  => 'Item',
            'fields' => [
                'name'   => 'string',
                'count'  => 'integer',
                'active' => 'boolean',
            ],
            'storage' => 'cpt',
        ] );

        $dataset = $view->get_dataset();
        $fields = $dataset->get_fields();

        $this->assertArrayHasKey( 'name', $fields );
        $this->assertArrayHasKey( 'count', $fields );
        $this->assertArrayHasKey( 'active', $fields );

        $this->assertEquals( DataSet::TYPE_STRING, $fields['name']['type'] );
        $this->assertEquals( DataSet::TYPE_INTEGER, $fields['count']['type'] );
        $this->assertEquals( DataSet::TYPE_BOOLEAN, $fields['active']['type'] );
    }

    public function test_dataview_cpt_creates_plural_object(): void {
        $view = new DataView( [
            'slug'    => 'dv_test_plural',
            'label'   => 'Item',
            'fields'  => [ 'name' => 'string' ],
            'storage' => 'cpt',
        ] );

        $object = $view->get_object();

        $this->assertInstanceOf( PluralObject::class, $object );
        $this->assertInstanceOf( CustomPostTypeStorage::class, $object->get_storage() );
    }

    public function test_dataview_cpt_creates_plural_handler(): void {
        $view = new DataView( [
            'slug'    => 'dv_test_handler',
            'label'   => 'Item',
            'fields'  => [ 'name' => 'string' ],
            'storage' => 'cpt',
        ] );

        $handler = $view->get_handler();

        $this->assertInstanceOf( PluralHandler::class, $handler );
    }

    public function test_dataview_cpt_can_add_validators(): void {
        $view = new DataView( [
            'slug'    => 'dv_test_validators',
            'label'   => 'Item',
            'fields'  => [
                'name'  => 'string',
                'email' => 'email',
            ],
            'storage' => 'cpt',
        ] );

        $handler = $view->get_handler();
        $handler->add_validator( 'name', Validators::required() );
        $handler->add_validator( 'email', Validators::email() );

        // Try to create with invalid data.
        $result = $handler->create( [ 'name' => '', 'email' => 'not-an-email' ] );

        $this->assertTrue( $result->is_error() );
        $this->assertNotEmpty( $result->get_errors() );
    }

    public function test_dataview_cpt_crud_operations(): void {
        $view = new DataView( [
            'slug'    => 'dv_crud_cpt',
            'label'   => 'Item',
            'fields'  => [
                'title' => 'string',
                'count' => 'integer',
            ],
            'storage' => 'cpt',
        ] );

        $view->register();
        $handler = $view->get_handler();

        // Create.
        $result = $handler->create( [ 'title' => 'Test Item', 'count' => 5 ] );
        $this->assertTrue( $result->is_success() );

        $entity = $result->get_entity();
        $id = $entity->get_id();
        $this->assertGreaterThan( 0, $id );

        // Read.
        $result = $handler->read( $id );
        $this->assertTrue( $result->is_success() );
        $this->assertEquals( 'Test Item', $result->get_entity()->get( 'title' ) );

        // Update.
        $result = $handler->update( $id, [ 'title' => 'Updated Item', 'count' => 10 ] );
        $this->assertTrue( $result->is_success() );

        $result = $handler->read( $id );
        $this->assertEquals( 'Updated Item', $result->get_entity()->get( 'title' ) );
        $this->assertEquals( 10, $result->get_entity()->get( 'count' ) );

        // Delete.
        $result = $handler->delete( $id );
        $this->assertTrue( $result->is_success() );

        $result = $handler->read( $id );
        $this->assertTrue( $result->is_error() );
    }

    /**
     * ==========================================================================
     * DataView with Database Storage Tests
     * ==========================================================================
     */

    public function test_dataview_database_requires_tdb(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'    => 'dv_test_db',
            'label'   => 'Item',
            'fields'  => [ 'name' => 'string' ],
            'storage' => 'database',
        ] );

        $this->assertInstanceOf( DataView::class, $view );
    }

    public function test_dataview_database_creates_database_storage(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'    => 'dv_test_db_storage',
            'label'   => 'Item',
            'fields'  => [ 'name' => 'string' ],
            'storage' => 'database',
        ] );

        $object = $view->get_object();

        $this->assertInstanceOf( PluralObject::class, $object );
        $this->assertInstanceOf( DatabaseModuleStorage::class, $object->get_storage() );
    }

    public function test_dataview_database_auto_generates_schema(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'    => 'dv_auto_schema',
            'label'   => 'Item',
            'fields'  => [
                'name'   => 'string',
                'email'  => 'email',
                'bio'    => 'text',
                'active' => 'boolean',
            ],
            'storage' => 'database',
        ] );

        /** @var DatabaseModuleStorage $storage */
        $storage = $view->get_object()->get_storage();
        $table = $storage->get_table();

        // If table was created, the schema worked.
        $this->assertNotNull( $table );
    }

    public function test_dataview_database_crud_operations(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'    => 'dv_crud_db',
            'label'   => 'Item',
            'fields'  => [
                'title'  => 'string',
                'count'  => 'integer',
                'active' => 'boolean',
            ],
            'storage' => 'database',
        ] );

        $handler = $view->get_handler();

        // Create.
        $result = $handler->create( [
            'title'  => 'DB Item',
            'count'  => 42,
            'active' => true,
        ] );
        $this->assertTrue( $result->is_success() );

        $entity = $result->get_entity();
        $id = $entity->get_id();
        $this->assertGreaterThan( 0, $id );

        // Read.
        $result = $handler->read( $id );
        $this->assertTrue( $result->is_success() );
        $this->assertEquals( 'DB Item', $result->get_entity()->get( 'title' ) );
        $this->assertEquals( 42, $result->get_entity()->get( 'count' ) );

        // Update.
        $result = $handler->update( $id, [
            'title'  => 'Updated DB Item',
            'count'  => 100,
            'active' => false,
        ] );
        $this->assertTrue( $result->is_success() );

        $result = $handler->read( $id );
        $this->assertEquals( 'Updated DB Item', $result->get_entity()->get( 'title' ) );
        $this->assertEquals( 100, $result->get_entity()->get( 'count' ) );

        // Delete.
        $result = $handler->delete( $id );
        $this->assertTrue( $result->is_success() );

        $result = $handler->read( $id );
        $this->assertTrue( $result->is_error() );
    }

    public function test_dataview_database_list_operations(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'    => 'dv_list_db',
            'label'   => 'Item',
            'fields'  => [ 'title' => 'string' ],
            'storage' => 'database',
        ] );

        $handler = $view->get_handler();

        // Create multiple items.
        $handler->create( [ 'title' => 'Item 1' ] );
        $handler->create( [ 'title' => 'Item 2' ] );
        $handler->create( [ 'title' => 'Item 3' ] );

        // List all.
        $result = $handler->list();
        $this->assertTrue( $result->is_success() );
        $this->assertCount( 3, $result->get_entities() );
    }

    /**
     * ==========================================================================
     * DataView with Option Storage (Singular Mode) Tests
     * ==========================================================================
     */

    public function test_dataview_singular_creates_singular_object(): void {
        $view = new DataView( [
            'slug'    => 'dv_test_singular',
            'label'   => 'Settings',
            'fields'  => [ 'site_name' => 'string' ],
            'storage' => 'option',
            'mode'    => 'singular',
        ] );

        $object = $view->get_object();

        $this->assertInstanceOf( SingularObject::class, $object );
        $this->assertInstanceOf( OptionStorage::class, $object->get_storage() );
    }

    public function test_dataview_singular_creates_singular_handler(): void {
        $view = new DataView( [
            'slug'    => 'dv_test_singular_handler',
            'label'   => 'Settings',
            'fields'  => [ 'site_name' => 'string' ],
            'storage' => 'option',
            'mode'    => 'singular',
        ] );

        $handler = $view->get_handler();

        $this->assertInstanceOf( SingularHandler::class, $handler );
    }

    public function test_dataview_singular_read_update_operations(): void {
        $view = new DataView( [
            'slug'    => 'dv_singular_ops',
            'label'   => 'Settings',
            'fields'  => [
                'site_name'  => 'string',
                'max_items'  => 'integer',
                'enabled'    => 'boolean',
            ],
            'storage' => 'option',
            'mode'    => 'singular',
        ] );

        $handler = $view->get_handler();

        // Update settings.
        $result = $handler->update( [
            'site_name' => 'My Site',
            'max_items' => 50,
            'enabled'   => true,
        ] );
        $this->assertTrue( $result->is_success() );

        // Read settings.
        $result = $handler->read();
        $this->assertTrue( $result->is_success() );

        $data = $result->get_data();
        $this->assertEquals( 'My Site', $data['site_name'] );
        $this->assertEquals( 50, $data['max_items'] );
        $this->assertTrue( $data['enabled'] );
    }

    public function test_dataview_singular_persists_to_options(): void {
        $view = new DataView( [
            'slug'    => 'dv_singular_persist',
            'label'   => 'Settings',
            'fields'  => [ 'api_key' => 'string' ],
            'storage' => 'option',
            'mode'    => 'singular',
        ] );

        $handler = $view->get_handler();
        $handler->update( [ 'api_key' => 'secret123' ] );

        // Verify it's in WordPress options.
        $saved = get_option( 'dv_singular_persist' );
        $this->assertIsArray( $saved );
        $this->assertEquals( 'secret123', $saved['api_key'] );
    }

    /**
     * ==========================================================================
     * DataView URL Generation Tests
     * ==========================================================================
     */

    public function test_dataview_generates_urls(): void {
        $view = new DataView( [
            'slug'   => 'dv_url_test',
            'label'  => 'Item',
            'fields' => [ 'name' => 'string' ],
            'ui'     => [ 'menu_page' => 'my_custom_page' ],
        ] );

        $list_url = $view->url( 'list' );
        $this->assertStringContainsString( 'page=my_custom_page', $list_url );

        $create_url = $view->url( 'create' );
        $this->assertStringContainsString( 'action=create', $create_url );

        $edit_url = $view->url( 'edit', 42 );
        $this->assertStringContainsString( 'action=edit', $edit_url );
        $this->assertStringContainsString( 'id=42', $edit_url );
    }

    /**
     * ==========================================================================
     * DataView Extended Field Types Tests
     * ==========================================================================
     */

    public function test_dataview_handles_email_field_type(): void {
        $view = new DataView( [
            'slug'    => 'dv_email_test',
            'label'   => 'Contact',
            'fields'  => [ 'email' => 'email' ],
            'storage' => 'cpt',
        ] );

        $view->register();
        $handler = $view->get_handler();

        // Email field should use sanitize_email.
        $result = $handler->create( [ 'email' => 'test@example.com' ] );
        $this->assertTrue( $result->is_success() );

        // Read back - verify it's stored.
        $entity = $result->get_entity();
        $this->assertEquals( 'test@example.com', $entity->get( 'email' ) );
    }

    public function test_dataview_handles_text_field_type(): void {
        $view = new DataView( [
            'slug'    => 'dv_text_test',
            'label'   => 'Note',
            'fields'  => [ 'content' => 'text' ],
            'storage' => 'cpt',
        ] );

        $view->register();
        $handler = $view->get_handler();

        $long_text = "Line 1\nLine 2\nLine 3";
        $result = $handler->create( [ 'content' => $long_text ] );
        $this->assertTrue( $result->is_success() );

        // Text field should preserve newlines.
        $entity = $result->get_entity();
        $this->assertStringContainsString( "\n", $entity->get( 'content' ) );
    }

    /**
     * ==========================================================================
     * DataView Configuration Accessors Tests
     * ==========================================================================
     */

    public function test_dataview_provides_config_access(): void {
        $view = new DataView( [
            'slug'   => 'dv_config_access',
            'label'  => 'Item',
            'fields' => [ 'name' => 'string' ],
        ] );

        $config = $view->get_config();
        $this->assertInstanceOf( DataViewConfig::class, $config );
        $this->assertEquals( 'dv_config_access', $config->slug );
    }

    public function test_dataview_provides_field_registry_access(): void {
        $view = new DataView( [
            'slug'   => 'dv_registry_access',
            'label'  => 'Item',
            'fields' => [ 'name' => 'string' ],
        ] );

        $registry = $view->get_field_registry();
        $this->assertInstanceOf( FieldTypeRegistry::class, $registry );
    }

    /**
     * ==========================================================================
     * DataView Storage Options Tests
     * ==========================================================================
     */

    public function test_config_has_empty_storage_options_by_default(): void {
        $config = new DataViewConfig( [
            'slug'   => 'test_view',
            'label'  => 'Test',
            'fields' => [ 'name' => 'string' ],
        ] );

        $this->assertEquals( [], $config->storage_options );
    }

    public function test_config_accepts_storage_options(): void {
        $config = new DataViewConfig( [
            'slug'            => 'test_view',
            'label'           => 'Test',
            'fields'          => [ 'name' => 'string' ],
            'storage_options' => [
                'version' => 2,
                'custom'  => 'value',
            ],
        ] );

        $this->assertEquals( 2, $config->storage_options['version'] );
        $this->assertEquals( 'value', $config->storage_options['custom'] );
    }

    public function test_dataview_database_uses_storage_options_version(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'            => 'dv_storage_opts',
            'label'           => 'Item',
            'fields'          => [ 'name' => 'string' ],
            'storage'         => 'database',
            'storage_options' => [
                'version' => 5,
            ],
        ] );

        /** @var DatabaseModuleStorage $storage */
        $storage = $view->get_object()->get_storage();
        $table = $storage->get_table();

        // Table should be created with the specified version.
        $this->assertNotNull( $table );
    }

    public function test_dataview_database_storage_options_override_defaults(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        // Create first with version 1.
        $view1 = new DataView( [
            'slug'            => 'dv_opts_override',
            'label'           => 'Item',
            'fields'          => [ 'name' => 'string' ],
            'storage'         => 'database',
            'storage_options' => [
                'version' => 1,
            ],
        ] );

        $handler1 = $view1->get_handler();
        $result = $handler1->create( [ 'name' => 'Test Item' ] );
        $this->assertTrue( $result->is_success() );
        $id = $result->get_entity()->get_id();

        // Create second view with same slug and version - should find the data.
        $view2 = new DataView( [
            'slug'            => 'dv_opts_override',
            'label'           => 'Item',
            'fields'          => [ 'name' => 'string' ],
            'storage'         => 'database',
            'storage_options' => [
                'version' => 1,
            ],
        ] );

        $handler2 = $view2->get_handler();
        $result = $handler2->read( $id );
        $this->assertTrue( $result->is_success() );
        $this->assertEquals( 'Test Item', $result->get_entity()->get( 'name' ) );
    }

    /**
     * ==========================================================================
     * TangibleFieldsRenderer Tests
     * ==========================================================================
     */

    public function test_tangible_fields_renderer_can_be_instantiated(): void {
        $renderer = new \Tangible\Renderer\TangibleFieldsRenderer();
        $this->assertInstanceOf( \Tangible\Renderer\TangibleFieldsRenderer::class, $renderer );
    }

    public function test_tangible_fields_renderer_implements_renderer_interface(): void {
        $renderer = new \Tangible\Renderer\TangibleFieldsRenderer();
        $this->assertInstanceOf( \Tangible\Renderer\Renderer::class, $renderer );
    }

    public function test_tangible_fields_renderer_accepts_field_configs(): void {
        $renderer = new \Tangible\Renderer\TangibleFieldsRenderer();

        $configs = [
            'name' => [ 'type' => 'string' ],
            'items' => [
                'type' => 'repeater',
                'sub_fields' => [
                    [ 'name' => 'title', 'type' => 'string' ],
                ],
            ],
        ];

        // Should not throw.
        $renderer->set_field_configs( $configs );
        $this->assertTrue( true );
    }

    public function test_tangible_fields_renderer_throws_without_framework(): void {
        if ( function_exists( 'tangible_fields' ) ) {
            $this->markTestSkipped( 'Tangible Fields framework is loaded, cannot test missing framework error.' );
        }

        $renderer = new \Tangible\Renderer\TangibleFieldsRenderer();
        $layout = new \Tangible\EditorLayout\Layout( new DataSet() );

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'Tangible Fields framework' );

        $renderer->render_editor( $layout, [] );
    }

    /**
     * ==========================================================================
     * DataView with TangibleFieldsRenderer Tests
     * ==========================================================================
     */

    public function test_dataview_accepts_tangible_fields_renderer(): void {
        $view = new DataView( [
            'slug'   => 'dv_tf_renderer',
            'label'  => 'Item',
            'fields' => [ 'name' => 'string' ],
        ] );

        $renderer = new \Tangible\Renderer\TangibleFieldsRenderer();
        $view->set_renderer( $renderer );

        // Should not throw.
        $this->assertTrue( true );
    }

    public function test_dataview_passes_field_configs_to_tangible_fields_renderer(): void {
        $view = new DataView( [
            'slug'   => 'dv_tf_configs',
            'label'  => 'Item',
            'fields' => [
                'name'  => 'string',
                'items' => [
                    'type'       => 'repeater',
                    'layout'     => 'table',
                    'sub_fields' => [
                        [ 'name' => 'title', 'type' => 'string' ],
                    ],
                ],
            ],
        ] );

        $renderer = new \Tangible\Renderer\TangibleFieldsRenderer();
        $view->set_renderer( $renderer );

        // Verify configs were passed (renderer should have them set).
        // We can't directly access private properties, but the method should not throw.
        $this->assertTrue( true );
    }

    public function test_dataview_with_repeater_creates_proper_schema(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'   => 'dv_repeater_schema',
            'label'  => 'Item',
            'fields' => [
                'name'  => 'string',
                'items' => [
                    'type'       => 'repeater',
                    'sub_fields' => [
                        [ 'name' => 'title', 'type' => 'string' ],
                    ],
                ],
            ],
            'storage' => 'database',
        ] );

        // Repeater field should be stored as longtext.
        $dataset = $view->get_dataset();
        $fields = $dataset->get_fields();

        $this->assertArrayHasKey( 'items', $fields );
        $this->assertEquals( DataSet::TYPE_STRING, $fields['items']['type'] );
    }

    public function test_dataview_repeater_crud_operations(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'   => 'dv_repeater_crud',
            'label'  => 'Item',
            'fields' => [
                'name'  => 'string',
                'items' => [
                    'type'       => 'repeater',
                    'sub_fields' => [
                        [ 'name' => 'title', 'type' => 'string' ],
                        [ 'name' => 'count', 'type' => 'integer' ],
                    ],
                ],
            ],
            'storage' => 'database',
        ] );

        $handler = $view->get_handler();

        // Create with repeater data.
        $repeater_data = [
            [ 'key' => 'row1', 'title' => 'First Item', 'count' => 5 ],
            [ 'key' => 'row2', 'title' => 'Second Item', 'count' => 10 ],
        ];

        $result = $handler->create( [
            'name'  => 'Test',
            'items' => json_encode( $repeater_data ),
        ] );

        $this->assertTrue( $result->is_success() );
        $id = $result->get_entity()->get_id();

        // Read back and verify.
        $result = $handler->read( $id );
        $this->assertTrue( $result->is_success() );

        $saved_items = json_decode( $result->get_entity()->get( 'items' ), true );
        $this->assertIsArray( $saved_items );
        $this->assertCount( 2, $saved_items );
        $this->assertEquals( 'First Item', $saved_items[0]['title'] );
        $this->assertEquals( 10, $saved_items[1]['count'] );

        // Update repeater data.
        $updated_data = [
            [ 'key' => 'row1', 'title' => 'Updated Item', 'count' => 15 ],
        ];

        $result = $handler->update( $id, [
            'name'  => 'Updated Test',
            'items' => json_encode( $updated_data ),
        ] );

        $this->assertTrue( $result->is_success() );

        // Verify update.
        $result = $handler->read( $id );
        $saved_items = json_decode( $result->get_entity()->get( 'items' ), true );
        $this->assertCount( 1, $saved_items );
        $this->assertEquals( 'Updated Item', $saved_items[0]['title'] );

        // Delete.
        $result = $handler->delete( $id );
        $this->assertTrue( $result->is_success() );
    }

    public function test_dataview_repeater_with_empty_value(): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            $this->markTestSkipped( 'Database module (TDB) is not loaded.' );
        }

        $view = new DataView( [
            'slug'   => 'dv_repeater_empty',
            'label'  => 'Item',
            'fields' => [
                'name'  => 'string',
                'items' => [
                    'type'       => 'repeater',
                    'sub_fields' => [
                        [ 'name' => 'title', 'type' => 'string' ],
                    ],
                ],
            ],
            'storage' => 'database',
        ] );

        $handler = $view->get_handler();

        // Create with empty repeater.
        $result = $handler->create( [
            'name'  => 'Test',
            'items' => '[]',
        ] );

        $this->assertTrue( $result->is_success() );
        $id = $result->get_entity()->get_id();

        // Read back.
        $result = $handler->read( $id );
        $saved_items = json_decode( $result->get_entity()->get( 'items' ), true );

        $this->assertIsArray( $saved_items );
        $this->assertEmpty( $saved_items );
    }
}
