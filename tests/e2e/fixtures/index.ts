/*
 * Copyright (C) Ascensio System SIA 2026
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
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
