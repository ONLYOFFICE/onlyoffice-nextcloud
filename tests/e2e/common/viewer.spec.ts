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
import { uploadFile, deleteFile, getFileId } from '../helpers/webdav'
import { createRoom, deleteRoom, shareFileToRoom } from '../helpers/talk'
import path from 'node:path'

const FILE_NAME = 'viewer-test.docx'
const FILE_PATH = `/${FILE_NAME}`
const LOCAL_PATH = path.join(__dirname, '../../../assets/document-templates/default/new.docx')

let roomToken: string

test.beforeEach(async ({ user }) => {
	await uploadFile(FILE_PATH, LOCAL_PATH, user)
	const fileId = await getFileId(FILE_PATH, user)
	roomToken = await createRoom('viewer-test', user)
	await shareFileToRoom(roomToken, fileId, user)
})

test.afterEach(async ({ user }) => {
	await deleteRoom(roomToken, user)
	await deleteFile(FILE_PATH, user)
})

test('docx shared in Talk opens in ONLYOFFICE viewer', async ({ editorPage }) => {
	await editorPage.page.goto(`/index.php/call/${roomToken}`)

	const file = editorPage.page.getByLabel('Conversation messages').getByText(FILE_NAME)
	await expect(file).toBeVisible()
	await file.click()

	await editorPage.waitForEditor()
})
