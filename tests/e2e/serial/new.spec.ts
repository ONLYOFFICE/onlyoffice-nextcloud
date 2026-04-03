import { test } from '../fixtures'
import { randomName } from '../helpers/utils'
import { addGlobalTemplate, deleteGlobalTemplate } from '../helpers/templates'

test.describe('with templates', () => {
	const templateName = randomName()
	let templateId: number

	test.beforeAll(async () => {
		templateId = await addGlobalTemplate(templateName, 'docx')
	})

	test.afterAll(async () => {
		await deleteGlobalTemplate(templateId)
	})

	test('creates file from selected template', async ({ filesPage, editorPage }) => {
		await filesPage.goto()
		await filesPage.openNewMenu()
		await filesPage.menuItem('New document').click()
		const createButton = filesPage.page.locator('button[data-cy-files-new-node-dialog-submit=""]').filter({ hasText: 'Create' })
		await createButton.click()
		await filesPage.templatePickerItem(templateName).click()
		await filesPage.templatePickerSubmit().click()
		await editorPage.waitForEditor()
	})
})
