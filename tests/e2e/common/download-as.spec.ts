import { test, expect } from '../fixtures'
import { uploadNewTemplate } from '../helpers/templates'
import { deleteFile } from '../helpers/webdav'

for (const fileType of ['docx', 'pdf', 'pptx', 'xlsx']) {
	test.describe(`"Download as" action check for ${fileType} file`, () => {
		const fileName = `download.${fileType}`
		test.beforeEach(async ({ filesPage, user }) => {
			await uploadNewTemplate('download', fileType, user)
			await filesPage.goto()
		})

		test.afterEach(async ({ user }) => {
			await deleteFile(`/${fileName}`, user)
		})

		test(`"Download as" downloads ${fileType} file`, async ({ filesPage }) => {
			await filesPage.rightClickFile(fileName)
			await filesPage.menuItem('Download as').click()

			await expect(filesPage.downloadPickerDialog()).toBeVisible()
			await filesPage.downloadPickerSelect().selectOption({ index: 0 })

			const download = filesPage.page.waitForEvent('download')
			await filesPage.downloadPickerButton().click()

			expect((await download).suggestedFilename()).toBeTruthy()
		})
	})
}
