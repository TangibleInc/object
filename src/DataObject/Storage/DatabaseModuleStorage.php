<?php declare( strict_types=1 );

namespace Tangible\DataObject\Storage;

use Tangible\DataObject\ListQuery;
use Tangible\DataObject\QueryablePluralStorage;
use TDB_Table;

/**
 * Storage adapter for the Tangible Database Module library.
 *
 * This adapter implements the PluralStorage interface using the database-module
 * library (TDB) instead of WordPress custom post types, providing direct database
 * table storage for entities.
 *
 * Schema fields are real table columns, so ListQuery executes natively:
 * filters and search become a prepared WHERE clause, ordering becomes
 * ORDER BY on a schema-whitelisted column, pagination becomes LIMIT/OFFSET.
 * Nothing outside the requested page is loaded into PHP.
 *
 * @see https://bitbucket.org/tangibleinc/tangible-database-module
 */
class DatabaseModuleStorage implements QueryablePluralStorage {

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

        return $this->map_rows( $rows );
    }

    /**
     * Execute a ListQuery natively as SQL against the table.
     *
     * @param ListQuery $query The list query.
     * @return array Data rows (each including 'id') for the requested page.
     */
    public function query( ListQuery $query ): array {
        if ( ! $this->table ) {
            return [];
        }

        $sql = 'SELECT * FROM ' . $this->table->db->table_name
            . $this->build_where( $query )
            . $this->build_order( $query )
            . $this->build_limit( $query );

        $rows = $GLOBALS['wpdb']->get_results( $sql );

        return $rows ? $this->map_rows( $rows ) : [];
    }

    public function count( ListQuery $query ): int {
        if ( ! $this->table ) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM ' . $this->table->db->table_name
            . $this->build_where( $query );

        return (int) $GLOBALS['wpdb']->get_var( $sql );
    }

    /**
     * Cast result rows to arrays and expose the primary key as 'id'.
     *
     * @param array $rows Raw wpdb result rows.
     * @return array Data rows.
     */
    protected function map_rows( array $rows ): array {
        $results     = [];
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

    /**
     * The queryable column whitelist: schema fields plus the primary key.
     *
     * Filter, search and order fields are validated against this list
     * before being interpolated into SQL.
     *
     * @return string[] Column names.
     */
    protected function schema_columns(): array {
        $db      = $this->table->db;
        $columns = array_keys( (array) $db->schema->fields );

        if ( ! in_array( $db->primary_key, $columns, true ) ) {
            $columns[] = $db->primary_key;
        }

        return $columns;
    }

    /**
     * Map the row-level 'id' alias back to the primary key column.
     *
     * @param string $field Field name from the query.
     * @return string Column name.
     */
    protected function normalize_column( string $field ): string {
        return $field === 'id' ? $this->table->db->primary_key : $field;
    }

    /**
     * Build the WHERE clause for a query (also used by count()).
     *
     * Preserves ListQuery's in-memory reference semantics: a filter on
     * a field rows don't have matches nothing, as does a search whose
     * declared fields are all unknown.
     *
     * @param ListQuery $query The list query.
     * @return string Leading-space WHERE clause, or empty string.
     */
    protected function build_where( ListQuery $query ): string {
        $wpdb    = $GLOBALS['wpdb'];
        $columns = $this->schema_columns();
        $clauses = [];

        foreach ( $query->filters as $field => $value ) {
            $column = $this->normalize_column( (string) $field );
            if ( ! in_array( $column, $columns, true ) ) {
                return ' WHERE 1 = 0';
            }
            $clauses[] = $wpdb->prepare( "`{$column}` = %s", (string) $value );
        }

        if ( $query->search !== '' ) {
            $fields = $query->search_fields !== []
                ? array_values( array_intersect(
                    array_map( [ $this, 'normalize_column' ], $query->search_fields ),
                    $columns
                ) )
                : $columns;

            if ( $fields === [] ) {
                return ' WHERE 1 = 0';
            }

            $like     = '%' . $wpdb->esc_like( $query->search ) . '%';
            $searches = [];
            foreach ( $fields as $field ) {
                $searches[] = $wpdb->prepare( "`{$field}` LIKE %s", $like );
            }
            $clauses[] = '( ' . implode( ' OR ', $searches ) . ' )';
        }

        return $clauses === [] ? '' : ' WHERE ' . implode( ' AND ', $clauses );
    }

    /**
     * Build the ORDER BY clause. Unknown order fields preserve storage
     * order, matching the in-memory fallback.
     *
     * @param ListQuery $query The list query.
     * @return string Leading-space ORDER BY clause, or empty string.
     */
    protected function build_order( ListQuery $query ): string {
        if ( $query->orderby === '' ) {
            return '';
        }

        $column = $this->normalize_column( $query->orderby );
        if ( ! in_array( $column, $this->schema_columns(), true ) ) {
            return '';
        }

        $direction = $query->order === 'desc' ? 'DESC' : 'ASC';

        return " ORDER BY `{$column}` {$direction}";
    }

    /**
     * Build the LIMIT/OFFSET clause.
     *
     * @param ListQuery $query The list query.
     * @return string Leading-space LIMIT clause, or empty string when unpaginated.
     */
    protected function build_limit( ListQuery $query ): string {
        if ( $query->per_page <= 0 ) {
            return '';
        }

        return $GLOBALS['wpdb']->prepare( ' LIMIT %d OFFSET %d', $query->per_page, $query->offset() );
    }
}
