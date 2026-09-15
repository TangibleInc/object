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
 * The in-memory helpers define the reference semantics: a plain filter
 * value is string-loose equality and a Filter instance selects another
 * operator (IN, IS NULL, comparisons — see Filter::matches()), search
 * is a case-insensitive substring match over the declared search
 * fields, ordering compares numerically when both values are numeric
 * and case-insensitively otherwise, key by key when several are given.
 * Storage implementations should preserve these semantics.
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
     * Primary field to order by. Empty string preserves storage order.
     *
     * The first key of $ordering, kept for callers that only know a
     * single sort column.
     *
     * @var string
     */
    public readonly string $orderby;

    /**
     * Direction of the primary order field: 'asc' or 'desc'.
     *
     * @var string
     */
    public readonly string $order;

    /**
     * The full ordering, field => 'asc'|'desc', in priority order.
     * Empty preserves storage order.
     *
     * @var array<string, string>
     */
    public readonly array $ordering;

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
     * Field filters as given: field => scalar (equality), scalar[] (IN)
     * or Filter. See constraints() for the normalized form.
     *
     * @var array<string, scalar|array|Filter>
     */
    public readonly array $filters;

    /**
     * The filters normalized to Filter instances, field => Filter.
     *
     * @var array<string, Filter>
     */
    private array $constraints;

    /**
     * Create a new ListQuery, normalizing out-of-range values.
     *
     * Ordering accepts a single field name, or an array for multi-column
     * ordering: field => 'asc'|'desc' pairs in priority order, or a plain
     * list of field names that all take $order.
     *
     * @param int          $page          Page number (clamped to >= 1).
     * @param int          $per_page      Items per page (clamped to >= 0; 0 = unpaginated).
     * @param string|array $orderby       Field to order by ('' = storage order), or an ordering array.
     * @param string       $order         'asc' or 'desc' (anything else becomes 'asc').
     * @param string       $search        Search term.
     * @param string[]     $search_fields Fields to search in.
     * @param array        $filters       Field => scalar, scalar[] or Filter constraints.
     */
    public function __construct(
        int $page = 1,
        int $per_page = 20,
        string|array $orderby = '',
        string $order = 'asc',
        string $search = '',
        array $search_fields = [],
        array $filters = []
    ) {
        $this->page          = max( 1, $page );
        $this->per_page      = max( 0, $per_page );
        $this->search        = $search;
        $this->search_fields = array_values( $search_fields );
        $this->filters       = $filters;

        $default_order = strtolower( $order ) === 'desc' ? 'desc' : 'asc';
        $ordering      = [];

        foreach ( is_string( $orderby ) ? [ $orderby ] : $orderby as $key => $value ) {
            if ( is_int( $key ) ) {
                $field     = (string) $value;
                $direction = $default_order;
            } else {
                $field     = (string) $key;
                $direction = strtolower( (string) $value ) === 'desc' ? 'desc' : 'asc';
            }
            if ( $field === '' || isset( $ordering[ $field ] ) ) {
                continue;
            }
            $ordering[ $field ] = $direction;
        }

        $this->ordering = $ordering;
        $this->orderby  = (string) ( array_key_first( $ordering ) ?? '' );
        $this->order    = $ordering[ $this->orderby ] ?? $default_order;

        $this->constraints = array_map( [ Filter::class, 'from' ], $filters );
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
     * The filters as Filter instances, field => Filter, so storages can
     * translate operators without repeating the shorthand rules.
     *
     * @return array<string, Filter> The normalized constraints.
     */
    public function constraints(): array {
        return $this->constraints;
    }

    /**
     * Whether a data row matches the search term and filters.
     *
     * @param array $row Field => value data row.
     * @return bool True when the row survives search and filters.
     */
    public function matches( array $row ): bool {
        foreach ( $this->constraints as $field => $filter ) {
            // A filter on a field the row does not have matches nothing,
            // whatever the operator.
            if ( ! array_key_exists( $field, $row ) ) {
                return false;
            }
            if ( ! $filter->matches( $row[ $field ] ) ) {
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

        if ( $this->ordering !== [] ) {
            // usort() is stable in PHP 8, so equal keys keep storage order.
            usort( $matched, function ( $a, $b ) use ( $row ) {
                $row_a = $row( $a );
                $row_b = $row( $b );
                foreach ( $this->ordering as $field => $direction ) {
                    $result = $this->compare_values( $row_a[ $field ] ?? null, $row_b[ $field ] ?? null );
                    if ( $result !== 0 ) {
                        return $direction === 'desc' ? -$result : $result;
                    }
                }
                return 0;
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
     * case-insensitive strings. Nulls sort before any value. The same
     * rule drives comparison filters (Filter::compare()).
     *
     * @param mixed $a First value.
     * @param mixed $b Second value.
     * @return int Spaceship-style comparison result.
     */
    protected function compare_values( mixed $a, mixed $b ): int {
        return Filter::compare( $a, $b );
    }
}
