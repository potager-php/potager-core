import { defineConfig } from 'vitepress';

// https://vitepress.dev/reference/site-config
export default defineConfig({
	title: 'Potager Framework',
	description: 'A small MVC PHP Framework',
	themeConfig: {
		// https://vitepress.dev/reference/default-theme-config
		nav: [
			{ text: 'Home', link: '/' },
			{ text: 'Guide', link: '/guide/getting-started' },
			{ text: 'Console', link: '/console/commands' },
		],

		sidebar: [
			{
				text: 'Guide',
				items: [{ text: 'Getting Started', link: '/guide/getting-started' }],
			},
			{
				text: 'Basics',
				items: [
					{ text: 'Configuration', link: '/basics/configuration' },
					{ text: 'Routing', link: '/basics/routing' },
					{ text: 'Models', link: '/basics/models' },
					{ text: 'CRUD Operations', link: '/basics/crud-operations' },
					{ text: 'Query Builder', link: '/basics/query-builder' },
				],
			},
			{
				text: 'Console (Farmer CLI)',
				items: [
					{ text: 'Console Commands', link: '/console/commands' },
					{ text: 'Code Generators', link: '/console/generators' },
				],
			},
		],

		socialLinks: [{ icon: 'github', link: 'https://github.com/vuejs/vitepress' }],
	},
});
