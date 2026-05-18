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
