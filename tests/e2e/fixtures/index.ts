import { test as base, Page } from '@playwright/test'
import { User, createRandomUser, deleteUser, admin } from '../helpers/users'
import { login } from '../helpers/auth'
import { AdminPage } from './AdminPage'
import { FilesPage } from './FilesPage'
import { EditorPage } from './EditorPage'

type Fixtures = {
	user: User
	userPage: Page
	guestPage: Page
	adminPage: AdminPage
	filesPage: FilesPage
	editorPage: EditorPage
}

export const test = base.extend<Fixtures>({
	user: async ({}, use) => {
		const randomUser = await createRandomUser()
		await use(randomUser)
		await deleteUser(randomUser)
	},

	userPage: async ({ browser, baseURL, user }, use) => {
		const page = await browser.newPage({ storageState: undefined, baseURL })
		await login(page, user)
		await use(page)
		await page.close()
	},

	guestPage: async({ browser, baseURL }, use) => {
		const page = await browser.newPage({ storageState: undefined, baseURL })
		await use(page)
		await page.close()
	},

	adminPage: async ({ browser, baseURL }, use) => {
		const page = await browser.newPage({ storageState: undefined, baseURL })
		await login(page, admin)
		await use(new AdminPage(page))
	},

	filesPage: async ({ userPage }, use) => {
		await use(new FilesPage(userPage))
	},

	editorPage: async ({ userPage }, use) => {
		await use(new EditorPage(userPage))
	},
})

export { expect } from '@playwright/test'
