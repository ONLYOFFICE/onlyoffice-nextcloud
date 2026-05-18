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

import { test } from '../fixtures'
import path from 'node:path'
import { uploadFile, deleteFile } from '../helpers/webdav'
import { createPublicShare, deleteShare, ShareInfo } from '../helpers/shares'
import { uploadNewTemplate } from '../helpers/templates'
import { EditorPage } from '../fixtures/EditorPage'
import { FilesPage } from '../fixtures/FilesPage'

const TEMPLATES_DIR = path.join(__dirname, '../templates')

test.describe('File share - default open enabled (docx)', () => {
	const FILE_NAME = 'share.docx'
	let share: ShareInfo

	test.beforeEach(async ({ user }) => {
		await uploadNewTemplate('share', 'docx', user)
		share = await createPublicShare(`/${FILE_NAME}`, user)
	})

	test.afterEach(async ({ user }) => {
		await deleteShare(share.id, user)
		await deleteFile(`/${FILE_NAME}`, user)
	})

	test('Editor auto-opens on load and reopens via double-click for user', async ({ userPage, filesPage, editorPage }) => {
		await userPage.goto(`index.php/s/${share.token}`)
		await editorPage.waitForEditor()
		await editorPage.closeButton().click()
		await filesPage.dblClickFile(share.token)
		await editorPage.waitForEditor()
	})

	test('Editor auto-opens on load and reopens via double-click for guest', async ({ guestPage }) => {
		await guestPage.goto(`index.php/s/${share.token}`)
		const editorPage = new EditorPage(guestPage)
		const filesPage = new FilesPage(guestPage)
		await editorPage.waitForEditor()
		await editorPage.closeButton().click()
		await filesPage.dblClickFile(share.token)
		await editorPage.waitForEditor()
	})
})

test.describe('File share - default open disabled (txt)', () => {
	const FILE_NAME = 'sample.txt'
	let share: ShareInfo

	test.beforeEach(async ({ user }) => {
		await uploadFile(`/${FILE_NAME}`, path.join(TEMPLATES_DIR, FILE_NAME), user)
		share = await createPublicShare(`/${FILE_NAME}`, user)
	})

	test.afterEach(async ({ user }) => {
		await deleteShare(share.id, user)
		await deleteFile(`/${FILE_NAME}`, user)
	})

	test('Opens editor via action for user', async ({ filesPage, editorPage }) => {
		await filesPage.page.goto(`index.php/s/${share.token}`)
		await filesPage.rightClickFile(share.token)
		await filesPage.menuItem('Open in ONLYOFFICE').click()
		await editorPage.waitForEditor()
	})

	test('Opens editor via action for guest', async ({ guestPage }) => {
		const filesPage = new FilesPage(guestPage)
		await filesPage.page.goto(`index.php/s/${share.token}`)
		await filesPage.rightClickFile(share.token)
		await filesPage.menuItem('Open in ONLYOFFICE').click()

		const editorPage = new EditorPage(guestPage)
		await editorPage.waitForEditor()
	})
})
