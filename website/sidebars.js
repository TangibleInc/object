/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  docsSidebar: [
    'intro',
    {
      type: 'category',
      label: 'Getting Started',
      items: [
        'getting-started/installation',
        'getting-started/quick-start',
      ],
    },
    {
      type: 'category',
      label: 'DataView',
      items: [
        'dataview/overview',
        'dataview/configuration',
        'dataview/field-types',
        'dataview/storage',
        'dataview/validation',
        'dataview/lifecycle-hooks',
      ],
    },
    {
      type: 'category',
      label: 'Layouts',
      items: [
        'layouts/overview',
        'layouts/sections',
        'layouts/tabs',
        'layouts/sidebar',
        'layouts/custom-layouts',
      ],
    },
    {
      type: 'category',
      label: 'Renderers',
      items: [
        'renderers/overview',
        'renderers/html-renderer',
        'renderers/tangible-fields',
        'renderers/custom-renderers',
      ],
    },
    {
      type: 'category',
      label: 'Advanced',
      items: [
        'advanced/architecture',
        'advanced/custom-field-types',
        'advanced/repeaters',
        'advanced/i18n',
      ],
    },
    {
      type: 'category',
      label: 'Examples',
      items: [
        'examples/settings-page',
        'examples/crud-admin',
        'examples/invoice-manager',
      ],
    },
    'api-reference',
  ],
};

export default sidebars;
