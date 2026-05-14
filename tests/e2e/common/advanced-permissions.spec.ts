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
import { User, createRandomUser, deleteUser } from '../helpers/users'
import { deleteFile } from '../helpers/webdav'
import { createUserShare, createPublicEditShare, deleteShare, ShareInfo } from '../helpers/shares'
import { uploadNewTemplate } from '../helpers/templates'
import { saveCommonSettings } from '../helpers/adminSettings'

const USER_SHARE_FILE = 'permissions-user.docx'
const PUBLIC_SHARE_FILE = 'permissions-public.docx'

let shareRecipient: User
let share: ShareInfo

test.describe('User share without reshare permission', () => {
	test.beforeEach(async ({ filesPage, user }) => {
		await saveCommonSettings({ advanced: true })
		shareRecipient = await createRandomUser()
		await uploadNewTemplate('permissions-user', 'docx', user)
		share = await createUserShare(`/${USER_SHARE_FILE}`, shareRecipient.username, user)
		await filesPage.goto()
	})

	test.afterEach(async ({ user }) => {
		await deleteShare(share.id, user)
		await deleteFile(`/${USER_SHARE_FILE}`, user)
		await deleteUser(shareRecipient)
		await saveCommonSettings({ advanced: false })
	})

	test('Review only permission can be toggled', async ({ filesPage }) => {
		await filesPage.openSidebar(USER_SHARE_FILE)
		await filesPage.sidebarTab('sharing').click()
		await expect(filesPage.page.locator('.sharing-entry__summary__desc', { hasText: shareRecipient.username })).toBeVisible()

		await filesPage.sidebarTab('onlyofficeSharingTabView').click()

		const shareItem = filesPage.page.getByRole('listitem').filter({ hasText: shareRecipient.username })
		await expect(shareItem).toBeVisible()

		await filesPage.page.getByRole('tabpanel', { name: 'Advanced' }).getByLabel('Actions').click()

		const reviewCheckbox = filesPage.page.getByRole('menuitemcheckbox', { name: 'Review only' })
		await reviewCheckbox.click()
		await expect(reviewCheckbox).toBeChecked()
	})
})

test.describe('Public share with edit permission', () => {
	test.beforeEach(async ({ filesPage, user }) => {
		await saveCommonSettings({ advanced: true })
		await uploadNewTemplate('permissions-public', 'docx', user)
		share = await createPublicEditShare(`/${PUBLIC_SHARE_FILE}`, user)
		await filesPage.goto()
	})

	test.afterEach(async ({ user }) => {
		await deleteShare(share.id, user)
		await deleteFile(`/${PUBLIC_SHARE_FILE}`, user)
		await saveCommonSettings({ advanced: false })
	})

	test('Review only permission can be toggled', async ({ filesPage }) => {
		await filesPage.openSidebar(PUBLIC_SHARE_FILE)
		await filesPage.sidebarTab('sharing').click()
		await expect(filesPage.page.getByTitle('Share link')).toBeVisible()

		await filesPage.sidebarTab('onlyofficeSharingTabView').click()

		const shareItem = filesPage.page.getByRole('listitem').filter({ hasText: 'Share link' })
		await expect(shareItem).toBeVisible()

		await filesPage.page.getByRole('tabpanel', { name: 'Advanced' }).getByLabel('Actions').click()

		const reviewCheckbox = filesPage.page.getByRole('menuitemcheckbox', { name: 'Review only' })
		await reviewCheckbox.click()
		await expect(reviewCheckbox).toBeChecked()
	})
})
