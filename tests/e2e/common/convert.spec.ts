import { test, expect } from '../fixtures'
import path from 'node:path'
import { uploadFile, deleteFile } from '../helpers/webdav'

const TEMPLATES_DIR = path.join(__dirname, '../templates')
const SOURCE_FILE = 'sample.odt'
const CONVERTED_FILE = 'sample.docx'

test.describe('Convert with ONLYOFFICE', () => {
	test.beforeEach(async ({ filesPage, user }) => {
		await uploadFile(`/${SOURCE_FILE}`, path.join(TEMPLATES_DIR, SOURCE_FILE), user)
		await filesPage.goto()
	})

	test.afterEach(async ({ user }) => {
		await deleteFile(`/${SOURCE_FILE}`, user)
		await deleteFile(`/${CONVERTED_FILE}`, user)
	})

	test('Converts odt to docx', async ({ filesPage }) => {
		await filesPage.rightClickFile(SOURCE_FILE)
		await filesPage.menuItem('Convert with ONLYOFFICE').click()

		await expect(filesPage.successToast()).toBeVisible()
		await expect(filesPage.fileRow(CONVERTED_FILE)).toBeVisible()
	})
})
