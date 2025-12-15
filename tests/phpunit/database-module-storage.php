<?php
namespace Tangible\Object\Tests;

use Tangible\DataObject\DataSet;
use Tangible\DataObject\PluralObject;
use Tangible\DataObject\PluralStorage;
use Tangible\DataObject\Storage\DatabaseModuleStorage;

/**
 * Tests for the DatabaseModuleStorage adapter.
 *
 * These tests verify that DatabaseModuleStorage correctly implements
 * the PluralStorage interface using the database-module (TDB) library.
 *
 * @covers \Tangible\DataObject\Storage\DatabaseModuleStorage
 * @covers \Tangible\DataObject\PluralObject
 * @covers \Tangible\DataObject\PluralObject\Entity
 */
class DatabaseModuleStorage_TestCase extends \WP_UnitTestCase {

    /**
     * Check if database-module is available.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        if ( ! function_exists( 'tdb_register_table' ) ) {
            self::markTestSkipped( 'Database module (TDB) is not loaded. Skipping DatabaseModuleStorage tests.' );
        }
    }

    /**
     * ==========================================================================
     * Basic Instantiation
     * ==========================================================================
     */

    public function test_storage_can_be_instantiated(): void {
        $storage = new DatabaseModuleStorage( 'test_items' );
        $this->assertInstanceOf( DatabaseModuleStorage::class, $storage );
    }

    public function test_storage_implements_plural_storage_interface(): void {
        $storage = new DatabaseModuleStorage( 'test_items' );
        $this->assertInstanceOf( PluralStorage::class, $storage );
    }

    /**
     * ==========================================================================
     * Registration
     * ==========================================================================
     */

    public function test_storage_can_register_table(): void {
        $storage = new DatabaseModuleStorage( 'tdb_test_register' );

        $storage->register( 'tdb_test_register', [
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
            ],
        ] );

        $this->assertNotNull( $storage->get_table() );
    }

    /**
     * ==========================================================================
     * CRUD Operations
     * ==========================================================================
     */

    public function test_storage_can_insert_data(): void {
        $storage = $this->create_test_storage( 'tdb_test_insert' );

        $id = $storage->insert( [ 'title' => 'Test Item' ] );

        $this->assertGreaterThan( 0, $id );
    }

    public function test_storage_can_find_by_id(): void {
        $storage = $this->create_test_storage( 'tdb_test_find' );

        $id = $storage->insert( [ 'title' => 'Findable Item' ] );
        $found = $storage->find( $id );

        $this->assertIsArray( $found );
        $this->assertEquals( 'Findable Item', $found['title'] );
    }

    public function test_storage_find_returns_null_for_nonexistent(): void {
        $storage = $this->create_test_storage( 'tdb_test_find_null' );

        $found = $storage->find( 999999 );

        $this->assertNull( $found );
    }

    public function test_storage_can_update_data(): void {
        $storage = $this->create_test_storage( 'tdb_test_update' );

        $id = $storage->insert( [ 'title' => 'Original' ] );
        $storage->update( $id, [ 'title' => 'Updated' ] );

        $found = $storage->find( $id );
        $this->assertEquals( 'Updated', $found['title'] );
    }

    public function test_storage_can_delete_data(): void {
        $storage = $this->create_test_storage( 'tdb_test_delete' );

        $id = $storage->insert( [ 'title' => 'To Delete' ] );
        $storage->delete( $id );

        $found = $storage->find( $id );
        $this->assertNull( $found );
    }

    public function test_storage_can_retrieve_all(): void {
        $storage = $this->create_test_storage( 'tdb_test_all' );

        $storage->insert( [ 'title' => 'Item 1' ] );
        $storage->insert( [ 'title' => 'Item 2' ] );
        $storage->insert( [ 'title' => 'Item 3' ] );

        $all = $storage->all();

        $this->assertCount( 3, $all );
    }

    public function test_all_includes_id_field(): void {
        $storage = $this->create_test_storage( 'tdb_test_all_id' );

        $id = $storage->insert( [ 'title' => 'Item with ID' ] );
        $all = $storage->all();

        $this->assertArrayHasKey( 'id', $all[0] );
        $this->assertEquals( $id, $all[0]['id'] );
    }

    /**
     * ==========================================================================
     * Integration with PluralObject
     * ==========================================================================
     */

    public function test_storage_works_with_plural_object(): void {
        $storage = $this->create_test_storage( 'tdb_plural_obj' );

        $dataset = new DataSet();
        $dataset->add_string( 'title' );
        $dataset->add_integer( 'count' );

        $object = new PluralObject( 'tdb_plural_obj', $storage );
        $object->set_dataset( $dataset );

        // Create
        $entity = $object->create( [
            'title' => 'Test Entity',
            'count' => 42,
        ] );

        $this->assertGreaterThan( 0, $entity->get_id() );
        $this->assertEquals( 'Test Entity', $entity->get('title') );

        // Find
        $found = $object->find( $entity->get_id() );
        $this->assertNotNull( $found );
        $this->assertEquals( 'Test Entity', $found->get('title') );

        // Update
        $entity->set( 'title', 'Updated Entity' );
        $object->save( $entity );

        $reloaded = $object->find( $entity->get_id() );
        $this->assertEquals( 'Updated Entity', $reloaded->get('title') );

        // Delete
        $object->delete( $entity );
        $deleted = $object->find( $entity->get_id() );
        $this->assertNull( $deleted );
    }

    public function test_storage_list_works_with_plural_object(): void {
        $storage = $this->create_test_storage( 'tdb_plural_list' );

        $dataset = new DataSet();
        $dataset->add_string( 'title' );

        $object = new PluralObject( 'tdb_plural_list', $storage );
        $object->set_dataset( $dataset );

        $object->create( [ 'title' => 'Entity 1' ] );
        $object->create( [ 'title' => 'Entity 2' ] );

        $all = $object->all();

        $this->assertCount( 2, $all );
        $this->assertGreaterThan( 0, $all[0]->get_id() );
    }

    /**
     * Helper to create a registered storage with a standard schema.
     */
    private function create_test_storage( string $name ): DatabaseModuleStorage {
        $storage = new DatabaseModuleStorage( $name );

        $storage->register( $name, [
            'version' => 1,
            'schema'  => [
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
                'count' => [
                    'type'    => 'int',
                    'length'  => '11',
                    'default' => 0,
                ],
            ],
        ] );

        return $storage;
    }
}
