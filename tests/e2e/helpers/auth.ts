import { Page } from '@playwright/test';
import { User } from './users';

export async function login(page: Page, user: User): Promise<void> {
	await page.goto('./index.php/login');
	await page.locator('#user').fill(user.username);
	await page.locator('#password').fill(user.password);
	await page.locator('#password').press('Enter');
	await page.waitForURL('**/apps/**', { waitUntil: 'domcontentloaded' });
}
