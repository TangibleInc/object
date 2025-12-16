<?php declare( strict_types=1 );

namespace Tangible\DataView;

/**
 * Generates database schema from field definitions.
 */
class SchemaGenerator {

    protected FieldTypeRegistry $registry;

    public function __construct( FieldTypeRegistry $registry ) {
        $this->registry = $registry;
    }

    /**
     * Generate a database-module compatible schema from fields configuration.
     *
     * @param array $fields Field name => type mapping.
     * @return array Schema array for DatabaseModuleStorage.
     */
    public function generate( array $fields ): array {
        $schema = [
            'id' => [
                'type'           => 'bigint',
                'length'         => 20,
                'auto_increment' => true,
                'primary_key'    => true,
            ],
        ];

        foreach ( $fields as $name => $type ) {
            $schema[ $name ] = $this->registry->get_schema( $type );
        }

        return $schema;
    }

    /**
     * Generate the full storage settings array including schema.
     *
     * @param array $fields Field name => type mapping.
     * @param int $version Schema version for migrations.
     * @return array Full settings array for DatabaseModuleStorage::register().
     */
    public function generate_settings( array $fields, int $version = 1 ): array {
        return [
            'version' => $version,
            'schema'  => $this->generate( $fields ),
        ];
    }
}
