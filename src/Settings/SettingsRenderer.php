<?php declare(strict_types=1);

namespace Tangible\Settings;

use Tangible\Renderer\Renderer;
use Tangible\DataObject\DataSet;
use Tangible\EditorLayout\Layout;

/**
 * Renders settings pages matching Tangible Framework's structure.
 */
class SettingsRenderer implements Renderer {

	protected Layout $layout;
	protected array $data;
	protected array $yamlConfig;
	protected bool $enqueued = false;

	public function __construct(array $yamlConfig = []) {
		$this->yamlConfig = $yamlConfig;
	}

	/**
	 * Render the editor/form.
	 */
	public function render_editor(Layout $layout, array $data = []): string {
		$this->ensure_tangible_fields_loaded();

		$this->layout = $layout;
		$this->data = $data;

		$structure = $layout->get_structure();

		// Plugin name for CSS classes
		$name = $this->yamlConfig['name'] ?? str_replace('_', '-', rtrim($this->yamlConfig['prefix'] ?? '', '_'));
		$title = $this->yamlConfig['title'] ?? $this->yamlConfig['menu_label'] ?? 'Settings';

		// Match Framework's page structure
		$html = '<div class="wrap tangible-plugin-settings-page ' . esc_attr($name) . '-settings">';

		// Header
		$html .= '<header>';
		$html .= '<div class="plugin-title">';
		$html .= '<h1>' . esc_html($title);
		$html .= '<div class="tangible-plugin-store-link">';
		$html .= 'By <a href="https://tangibleplugins.com" target="_blank">Tangible Plugins</a>';
		$html .= '</div>';
		$html .= '</h1>';
		$html .= '</div>';
		$html .= '</header>';

		// Render layout items (tabs or sections)
		foreach ($structure['items'] as $item) {
			$html .= $this->render_item($item, $name);
		}

		$html .= '</div>'; // .wrap

		$this->schedule_enqueue();

		return $html;
	}

	/**
	 * Render a layout item (tabs or section).
	 */
	protected function render_item(array $item, string $name = ''): string {
		return match ($item['type']) {
			'section' => $this->render_section($item),
			'tabs'    => $this->render_tabs($item, $name),
			default   => '',
		};
	}

