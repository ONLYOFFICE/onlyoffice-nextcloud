import { test } from '../fixtures'
import path from 'node:path'
import { uploadFile, deleteFile } from '../helpers/webdav'
import { createPublicShare, deleteShare, ShareInfo } from '../helpers/shares'
import { uploadNewTemplate } from '../helpers/templates'
import { EditorPage } from '../fixtures/EditorPage'
import { FilesPage } from '../fixtures/FilesPage'

const TEMPLATES_DIR = path.join(__dirname, '../templates')

test.describe('File share - default open enabled (docx)', () => {
	const FILE_NAME = 'share.docx'
	let share: ShareInfo

	test.beforeEach(async ({ user }) => {
		await uploadNewTemplate('share', 'docx', user)
		share = await createPublicShare(`/${FILE_NAME}`, user)
	})

	test.afterEach(async ({ user }) => {
		await deleteShare(share.id, user)
		await deleteFile(`/${FILE_NAME}`, user)
	})

	test('Editor auto-opens on load and reopens via double-click for user', async ({ userPage, filesPage, editorPage }) => {
		await userPage.goto(`index.php/s/${share.token}`)
		await editorPage.waitForEditor()
		await editorPage.closeButton().click()
		await filesPage.dblClickFile(share.token)
		await editorPage.waitForEditor()
	})

	test('Editor auto-opens on load and reopens via double-click for guest', async ({ guestPage }) => {
		await guestPage.goto(`index.php/s/${share.token}`)
		const editorPage = new EditorPage(guestPage)
		const filesPage = new FilesPage(guestPage)
		await editorPage.waitForEditor()
		await editorPage.closeButton().click()
		await filesPage.dblClickFile(share.token)
		await editorPage.waitForEditor()
	})
})

test.describe('File share - default open disabled (txt)', () => {
	const FILE_NAME = 'sample.txt'
	let share: ShareInfo

	test.beforeEach(async ({ user }) => {
		await uploadFile(`/${FILE_NAME}`, path.join(TEMPLATES_DIR, FILE_NAME), user)
		share = await createPublicShare(`/${FILE_NAME}`, user)
	})

	test.afterEach(async ({ user }) => {
		await deleteShare(share.id, user)
		await deleteFile(`/${FILE_NAME}`, user)
	})

	test('Opens editor via action for user', async ({ filesPage, editorPage }) => {
		await filesPage.page.goto(`index.php/s/${share.token}`)
		await filesPage.rightClickFile(share.token)
		await filesPage.menuItem('Open in ONLYOFFICE').click()
		await editorPage.waitForEditor()
	})

	test('Opens editor via action for guest', async ({ guestPage }) => {
		const filesPage = new FilesPage(guestPage)
		await filesPage.page.goto(`index.php/s/${share.token}`)
		await filesPage.rightClickFile(share.token)
		await filesPage.menuItem('Open in ONLYOFFICE').click()

		const editorPage = new EditorPage(guestPage)
		await editorPage.waitForEditor()
	})
})
