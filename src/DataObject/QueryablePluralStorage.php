<?php declare( strict_types=1 );
/**
 * The QueryablePluralStorage interface file.
 *
 * @package @tangible/framework
 */

namespace Tangible\DataObject;

/**
 * Optional storage capability: native ListQuery execution.
 *
 * PluralStorage implementations that can translate a ListQuery to
 * their backend (SQL LIMIT/ORDER/WHERE, WP_Query, ...) implement this
 * interface; PluralObject then delegates instead of loading all()
 * and applying the query in memory. Implementations must preserve
 * ListQuery's reference semantics (see that class).
 */
interface QueryablePluralStorage extends PluralStorage {

    /**
     * Return the data rows (each including 'id') matching the query,
     * ordered and paginated.
     *
     * @param ListQuery $query The list query.
     * @return array Data rows.
     */
    public function query( ListQuery $query ): array;

    /**
     * Count the rows matching the query, ignoring pagination.
     *
     * @param ListQuery $query The list query.
     * @return int Matching row count.
     */
    public function count( ListQuery $query ): int;
}
