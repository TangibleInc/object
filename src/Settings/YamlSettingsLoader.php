<?php declare(strict_types=1);

namespace Tangible\Settings;

use Symfony\Component\Yaml\Yaml;
use Tangible\DataView\DataView;
use Tangible\EditorLayout\Layout;
use Tangible\EditorLayout\Tabs;
use Tangible\EditorLayout\Tab;
use Tangible\EditorLayout\Section;

/**
 * Loads settings pages from YAML configuration files.
 *
 * Minimal YAML:
 * ```yaml
 * tabs:
 *   general:
 *     label: General
 *     fields:
 *       enable_feature:
 *         type: boolean
 *         label: Enable Feature
 * ```
 */
class YamlSettingsLoader {

	protected object $plugin;
	protected array $config;
	protected ?DataView $view = null;

	public function __construct(object $plugin, array $config) {
		$this->plugin = $plugin;
		$this->config = $this->applyDefaults($config);
	}

	/**
	 * Register settings from a YAML file.
	 */
	public static function register(object $plugin, string $yaml_path): ?DataView {
		if (!file_exists($yaml_path)) {
			trigger_error("Settings YAML not found: {$yaml_path}", E_USER_WARNING);
			return null;
		}

		$config = Yaml::parseFile($yaml_path);
		$loader = new self($plugin, $config);

		return $loader->createDataView();
	}

	/**
	 * Apply default values inferred from $plugin.
	 */
	protected function applyDefaults(array $config): array {
		$pluginPrefix = $this->plugin->setting_prefix ?? str_replace('-', '_', $this->plugin->name);

		$defaults = [
			'menu_label' => 'Settings',
			'parent'     => $this->plugin->name,
			'prefix'     => $pluginPrefix . '_',
			'capability' => 'manage_options',
		];

		$merged = array_merge($defaults, $config);

		// Slug depends on prefix, so calculate after merge
		if (!isset($merged['slug'])) {
			$prefix = rtrim($merged['prefix'], '_');
			$merged['slug'] = $prefix . '_settings';
		}

		return $merged;
	}

	/**
	 * Create and register the DataView.
	 */
	public function createDataView(): DataView {
		$fields = $this->extractAllFields();

		$this->view = new DataView([
			'slug'       => $this->config['slug'],
			'label'      => 'Settings',
			'mode'       => 'singular',
			'storage'    => 'option',
			'capability' => $this->config['capability'],
			'fields'     => $fields,
			'ui'         => [
				'menu_label' => $this->config['menu_label'],
				'parent'     => $this->config['parent'],
			],
		]);

		$this->view->set_layout(fn(Layout $layout) => $this->buildLayout($layout));
		$this->view->set_renderer(new SettingsRenderer($this->config));

		// Register dynamic values if defined
		if (!empty($this->config['dynamic_values'])) {
			$this->registerDynamicValues();
		}

		add_action('admin_menu', fn() => $this->view->register(), 60);

		return $this->view;
	}

	/**
	 * Extract all fields from tabs/sections into flat array for DataView.
	 */
	protected function extractAllFields(): array {
		$fields = [];
		$prefix = $this->config['prefix'];

		foreach ($this->config['tabs'] ?? [] as $tabKey => $tab) {
			// Fields directly in tab
			foreach ($tab['fields'] ?? [] as $fieldKey => $fieldDef) {
				$fullKey = $prefix . $fieldKey;
				$fields[$fullKey] = YamlFieldMapper::toDataViewField($fieldDef);
			}

			// Fields in sections
			foreach ($tab['sections'] ?? [] as $sectionKey => $section) {
				foreach ($section['fields'] ?? [] as $fieldKey => $fieldDef) {
					$fullKey = $prefix . $fieldKey;
					$fields[$fullKey] = YamlFieldMapper::toDataViewField($fieldDef);
				}
			}
		}

		return $fields;
	}

	/**
	 * Build the layout from YAML config.
	 */
	protected function buildLayout(Layout $layout): void {
		$tabs = $this->config['tabs'] ?? [];

		if (empty($tabs)) {
			return;
		}

		$layout->tabs(function(Tabs $tabsBuilder) use ($tabs) {
			foreach ($tabs as $tabKey => $tabConfig) {
				$tabsBuilder->tab($tabConfig['label'] ?? ucfirst($tabKey), function(Tab $tab) use ($tabConfig) {
					$this->buildTabContent($tab, $tabConfig);
				});
			}
		});
	}

	/**
	 * Build content for a single tab.
	 */
	protected function buildTabContent(Tab $tab, array $tabConfig): void {
		$prefix = $this->config['prefix'];

		// Add sections if present
		if (!empty($tabConfig['sections'])) {
			foreach ($tabConfig['sections'] as $sectionKey => $sectionConfig) {
				$tab->section($sectionConfig['label'] ?? ucfirst($sectionKey), function(Section $section) use ($sectionConfig, $prefix) {
					foreach ($sectionConfig['fields'] ?? [] as $fieldKey => $fieldDef) {
						$section->field($prefix . $fieldKey);
					}
				});
			}
		}

		// Add fields directly in tab (no section)
		foreach ($tabConfig['fields'] ?? [] as $fieldKey => $fieldDef) {
			$tab->field($prefix . $fieldKey);
		}
	}

	/**
	 * Register dynamic values for template tags.
	 */
	protected function registerDynamicValues(): void {
		if (!function_exists('tangible_fields')) {
			return;
		}

		$fields = tangible_fields();
		$dvConfig = $this->config['dynamic_values'];

		// Register category
		if (!empty($dvConfig['category'])) {
			$fields->register_dynamic_value_category($dvConfig['category'], [
				'label' => $dvConfig['label'] ?? ucfirst($dvConfig['category']),
			]);
		}

		// Register values
		foreach ($dvConfig['values'] ?? [] as $name => $valueDef) {
			$callback = $valueDef['callback'] ?? null;

			if (is_string($callback) && is_callable($callback)) {
				$callbackFn = fn($settings, $config) => call_user_func($callback);
			} elseif (is_string($callback) && strpos($callback, '::') !== false) {
				$callbackFn = fn($settings, $config) => call_user_func($callback);
			} else {
				$callbackFn = fn() => '';
			}

			$fields->register_dynamic_value([
				'category'            => $dvConfig['category'] ?? 'general',
				'name'                => $name,
				'label'               => $valueDef['label'] ?? ucfirst($name),
				'type'                => 'text',
				'callback'            => $callbackFn,
				'permission_callback' => '__return_true',
			]);
		}
	}

	/**
	 * Get the created DataView.
	 */
	public function getView(): ?DataView {
		return $this->view;
	}

	/**
	 * Get the parsed config.
	 */
	public function getConfig(): array {
		return $this->config;
	}
}
