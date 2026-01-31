<?php
/**
 * Global functions for YAML settings registration.
 */

use Tangible\Settings\YamlSettingsLoader;
use Tangible\DataView\DataView;

if (!function_exists('tangible_object_register_settings')) {
	/**
	 * Register a settings page from a YAML configuration file.
	 *
	 * @param object $plugin The plugin instance from Tangible Framework.
	 * @param string $yaml_path Absolute path to the settings YAML file.
	 * @return DataView|null The created DataView, or null on failure.
	 *
	 * @example
	 * ```php
	 * tangible_object_register_settings($plugin, __DIR__ . '/config/settings.yaml');
	 * ```
	 */
	function tangible_object_register_settings(object $plugin, string $yaml_path): ?DataView {
		return YamlSettingsLoader::register($plugin, $yaml_path);
	}
}
