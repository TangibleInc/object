<?php declare( strict_types=1 );
/**
 * The ListQuery class file.
 *
 * @package @tangible/framework
 */

namespace Tangible\DataObject;

/**
 * Value object describing a list request: pagination, ordering,
 * search and field filters.
 *
 * Consumed in three places:
 * - QueryablePluralStorage implementations translate it to their
 *   native query language (SQL, WP_Query, ...).
 * - PluralObject / PluralHandler apply it in memory as a universal
 *   fallback for storages and handlers that predate it.
 * - The DataView RequestRouter builds it from list-page request
 *   parameters (paged / orderby / order / s / filter_*).
 *
 * The in-memory helpers define the reference semantics: filters are
 * string-loose equality, search is a case-insensitive substring match
 * over the declared search fields, ordering compares numerically when
 * both values are numeric and case-insensitively otherwise. Storage
 * implementations should preserve these semantics.
 */
class ListQuery {

    /**
     * Current page, 1-based.
     *
     * @var int
     */
    public readonly int $page;

    /**
     * Items per page. 0 disables pagination (return everything).
     *
     * @var int
     */
    public readonly int $per_page;

    /**
     * Field to order by. Empty string preserves storage order.
     *
     * @var string
     */
    public readonly string $orderby;

    /**
     * Order direction: 'asc' or 'desc'.
     *
     * @var string
     */
    public readonly string $order;

    /**
     * Search term. Empty string means no search.
     *
     * @var string
     */
    public readonly string $search;

    /**
     * Fields the search term is matched against. When empty, the
     * search matches against every scalar field value.
     *
     * @var string[]
     */
    public readonly array $search_fields;

    /**
     * Field filters as field => value equality constraints.
     *
     * @var array<string, scalar>
     */
    public readonly array $filters;

    /**
     * Create a new ListQuery, normalizing out-of-range values.
     *
     * @param int      $page          Page number (clamped to >= 1).
     * @param int      $per_page      Items per page (clamped to >= 0; 0 = unpaginated).
     * @param string   $orderby       Field to order by ('' = storage order).
     * @param string   $order         'asc' or 'desc' (anything else becomes 'asc').
     * @param string   $search        Search term.
     * @param string[] $search_fields Fields to search in.
     * @param array    $filters       Field => value equality filters.
     */
    public function __construct(
        int $page = 1,
        int $per_page = 20,
        string $orderby = '',
        string $order = 'asc',
        string $search = '',
        array $search_fields = [],
        array $filters = []
    ) {
        $this->page          = max( 1, $page );
        $this->per_page      = max( 0, $per_page );
        $this->orderby       = $orderby;
        $this->order         = strtolower( $order ) === 'desc' ? 'desc' : 'asc';
        $this->search        = $search;
        $this->search_fields = array_values( $search_fields );
        $this->filters       = $filters;
    }

    /**
     * The row offset this query's page starts at.
     *
     * @return int Zero-based offset.
     */
    public function offset(): int {
        return $this->per_page > 0 ? ( $this->page - 1 ) * $this->per_page : 0;
    }

    /**
     * Whether a data row matches the search term and filters.
     *
     * @param array $row Field => value data row.
     * @return bool True when the row survives search and filters.
     */
    public function matches( array $row ): bool {
        foreach ( $this->filters as $field => $value ) {
            if ( ! array_key_exists( $field, $row ) ) {
                return false;
            }
            $actual = $row[ $field ];
            if ( ! is_scalar( $actual ) && $actual !== null ) {
                return false;
            }
            // String-loose equality: filter values usually arrive from
            // URLs as strings while stored values may be int or bool.
            if ( (string) $actual !== (string) $value ) {
                return false;
            }
        }

        if ( $this->search === '' ) {
            return true;
        }

        $fields = $this->search_fields !== []
            ? $this->search_fields
            : array_keys( $row );

        foreach ( $fields as $field ) {
            $value = $row[ $field ] ?? null;
            if ( is_scalar( $value ) && stripos( (string) $value, $this->search ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count the items matching this query, ignoring pagination.
     *
     * @param array    $items    The items to count.
     * @param callable $accessor Optional item => data-row accessor.
     * @return int Number of matching items.
     */
    public function count_matching( array $items, ?callable $accessor = null ): int {
        $count = 0;
        foreach ( $items as $item ) {
            if ( $this->matches( $accessor !== null ? $accessor( $item ) : $item ) ) {
                ++$count;
            }
        }
        return $count;
    }

    /**
     * Apply the full query to a set of items in memory:
     * filter + search, then order, then paginate.
     *
     * Items may be data rows themselves, or anything an accessor can
     * turn into one (e.g. entities) — the returned array holds the
     * surviving ORIGINAL items, in query order.
     *
     * @param array    $items    The items to query.
     * @param callable $accessor Optional item => data-row accessor.
     * @return array The matching page of items.
     */
    public function apply( array $items, ?callable $accessor = null ): array {
        $row = $accessor ?? static fn( $item ) => $item;

        $matched = array_values( array_filter(
            $items,
            fn( $item ) => $this->matches( $row( $item ) )
        ) );

        if ( $this->orderby !== '' ) {
            // usort() is stable in PHP 8, so equal keys keep storage order.
            usort( $matched, function ( $a, $b ) use ( $row ) {
                $result = $this->compare_values(
                    $row( $a )[ $this->orderby ] ?? null,
                    $row( $b )[ $this->orderby ] ?? null
                );
                return $this->order === 'desc' ? -$result : $result;
            } );
        }

        if ( $this->per_page > 0 ) {
            $matched = array_slice( $matched, $this->offset(), $this->per_page );
        }

        return $matched;
    }

    /**
     * Compare two field values for ordering.
     *
     * Numeric pairs compare numerically, everything else compares as
     * case-insensitive strings. Nulls sort before any value.
     *
     * @param mixed $a First value.
     * @param mixed $b Second value.
     * @return int Spaceship-style comparison result.
     */
    protected function compare_values( mixed $a, mixed $b ): int {
        if ( $a === null || $b === null ) {
            return ( $a === null ? 0 : 1 ) <=> ( $b === null ? 0 : 1 );
        }
        if ( is_numeric( $a ) && is_numeric( $b ) ) {
            return $a <=> $b;
        }
        return strcasecmp( (string) $a, (string) $b );
    }
}
