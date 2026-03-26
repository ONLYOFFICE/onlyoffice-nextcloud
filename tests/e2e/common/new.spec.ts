import { test, expect } from '../fixtures'

test('New file buttons are present on the files page', async ({ filesPage }) => {
	await filesPage.goto()
	await filesPage.openNewMenu()

	for (const fileType of ['document', 'presentation', 'spreadsheet', 'PDF form']) {
		await expect(filesPage.menuItem(`New ${fileType}`)).toBeVisible()
	}
})

for (const fileType of ['document', 'presentation', 'spreadsheet']) {
	test(`Create new ${fileType} file`, async ({ filesPage, editorPage }) => {
		await filesPage.goto()
		await filesPage.openNewMenu()

		await filesPage.menuItem(`New ${fileType}`).click()
		const createButton = filesPage.page.locator('button[data-cy-files-new-node-dialog-submit=""]').filter({ hasText: 'Create' })
		await expect(createButton).toBeVisible()
		await createButton.click()
		await expect(editorPage.editorIFrame()).toBeVisible()
		await expect(editorPage.documentContent()).toBeVisible()
	})
}
