/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  tutorialSidebar: [
    {
      type: 'category',
      label: 'Introduction',
      items: ['intro/overview', 'intro/why-multi-tenancy', 'intro/architecture'],
    },
    'getting-started/getting-started',
    'concepts/core-concepts',
    'usage/usage',
    'events/lifecycle-events',
    'cli/cli-commands',
    'resolver/automatic-resolution',
    'cache/cache-isolation',
    'customization/customization',
    'suggestions/suggestions',
    'examples/examples',
    'contributing/contribution',
    'changelog',
    'license',
  ],
};

module.exports = sidebars;
