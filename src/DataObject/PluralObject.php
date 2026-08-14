<?php declare( strict_types=1 );
/**
 * The PluralObject class file.
 *
 * @package @tangible/framework
 */

namespace Tangible\DataObject;

use Tangible\DataObject\PluralObject\Entity;
use Tangible\DataObject\Storage\CustomPostTypeStorage;

/**
 * The PluralObject class is the base for objects that may exist
 * in multiple instances, like posts, pages, etc.
 */
class PluralObject extends BaseDataObject {

    protected PluralStorage $storage;
    protected string $slug;

    protected const DEFAULT_SETTINGS = [
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'supports'     => [ 'title' ],
    ];


    /**
     * Creates a new plural object with optional storage.
     *
     * @param string $slug the object slug.
     * @param PluralStorage|null $storage the storage object to use.
     */
    public function __construct( string $slug, PluralStorage|null $storage = null ) {
        $this->slug = $slug;

        if ( $storage !== null ) {
            $this->storage = $storage;
        } else {
            $this->storage = new CustomPostTypeStorage( $slug );
        }
    }

    public function get_storage(): PluralStorage {
        return $this->storage;
    }

    /**
     * Register the custom post type to represent the plural object.
     *
     * @return PluralObject
     */
    public function register( array $settings ): PluralObject {
        $merged = array_merge( self::DEFAULT_SETTINGS, $settings );
        $this->storage->register( $this->slug, $merged );
        return $this;
    }

    public function create( array $data ): Entity {
        $id = $this->storage->insert( $data );
        return $this->hydrateEntity( $id, $data );
    }

    public function save( Entity $entity ): PluralObject {
        $this->storage->update( $entity->get_id(), $entity->get_data() );
        return $this;
    }

    public function find( int $id ): Entity|null {
        $data = $this->storage->find( $id );
        if ( $data === null ) {
            return null;
        }
        return $this->hydrateEntity( $id, $data );
    }

    public function delete( Entity $entity ): void {
        $this->storage->delete( $entity->get_id() );
    }

    public function all(): array {
        $results = [];
        foreach ( $this->storage->all() as $data ) {
            $results[] = $this->hydrateEntity( $data['id'], $data );
        }
        return $results;
    }

    /**
     * Return the entities matching a list query, ordered and paginated.
     *
     * Delegates to the storage when it can execute the query natively;
     * otherwise applies the query in memory over all().
     *
     * @param ListQuery $query The list query.
     * @return Entity[] The matching page of entities.
     */
    public function query( ListQuery $query ): array {
        if ( $this->storage instanceof QueryablePluralStorage ) {
            $results = [];
            foreach ( $this->storage->query( $query ) as $data ) {
                $results[] = $this->hydrateEntity( $data['id'], $data );
            }
            return $results;
        }

        $results = [];
        foreach ( $query->apply( $this->storage->all() ) as $data ) {
            $results[] = $this->hydrateEntity( $data['id'], $data );
        }
        return $results;
    }

    /**
     * Count the entities matching a list query, ignoring pagination.
     *
     * @param ListQuery $query The list query.
     * @return int The matching entity count.
     */
    public function count( ListQuery $query ): int {
        if ( $this->storage instanceof QueryablePluralStorage ) {
            return $this->storage->count( $query );
        }

        return $query->count_matching( $this->storage->all() );
    }

    private function hydrateEntity( int $id, array $data ): Entity {
        $entity = new Entity( $data );
        $entity->set_id( $id );

        return $entity;
    }

}
