import { defineConfig, devices } from '@playwright/test';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
	testDir: 'tests/e2e',
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: [
		['list'],
		['html'],
	],
	use: {
		baseURL: process.env.PLAYWRIGHT_BASEURL ?? 'http://localhost',
		trace: 'on-first-retry',
	},

	projects: [
		{
			name: 'admin',
			testMatch: '**/admin/**/*.spec.ts',
			use: {
				...devices['Desktop Chrome'],
			},
		},
		{
			name: 'common',
			dependencies: ['admin'],
			testMatch: '**/common/**/*.spec.ts',
			use: {
				...devices['Desktop Chrome'],
			},
		},
	],
});
