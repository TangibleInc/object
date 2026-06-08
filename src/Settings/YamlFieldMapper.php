<?php declare(strict_types=1);

namespace Tangible\Settings;

/**
 * Maps YAML field definitions to DataView and Tangible Fields formats.
 */
class YamlFieldMapper {

	/**
	 * Map YAML type to DataView field type.
	 */
	protected static array $typeMap = [
		'string'      => 'string',
		'text'        => 'string',
		'integer'     => 'integer',
		'number'      => 'integer',
		'boolean'     => 'boolean',
		'email'       => 'string',
		'url'         => 'string',
		'select'      => 'string',
		'radio'       => 'string',
		'multiselect' => 'string',
		'dimension'   => 'string',
		'date'        => 'string',
	];

	/**
	 * Map YAML type to Tangible Fields type.
	 */
	protected static array $tfTypeMap = [
		'string'      => 'text',
		'text'        => 'textarea',
		'integer'     => 'number',
		'number'      => 'number',
		'boolean'     => 'switch',
		'email'       => 'text',
		'url'         => 'text',
		'select'      => 'select',
		'radio'       => 'radio',
		'multiselect' => 'checkboxMultiselect',
		'dimension'   => 'simple_dimension',
		'date'        => 'date_picker',
	];

	/**
	 * Convert YAML field definition to DataView field config.
	 */
	public static function toDataViewField(array $fieldDef): array {
		$type = $fieldDef['type'] ?? 'string';

		return [
			'type'        => self::$typeMap[$type] ?? 'string',
			'label'       => $fieldDef['label'] ?? '',
			'description' => $fieldDef['description'] ?? '',
			'placeholder' => $fieldDef['placeholder'] ?? '',
			'default'     => $fieldDef['default'] ?? null,
			'required'    => $fieldDef['required'] ?? false,
			// Pass through all original config for renderer
			'_yaml'       => $fieldDef,
		];
	}

	/**
	 * Convert YAML field definition to Tangible Fields render config.
	 */
	public static function toTangibleFieldsConfig(string $name, array $fieldDef, string $settingsKey = ''): array {
		$type = $fieldDef['type'] ?? 'string';
		$tfType = self::$tfTypeMap[$type] ?? 'text';

		$config = [
			'type'        => $tfType,
			'name'        => $settingsKey ? "{$settingsKey}[{$name}]" : $name,
			'label'       => '', // Label rendered separately in table layout
			'placeholder' => $fieldDef['placeholder'] ?? '',
		];

		// Switch-specific: description shows inline
		if ($tfType === 'switch') {
			$config['description'] = $fieldDef['description'] ?? '';
			$config['value_on'] = true;
			$config['value_off'] = false;
		}

		// Number-specific
		if ($tfType === 'number') {
			if (isset($fieldDef['min'])) $config['min'] = $fieldDef['min'];
			if (isset($fieldDef['max'])) $config['max'] = $fieldDef['max'];
		}

		// Choices for select/radio/multiselect
		if (!empty($fieldDef['choices'])) {
			$config['choices'] = $fieldDef['choices'];
		}

		// Dimension units
		if ($tfType === 'simple_dimension' && !empty($fieldDef['units'])) {
			$config['units'] = $fieldDef['units'];
		}

		// Dynamic tags
		if (!empty($fieldDef['dynamic'])) {
			$config['dynamic'] = [
				'mode'       => 'insert',
				'categories' => $fieldDef['dynamic_categories'] ?? [],
			];
		}

		// Conditions
		if (!empty($fieldDef['condition'])) {
			$config['condition'] = self::mapCondition($fieldDef['condition'], $settingsKey);
		}

		return $config;
	}

	/**
	 * Map simplified YAML condition to TFields format.
	 *
	 * YAML format:
	 * ```yaml
	 * condition:
	 *   field: other_field
	 *   equals: value
	 * ```
	 *
	 * TFields format:
	 * ```php
	 * 'condition' => [
	 *   'action' => 'show',
	 *   'condition' => ['settings_key[other_field]' => ['_eq' => 'value']]
	 * ]
	 * ```
	 */
	public static function mapCondition(array $condition, string $settingsKey = ''): array {
		$field = $condition['field'] ?? '';
		$fieldName = $settingsKey ? "{$settingsKey}[{$field}]" : $field;

		$tfCondition = [];

		if (isset($condition['equals'])) {
			$tfCondition[$fieldName] = ['_eq' => $condition['equals']];
		} elseif (isset($condition['not_equals'])) {
			$tfCondition[$fieldName] = ['_neq' => $condition['not_equals']];
		} elseif (isset($condition['in'])) {
			$tfCondition[$fieldName] = ['_in' => $condition['in']];
		} elseif (isset($condition['not_in'])) {
			$tfCondition[$fieldName] = ['_nin' => $condition['not_in']];
		}

		return [
			'action'    => 'show',
			'condition' => $tfCondition,
		];
	}

	/**
	 * Get the Tangible Fields type for a YAML type.
	 */
	public static function getTfType(string $yamlType): string {
		return self::$tfTypeMap[$yamlType] ?? 'text';
	}

	/**
	 * Get the DataView type for a YAML type.
	 */
	public static function getDataViewType(string $yamlType): string {
		return self::$typeMap[$yamlType] ?? 'string';
	}
}
