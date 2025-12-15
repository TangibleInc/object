<?php declare( strict_types=1 );

namespace Tangible\DataObject\Storage;

use Tangible\DataObject\PluralStorage;
use TDB_Table;

/**
 * Storage adapter for the Tangible Database Module library.
 *
 * This adapter implements the PluralStorage interface using the database-module
 * library (TDB) instead of WordPress custom post types, providing direct database
 * table storage for entities.
 *
 * @see https://bitbucket.org/tangibleinc/tangible-database-module
 */
class DatabaseModuleStorage implements PluralStorage {

    protected string $slug;

    protected ?TDB_Table $table = null;

    public function __construct( string $slug ) {
        $this->slug = $slug;
    }

    /**
     * Register a database table using the database-module library.
     *
     * @param string $slug     The table name/slug.
     * @param array  $settings Settings array, should include 'schema' for field definitions.
     */
    public function register( string $slug, array $settings ): void {
        if ( ! function_exists( 'tdb_register_table' ) ) {
            return;
        }

        $defaults = [
            'show_ui'      => false,
            'show_in_rest' => false,
            'version'      => 1,
        ];

        $settings = array_merge( $defaults, $settings );

        $this->table = tdb_register_table( $slug, $settings );

        // Ensure table is created (normally happens on admin_init)
        if ( $this->table && $this->table->db ) {
            $this->table->db->maybe_upgrade();
        }
    }

    /**
     * Get the underlying TDB_Table instance.
     */
    public function get_table(): ?TDB_Table {
        return $this->table;
    }

    public function insert( array $data ): int {
        if ( ! $this->table ) {
            return 0;
        }

        $id = $this->table->db->insert( $data );

        return $id ? (int) $id : 0;
    }

    public function update( int $id, array $data ): void {
        if ( ! $this->table ) {
            return;
        }

        $primary_key = $this->table->db->primary_key;

        $this->table->db->update( $data, [ $primary_key => $id ] );
    }

    public function delete( int $id ): void {
        if ( ! $this->table ) {
            return;
        }

        $this->table->db->delete( $id );
    }

    public function find( int $id ): ?array {
        if ( ! $this->table ) {
            return null;
        }

        $row = $this->table->db->get( $id );

        if ( ! $row ) {
            return null;
        }

        return (array) $row;
    }

    public function all(): array {
        if ( ! $this->table ) {
            return [];
        }

        $rows = $this->table->db->query();

        if ( ! $rows ) {
            return [];
        }

        $results = [];
        $primary_key = $this->table->db->primary_key;

        foreach ( $rows as $row ) {
            $data = (array) $row;
            if ( isset( $data[ $primary_key ] ) ) {
                $data['id'] = (int) $data[ $primary_key ];
            }
            $results[] = $data;
        }

        return $results;
    }
}
