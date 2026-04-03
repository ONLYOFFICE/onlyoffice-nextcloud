import { test, expect } from '../fixtures'
import { login } from '../helpers/auth'
import { User, createRandomUser, deleteUser } from '../helpers/users'
import { randomName } from '../helpers/utils'
import { createGroup, deleteGroup, addUserToGroup } from '../helpers/groups'
import { saveCommonSettings } from '../helpers/adminSettings'
import { uploadNewTemplate } from '../helpers/templates'
import { deleteFile } from '../helpers/webdav'
import { FilesPage } from '../fixtures/FilesPage'
import { EditorPage } from '../fixtures/EditorPage'

test.describe('Group access restriction', () => {
	let allowedUser: User
	let restrictedUser: User
	const group = `group-${randomName()}`
	const testFileName = randomName()
	const testFile = `${testFileName}.docx`

	test.beforeAll(async () => {
		allowedUser = await createRandomUser()
		restrictedUser = await createRandomUser()
		await createGroup(group)
		await addUserToGroup(allowedUser.username, group)
		await uploadNewTemplate(testFileName, 'docx', allowedUser)
		await uploadNewTemplate(testFileName, 'docx', restrictedUser)
		await saveCommonSettings({ limitGroups: [group] })
	})

	test.afterAll(async () => {
		await saveCommonSettings({ limitGroups: [] })
		await deleteFile(`/${testFile}`, allowedUser)
		await deleteFile(`/${testFile}`, restrictedUser)
		await deleteGroup(group)
		await deleteUser(allowedUser)
		await deleteUser(restrictedUser)
	})

	test('In-group user opens editor by double-clicking file', async ({ browser, baseURL }) => {
		const page = await browser.newPage({ storageState: undefined, baseURL })
		await login(page, allowedUser)
		const filesPage = new FilesPage(page)
		const editorPage = new EditorPage(page)
		await filesPage.goto()
		await filesPage.dblClickFile(testFile)
		await editorPage.waitForEditor()
		await page.close()
	})

	test('Out-of-group user cannot open editor by double-clicking file', async ({ browser, baseURL }) => {
		const page = await browser.newPage({ storageState: undefined, baseURL })
		await login(page, restrictedUser)
		const filesPage = new FilesPage(page)
		const editorPage = new EditorPage(page)
		await filesPage.goto()
		await filesPage.dblClickFile(testFile)
		await expect(editorPage.editorIFrame()).toBeHidden()
		await page.close()
	})
})
