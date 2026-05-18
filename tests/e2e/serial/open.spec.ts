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
import { deleteFile } from '../helpers/webdav'
import { uploadNewTemplate } from '../helpers/templates'
import { saveCommonSettings } from '../helpers/adminSettings'
import { EditorPage } from '../fixtures/EditorPage'

test.describe('Open in new tab', () => {
	const fileName = 'open.docx'

	test.beforeEach(async ({ filesPage, user }) => {
		await uploadNewTemplate('open', 'docx', user)
		await saveCommonSettings({ sameTab: false })
		await filesPage.goto()
	})

	test.afterEach(async ({ user }) => {
		await saveCommonSettings({ sameTab: true })
		await deleteFile(`/${fileName}`, user)
	})

	test('Opens editor in a new tab', async ({ filesPage, userPage }) => {
		const [newPage] = await Promise.all([
			filesPage.page.context().waitForEvent('page'),
			filesPage.dblClickFile(fileName),
		])
		const editorPage = new EditorPage(newPage)
		await editorPage.waitForEditor()
	})
})
