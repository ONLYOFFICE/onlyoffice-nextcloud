import { test, expect } from '../fixtures'
import { uploadNewTemplate } from '../helpers/templates'
import { deleteFile } from '../helpers/webdav'

const SOURCE_FILE = 'source.docx'

test('New PDF form button is present on the files page', async ({ filesPage }) => {
	await filesPage.goto()
	await filesPage.openNewMenu()

	await expect(filesPage.menuItem('New PDF form')).toBeVisible()
})

test.describe('Create PDF form from docx', () => {
	test.beforeEach(async ({ filesPage, user }) => {
		await uploadNewTemplate('source', 'docx', user)
		await filesPage.goto()
	})

	test.afterEach(async ({ user }) => {
		await deleteFile(`/${SOURCE_FILE}`, user)
		await deleteFile('/New PDF form.pdf', user)
	})

	test('Creates PDF form from docx file', async ({ filesPage, editorPage }) => {
		await filesPage.openNewMenu()
		await filesPage.menuItem('New PDF form').click()

		const dialog = filesPage.page.getByRole('dialog')
		await dialog.locator(`tr[data-filename="${SOURCE_FILE}"]`).click()
		await dialog.getByRole('button', { name: 'From text document' }).click()

		await expect(editorPage.editorIFrame()).toBeVisible()
		await expect(editorPage.documentContent()).toBeVisible()
	})
})

test('Create new PDF form from blank template', async ({ filesPage, editorPage }) => {
	await filesPage.goto()
	await filesPage.openNewMenu()

	await filesPage.menuItem('New PDF form').click()

	const blankButton = filesPage.page.getByRole('dialog').getByRole('button', { name: 'Blank' })
	await expect(blankButton).toBeVisible()
	await blankButton.click()

	await expect(editorPage.editorIFrame()).toBeVisible()
	await expect(editorPage.documentContent()).toBeVisible()
})
