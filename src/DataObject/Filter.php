<?php declare( strict_types=1 );
/**
 * The Filter class file.
 *
 * @package @tangible/framework
 */

namespace Tangible\DataObject;

use InvalidArgumentException;

/**
 * A single field constraint inside a ListQuery.
 *
 * ListQuery::$filters maps field names to constraints. A plain scalar is
 * shorthand for Filter::equals(); a plain array is shorthand for
 * Filter::in(); a Filter instance selects any other operator:
 *
 *     new ListQuery( filters: [
 *         'status'        => 'published',                 // = 'published'
 *         'type'          => [ 'video', 'text' ],         // IN ('video', 'text')
 *         'superseded_by' => Filter::is_null(),           // IS NULL
 *         'weight'        => Filter::at_least( 10 ),      // >= 10
 *     ] );
 *
 * matches() defines the reference semantics every storage translation
 * must preserve (see ListQuery):
 *
 * - Equality is string-loose: filter values usually arrive from URLs as
 *   strings while stored values may be int, bool or null.
 * - IS NULL / IS NOT NULL test the PHP null, never the empty string.
 * - Comparisons use the same rule as ordering: numeric pairs compare
 *   numerically, everything else case-insensitively as strings, and null
 *   sorts before every value. Storages that compare text columns under
 *   a collation may differ from this rule for numeric-looking strings.
 */
final class Filter {

    public const EQUALS       = '=';
    public const NOT_EQUALS   = '!=';
    public const LESS_THAN    = '<';
    public const AT_MOST      = '<=';
    public const GREATER_THAN = '>';
    public const AT_LEAST     = '>=';
    public const IN           = 'in';
    public const IS_NULL      = 'null';
    public const IS_NOT_NULL  = 'not_null';

    /**
     * Operators whose operand must be a single scalar.
     */
    private const SCALAR_OPERATORS = [
        self::EQUALS,
        self::NOT_EQUALS,
        self::LESS_THAN,
        self::AT_MOST,
        self::GREATER_THAN,
        self::AT_LEAST,
    ];

    /**
     * @param string $operator One of the operator constants.
     * @param mixed  $value    The operand: a scalar, a scalar[] for IN, null for the null tests.
     */
    private function __construct(
        public readonly string $operator,
        public readonly mixed $value = null
    ) {
        if ( in_array( $operator, self::SCALAR_OPERATORS, true ) && ! is_scalar( $value ) ) {
            throw new InvalidArgumentException( "Filter operator '{$operator}' requires a scalar operand." );
        }
        if ( $operator === self::IN ) {
            if ( ! is_array( $value ) ) {
                throw new InvalidArgumentException( 'Filter operator IN requires an array operand.' );
            }
            foreach ( $value as $item ) {
                if ( ! is_scalar( $item ) ) {
                    throw new InvalidArgumentException( 'Filter operator IN requires scalar list items.' );
                }
            }
        }
    }

    /**
     * Normalize a ListQuery filter value into a Filter.
     *
     * Scalars become equality, arrays become IN, Filters pass through.
     *
     * @param mixed $value A scalar, a scalar[] or a Filter.
     * @return self The constraint.
     */
    public static function from( mixed $value ): self {
        if ( $value instanceof self ) {
            return $value;
        }
        if ( is_array( $value ) ) {
            return self::in( $value );
        }
        if ( $value === null ) {
            // A bare null has always stringified to '' under the loose
            // equality rule; keep that rather than silently turning it
            // into IS NULL. Callers wanting the null test say so.
            return self::equals( '' );
        }
        return self::equals( $value );
    }

    public static function equals( int|float|string|bool $value ): self {
        return new self( self::EQUALS, $value );
    }

    public static function not_equals( int|float|string|bool $value ): self {
        return new self( self::NOT_EQUALS, $value );
    }

    public static function less_than( int|float|string|bool $value ): self {
        return new self( self::LESS_THAN, $value );
    }

    public static function at_most( int|float|string|bool $value ): self {
        return new self( self::AT_MOST, $value );
    }

    public static function greater_than( int|float|string|bool $value ): self {
        return new self( self::GREATER_THAN, $value );
    }

    public static function at_least( int|float|string|bool $value ): self {
        return new self( self::AT_LEAST, $value );
    }

    /**
     * @param array<int|float|string|bool> $values Accepted values; an empty list matches nothing.
     */
    public static function in( array $values ): self {
        return new self( self::IN, array_values( $values ) );
    }

    public static function is_null(): self {
        return new self( self::IS_NULL );
    }

    public static function is_not_null(): self {
        return new self( self::IS_NOT_NULL );
    }

    /**
     * Whether a stored value satisfies this constraint (reference semantics).
     *
     * @param mixed $actual The stored field value: a scalar or null.
     * @return bool True when the value passes.
     */
    public function matches( mixed $actual ): bool {
        if ( ! is_scalar( $actual ) && $actual !== null ) {
            return false;
        }

        switch ( $this->operator ) {
            case self::IS_NULL:
                return $actual === null;
            case self::IS_NOT_NULL:
                return $actual !== null;
            case self::IN:
                foreach ( $this->value as $candidate ) {
                    if ( (string) $actual === (string) $candidate ) {
                        return true;
                    }
                }
                return false;
            case self::EQUALS:
                return (string) $actual === (string) $this->value;
            case self::NOT_EQUALS:
                return (string) $actual !== (string) $this->value;
            case self::LESS_THAN:
                return self::compare( $actual, $this->value ) < 0;
            case self::AT_MOST:
                return self::compare( $actual, $this->value ) <= 0;
            case self::GREATER_THAN:
                return self::compare( $actual, $this->value ) > 0;
            case self::AT_LEAST:
                return self::compare( $actual, $this->value ) >= 0;
        }

        return false;
    }

    /**
     * Compare two field values, the rule shared by comparison filters
     * and ordering.
     *
     * Numeric pairs compare numerically, everything else compares as
     * case-insensitive strings. Nulls sort before any value.
     *
     * @param mixed $a First value.
     * @param mixed $b Second value.
     * @return int Spaceship-style comparison result.
     */
    public static function compare( mixed $a, mixed $b ): int {
        if ( $a === null || $b === null ) {
            return ( $a === null ? 0 : 1 ) <=> ( $b === null ? 0 : 1 );
        }
        if ( is_numeric( $a ) && is_numeric( $b ) ) {
            return $a <=> $b;
        }
        return strcasecmp( (string) $a, (string) $b );
    }
}
