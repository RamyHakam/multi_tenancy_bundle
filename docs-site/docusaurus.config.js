const {themes} = require('prism-react-renderer');
const lightTheme = themes.github;
const darkTheme = themes.dracula;

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'Multi Tenancy Bundle',
  tagline: 'Symfony bundle to extend doctrine to support db switcher and multi tenants',
  favicon: 'img/favicon.ico',

  url: 'https://ramyhakam.github.io',
  baseUrl: '/multi_tenancy_bundle/',

  organizationName: 'RamyHakam',
  projectName: 'multi_tenancy_bundle',

  onBrokenLinks: 'warn',
  onBrokenMarkdownLinks: 'warn',

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          routeBasePath: '/',
          sidebarPath: './sidebars.js',
          editUrl: 'https://github.com/RamyHakam/multi_tenancy_bundle/tree/master/docs-site/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      image: 'img/logo.svg',
      navbar: {
        title: 'Multi Tenancy Bundle',
        logo: {
          alt: 'Multi Tenancy Bundle logo',
          src: 'img/logo.svg',
        },
        items: [
          {
            type: 'docSidebar',
            sidebarId: 'tutorialSidebar',
            position: 'left',
            label: 'Overview',
          },
          {
            href: 'https://github.com/RamyHakam/multi_tenancy_bundle',
            label: 'GitHub',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        copyright: `Copyright \u00a9 ${new Date().getFullYear()} Ramy Hakam. Built with Docusaurus.`,
      },
      prism: {
        theme: lightTheme,
        darkTheme: darkTheme,
        additionalLanguages: ['php', 'yaml', 'bash'],
      },
    }),
};

module.exports = config;
