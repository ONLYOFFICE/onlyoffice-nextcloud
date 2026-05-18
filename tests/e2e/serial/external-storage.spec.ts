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
import { test, expect } from '../fixtures'
import path from 'node:path'
import { login } from '../helpers/auth'
import { User, admin, createRandomUser, deleteUser } from '../helpers/users'
import { saveCommonSettings } from '../helpers/adminSettings'
import { createGlobalStorage, deleteGlobalStorage } from '../helpers/externalStorage'
import { uploadFile, deleteFile } from '../helpers/webdav'
import { createUserShare, deleteShare } from '../helpers/shares'
import { getDirectEditingUrl } from '../helpers/directEditing'
import { DOCUMENT_TEMPLATES_PATH } from '../helpers/templates'
import { randomName } from '../helpers/utils'
import { FilesPage } from '../fixtures/FilesPage'
import { EditorPage } from '../fixtures/EditorPage'

const MOUNT_POINT = 'SMB'
const TEST_FILE = `${randomName()}.docx`
const TEMPLATE_PATH = path.join(DOCUMENT_TEMPLATES_PATH, 'new.docx')

test.describe('External storage restriction', () => {
	let storageId: number
	let ownerUser: User
	let sharedUser: User
	let shareId: number

	test.beforeAll(async () => {
		ownerUser = await createRandomUser()
		sharedUser = await createRandomUser()
		storageId = await createGlobalStorage({
			mountPoint: MOUNT_POINT,
			backend: 'smb',
			authMechanism: 'password::password',
			backendOptions: {
				host: 'smb',
				share: 'share',
				root: '/',
				domain: '',
				user: 'nextcloud',
				password: 'nextcloud',
			},
			mountOptions: {
				enable_sharing: true,
			},
		})
		await uploadFile(`/${MOUNT_POINT}/${TEST_FILE}`, TEMPLATE_PATH, admin)
		const share = await createUserShare(`/${MOUNT_POINT}/${TEST_FILE}`, sharedUser.username, ownerUser)
		shareId = share.id
	})

	test.afterAll(async () => {
		await saveCommonSettings({ restrictExternalStorage: false })
		await deleteShare(shareId, ownerUser)
		await deleteFile(`/${MOUNT_POINT}/${TEST_FILE}`, admin)
		await deleteGlobalStorage(storageId)
		await deleteUser(ownerUser)
		await deleteUser(sharedUser)
	})

	test.describe('Direct access', () => {
		test('Restriction ON: editor does not open for external storage file', async ({ browser, baseURL }) => {
			await saveCommonSettings({ restrictExternalStorage: true })
			const page = await browser.newPage({ storageState: undefined, baseURL })
			await login(page, ownerUser)
			const filesPage = new FilesPage(page)
			const editorPage = new EditorPage(page)
			await filesPage.goto()
			await filesPage.dblClickFile(MOUNT_POINT)
			await filesPage.fileRow(TEST_FILE).waitFor()
			await filesPage.dblClickFile(TEST_FILE)
			await expect(editorPage.editorIFrame()).toBeHidden()
			await page.close()
		})

		test('Restriction OFF: editor opens for external storage file', async ({ browser, baseURL }) => {
			await saveCommonSettings({ restrictExternalStorage: false })
			const page = await browser.newPage({ storageState: undefined, baseURL })
			await login(page, ownerUser)
			const filesPage = new FilesPage(page)
			const editorPage = new EditorPage(page)
			await filesPage.goto()
			await filesPage.dblClickFile(MOUNT_POINT)
			await filesPage.fileRow(TEST_FILE).waitFor()
			await filesPage.dblClickFile(TEST_FILE)
			await editorPage.waitForEditor()
			await page.close()
		})
	})

	test.describe('Direct editing', () => {
		test('Restriction ON: direct editor shows error for external storage file', async ({ browser, baseURL }) => {
			await saveCommonSettings({ restrictExternalStorage: true })
			const url = await getDirectEditingUrl(`/${MOUNT_POINT}/${TEST_FILE}`, ownerUser)
			const page = await browser.newPage({ storageState: undefined, baseURL })
			await page.goto(url)
			await expect(page.locator('#directEditorError')).toBeVisible()
			await page.close()
		})

		test('Restriction OFF: direct editor opens for external storage file', async ({ browser, baseURL }) => {
			await saveCommonSettings({ restrictExternalStorage: false })
			const url = await getDirectEditingUrl(`/${MOUNT_POINT}/${TEST_FILE}`, ownerUser)
			const page = await browser.newPage({ storageState: undefined, baseURL })
			await page.goto(url)
			const editorPage = new EditorPage(page)
			await editorPage.waitForEditor()
			await page.close()
		})
	})

	test.describe('Shared from external storage', () => {
		test('Restriction ON: editor does not open for shared external storage file', async ({ browser, baseURL }) => {
			await saveCommonSettings({ restrictExternalStorage: true })
			const page = await browser.newPage({ storageState: undefined, baseURL })
			await login(page, sharedUser)
			const filesPage = new FilesPage(page)
			await filesPage.goto()
			await filesPage.dblClickFile(TEST_FILE)
			await filesPage.waitForError()
			await page.close()
		})

		test('Restriction OFF: editor opens for shared external storage file', async ({ browser, baseURL }) => {
			await saveCommonSettings({ restrictExternalStorage: false })
			const page = await browser.newPage({ storageState: undefined, baseURL })
			await login(page, sharedUser)
			const filesPage = new FilesPage(page)
			const editorPage = new EditorPage(page)
			await filesPage.goto()
			await filesPage.dblClickFile(TEST_FILE)
			await editorPage.waitForEditor()
			await page.close()
		})
	})
})
