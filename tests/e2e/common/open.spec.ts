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
import { uploadNewTemplate } from '../helpers/templates'

const TEMPLATES_DIR = path.join(__dirname, '../templates')

const SAMPLE_TXT = 'sample.txt'

test.describe('Open in ONLYOFFICE action', () => {
    test.beforeEach(async ({ filesPage, user }) => {
        await uploadFile(`/${SAMPLE_TXT}`, path.join(TEMPLATES_DIR, SAMPLE_TXT), user)
        await filesPage.goto()
    })

    test.afterEach(async ({ user }) => {
        await deleteFile(`/${SAMPLE_TXT}`, user)
    })

    test('Opens txt file in ONLYOFFICE editor', async ({ filesPage }) => {
        await filesPage.rightClickFile(SAMPLE_TXT)
        await filesPage.menuItem('Open in ONLYOFFICE').click()
        await filesPage.page.waitForEvent('console', msg => msg.text() === 'ONLYOFFICE Editor is loaded')
    })
})

for (const fileType of ['docx', 'xlsx', 'pptx']) {
    test.describe(`Default opening for ${fileType} file`, () => {
        const fileName = `open.${fileType}`

        test.beforeEach(async ({ filesPage, user }) => {
            await uploadNewTemplate('open', fileType, user)
            await filesPage.goto()
        })

        test.afterEach(async ({ user }) => {
            await deleteFile(`/${fileName}`, user)
        })

        test(`Editor opens for ${fileType} file`, async ({ filesPage, editorPage }) => {
            await filesPage.dblClickFile(fileName)
            await editorPage.waitForEditor()
        })
    })
}
