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
		await expect(filesPage.page.getByText(`${shareRecipient.username} Custom`)).toBeVisible()

		await filesPage.sidebarTab('onlyofficeSharingTabView').click()

		const shareItem = filesPage.page.locator('.onlyoffice-share-item')
		await expect(shareItem).toBeVisible()
		await shareItem.locator('#onlyoffice-share-action').click()

		const popup = filesPage.page.locator('#onlyoffice-share-popup-menu')
		await expect(popup).toBeVisible()

		const reviewCheckbox = popup.getByText('Review only')
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

		const shareItem = filesPage.page.locator('.onlyoffice-share-item')
		await expect(shareItem).toBeVisible()
		await shareItem.locator('#onlyoffice-share-action').click()

		const popup = filesPage.page.locator('#onlyoffice-share-popup-menu')
		await expect(popup).toBeVisible()

		const reviewCheckbox = popup.getByText('Review only')
		await reviewCheckbox.click()
		await expect(reviewCheckbox).toBeChecked()
	})
})
