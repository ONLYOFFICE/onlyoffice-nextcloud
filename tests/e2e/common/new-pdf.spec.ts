/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

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

		await editorPage.waitForEditor()
	})
})

test('Create new PDF form from blank template', async ({ filesPage, editorPage }) => {
	await filesPage.goto()
	await filesPage.openNewMenu()

	await filesPage.menuItem('New PDF form').click()

	const blankButton = filesPage.page.getByRole('dialog').getByRole('button', { name: 'Blank' })
	await expect(blankButton).toBeVisible()
	await blankButton.click()

	await editorPage.waitForEditor()
})
