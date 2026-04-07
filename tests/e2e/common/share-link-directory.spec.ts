import { test, expect } from '../fixtures'
import path from 'node:path'
import { uploadFile, deleteFile, createDirectory } from '../helpers/webdav'
import { createPublicEditShare, deleteShare, ShareInfo } from '../helpers/shares'
import { FilesPage } from '../fixtures/FilesPage'
import { DOCUMENT_TEMPLATES_PATH } from '../helpers/templates'
import { EditorPage } from '../fixtures/EditorPage'

const TEMPLATES_DIR = path.join(__dirname, '../templates')

test.describe('Public edit directory share', () => {
	const DIR_NAME = 'share-dir'
	let share: ShareInfo

	test.beforeEach(async ({ user }) => {
		await createDirectory(`/${DIR_NAME}`, user)
		share = await createPublicEditShare(`/${DIR_NAME}`, user)
	})

	test.afterEach(async ({ user }) => {
		await deleteShare(share.id, user)
		await deleteFile(`/${DIR_NAME}`, user)
	})

	for (const fileType of ['document', 'presentation', 'spreadsheet']) {
		test(`New ${fileType} file is present and creates file on edit directory share`, async ({ guestPage }) => {
			await guestPage.goto(`index.php/s/${share.token}`)
			const filesPage = new FilesPage(guestPage)
			await filesPage.openNewMenu()
			await filesPage.menuItem(`New ${fileType}`).click()

			const editorPage = new EditorPage(guestPage)
			await editorPage.waitForEditor()
		})
	}

	test.describe('Convert with ONLYOFFICE', () => {
		const SOURCE_FILE = 'sample.odt'
		const CONVERTED_FILE = 'sample.docx'

		test.beforeEach(async ({ user }) => {
			await uploadFile(`/${DIR_NAME}/${SOURCE_FILE}`, path.join(TEMPLATES_DIR, SOURCE_FILE), user)
		})

		test('Converts odt to docx', async ({ guestPage }) => {
			await guestPage.goto(`index.php/s/${share.token}`)
			const filesPage = new FilesPage(guestPage)
			await filesPage.rightClickFile(SOURCE_FILE)
			await filesPage.menuItem('Convert with ONLYOFFICE').click()
			await filesPage.waitForSuccess()
			await expect(filesPage.fileRow(CONVERTED_FILE)).toBeVisible()
		})
	})

	test.describe('New PDF form', () => {
		test.describe('Create PDF form from docx', () => {
			const SOURCE_FILE = 'source.docx'

			test.beforeEach(async ({ user }) => {
				await uploadFile(`/${DIR_NAME}/${SOURCE_FILE}`, path.join(DOCUMENT_TEMPLATES_PATH, 'new.docx'), user)
			})

			test('Creates PDF form from docx file', async ({ guestPage }) => {
				await guestPage.goto(`index.php/s/${share.token}`)
				const filesPage = new FilesPage(guestPage)
				await filesPage.openNewMenu()
				await filesPage.menuItem('New PDF form').click()

				const dialog = filesPage.page.getByRole('dialog')
				await dialog.locator(`tr[data-filename="${SOURCE_FILE}"]`).click()
				await dialog.getByRole('button', { name: 'From text document' }).click()

				const editorPage = new EditorPage(guestPage)
				await editorPage.waitForEditor()
			})
		})

		test('Creates PDF form from blank template', async ({ guestPage }) => {
			await guestPage.goto(`index.php/s/${share.token}`)
			const filesPage = new FilesPage(guestPage)
			await filesPage.openNewMenu()
			await filesPage.menuItem('New PDF form').click()

			const blankButton = filesPage.page.getByRole('dialog').getByRole('button', { name: 'Blank' })
			await expect(blankButton).toBeVisible()
			await blankButton.click()

			const editorPage = new EditorPage(guestPage)
			await editorPage.waitForEditor()
		})
	})
})
