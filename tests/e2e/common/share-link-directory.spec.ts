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