	/**
	 * Render a section with form-table.
	 */
	protected function render_section(array $section): string {
		$html = '<div class="tangible-plugin-settings-section">';
		$html .= '<h3>' . esc_html($section['label']) . '</h3>';
		$html .= '<table class="form-table" role="presentation"><tbody>';

		foreach ($section['fields'] as $field) {
			$html .= $this->render_field_row($field);
		}

		$html .= '</tbody></table>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render tabs matching Framework's nav-tab structure.
	 */
	protected function render_tabs(array $tabsStructure, string $name = ''): string {
		$currentTab = $_GET['tab'] ?? null;
		$tabs = $tabsStructure['tabs'];

		if (empty($tabs)) {
			return '';
		}

		// Default to first tab
		if ($currentTab === null) {
			$currentTab = sanitize_key($tabs[0]['label']);
		}

		// Tab navigation - Framework uses h2
		$html = '<h2 class="nav-tab-wrapper">';
		foreach ($tabs as $tab) {
			$tabKey = sanitize_key($tab['label']);
			$active = ($currentTab === $tabKey) ? ' nav-tab-active' : '';
			$url = add_query_arg('tab', $tabKey);
			$html .= '<a href="' . esc_url($url) . '" class="nav-tab' . $active . '">' . esc_html($tab['label']) . '</a>';
		}
		$html .= '</h2>';

		// Content wrapper
		$html .= '<div class="tangible-plugin-settings-section-wrapper">';

		// Render active tab content
		foreach ($tabs as $tab) {
			$tabKey = sanitize_key($tab['label']);
			if ($currentTab !== $tabKey) {
				continue;
			}

			// Form wrapper with Framework classes
			$html .= '<form method="post" class="tangible-plugin-settings-tab ' . esc_attr($name) . '-settings-tab ' . esc_attr($name) . '-settings-tab-' . esc_attr($tabKey) . '">';

			// Title section if defined in YAML
			$titleSection = $this->getTabTitleSection($tab['label']);
			if ($titleSection) {
				$html .= '<div class="tangible-plugin-settings-title-section">';
				$html .= '<h2>' . esc_html($titleSection['title'] ?? $tab['label']) . '</h2>';
				if (!empty($titleSection['description'])) {
					$html .= '<p>' . esc_html($titleSection['description']) . '</p>';
				}
				$html .= '</div>';
			}

			// Static content (e.g., documentation tab)
			$tabContent = $this->getTabContent($tab['label']);
			if (!empty($tabContent)) {
				$html .= '<div class="tangible-plugin-settings-title-section">';
				$html .= wp_kses_post($tabContent);
				$html .= '</div>';
			}

			// Fields directly in tab
			if (!empty($tab['fields'])) {
				$html .= '<div class="tangible-plugin-box">';
				$html .= '<div class="tangible-plugin-panel-content">';
				$html .= '<table class="form-table" role="presentation"><tbody>';
				foreach ($tab['fields'] as $field) {
					$html .= $this->render_field_row($field);
				}
				$html .= '</tbody></table>';
				$html .= '</div>';
				$html .= '</div>';
			}

			// Sections within tab
			if (!empty($tab['items'])) {
				foreach ($tab['items'] as $item) {
					if ($item['type'] === 'section') {
						$html .= '<div class="tangible-plugin-box">';
						$html .= '<div class="tangible-plugin-panel-content">';
						$html .= '<h3>' . esc_html($item['label']) . '</h3>';
						$html .= '<table class="form-table" role="presentation"><tbody>';
						foreach ($item['fields'] as $field) {
							$html .= $this->render_field_row($field);
						}
						$html .= '</tbody></table>';
						$html .= '</div>';
						$html .= '</div>';
					}
				}
			}

			// Submit button
			$html .= '<div class="tangible-plugin-panel-actions">';
			$html .= get_submit_button('Save Settings', 'primary tpf-button', 'submit', false);
			$html .= '</div>';

			$html .= '</form>';
		}

		$html .= '</div>'; // .tangible-plugin-settings-section-wrapper

		return $html;
	}

	/**
	 * Render a single field row in form-table layout.
	 */
	protected function render_field_row(array $field): string {
		$fields = tangible_fields();
		$slug = $field['slug'];

		$yamlDef = $this->getYamlFieldDef($slug);
		$label = $yamlDef['label'] ?? $field['label'] ?? ucfirst(str_replace('_', ' ', $slug));
		$description = $yamlDef['description'] ?? '';
		$type = $yamlDef['type'] ?? 'string';
		$tfType = YamlFieldMapper::getTfType($type);

		$value = $this->data[$slug] ?? $yamlDef['default'] ?? '';

		$showDescInLabel = ($tfType !== 'switch' && !empty($description));

		$html = '<tr>';
		$html .= '<th scope="row">';
		$html .= '<label for="' . esc_attr($slug) . '">' . esc_html($label) . '</label>';
		if ($showDescInLabel) {
			$html .= '<p class="description">' . esc_html($description) . '</p>';
		}
		$html .= '</th>';
		$html .= '<td>';

		$settingsKey = $this->yamlConfig['slug'] ?? '';
		$fieldConfig = YamlFieldMapper::toTangibleFieldsConfig($slug, $yamlDef, $settingsKey);
		$fieldConfig['value'] = $this->formatValue($value, $type);

		$fields->register_field($slug, $fieldConfig);
		$html .= $fields->render_field($slug);

		$html .= '</td>';
		$html .= '</tr>';

		return $html;
	}

	/**
	 * Get tab content from YAML config by label.
	 */
	protected function getTabContent(string $label): string {
		foreach ($this->yamlConfig['tabs'] ?? [] as $tabConfig) {
			if (($tabConfig['label'] ?? '') === $label && isset($tabConfig['content'])) {
				return $tabConfig['content'];
			}
		}
		return '';
	}

	/**
	 * Get tab title_section from YAML config by label.
	 */
	protected function getTabTitleSection(string $label): ?array {
		foreach ($this->yamlConfig['tabs'] ?? [] as $tabConfig) {
			if (($tabConfig['label'] ?? '') === $label && isset($tabConfig['title_section'])) {
				return $tabConfig['title_section'];
			}
		}
		return null;
	}

	/**
	 * Get YAML field definition by slug.
	 */
	protected function getYamlFieldDef(string $slug): array {
		$prefix = $this->yamlConfig['prefix'] ?? '';
		$shortName = str_starts_with($slug, $prefix) ? substr($slug, strlen($prefix)) : $slug;

		foreach ($this->yamlConfig['tabs'] ?? [] as $tab) {
			if (isset($tab['fields'][$shortName])) {
				return $tab['fields'][$shortName];
			}
			foreach ($tab['sections'] ?? [] as $section) {
				if (isset($section['fields'][$shortName])) {
					return $section['fields'][$shortName];
				}
			}
		}

		return [];
	}

	/**
	 * Format value for Tangible Fields.
	 */
	protected function formatValue(mixed $value, string $type): mixed {
		if ($value === null) {
			return '';
		}

		return match ($type) {
			'boolean' => (bool) $value,
			'integer', 'number' => (int) $value,
			default => $value,
		};
	}

	/**
	 * Render list view (not used for settings).
	 */
	public function render_list(DataSet $dataset, array $entities): string {
		return '<p>List view not available for settings.</p>';
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_assets(): void {
		if ($this->enqueued) {
			return;
		}

		$this->ensure_tangible_fields_loaded();
		tangible_fields()->enqueue();

		// Enqueue Framework settings CSS if available
		$framework = function_exists('tangible') ? tangible() : null;
		if ($framework && !empty($framework->url)) {
			wp_enqueue_style(
				'tangible-plugin-settings-page',
				$framework->url . '/assets/settings.css',
				[],
				$framework->version ?? '1.0.0'
			);
		}

		$this->enqueued = true;
	}

	/**
	 * Schedule asset enqueue on footer.
	 */
	protected function schedule_enqueue(): void {
		if ($this->enqueued) {
			return;
		}
		add_action('admin_footer', [$this, 'enqueue_assets'], 5);
	}

	/**
	 * Ensure Tangible Fields is available.
	 */
	protected function ensure_tangible_fields_loaded(): void {
		if (!function_exists('tangible_fields')) {
			throw new \RuntimeException('SettingsRenderer requires Tangible Fields.');
		}
	}
}
