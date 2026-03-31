import { test, expect } from '../fixtures'
import { deleteFile } from '../helpers/webdav'
import { createPublicShare, deleteShare, ShareInfo } from '../helpers/shares'
import { uploadNewTemplate } from '../helpers/templates'

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

test('Editor auto-opens for public docx share for user', async ({ userPage }) => {
	await userPage.goto(`index.php/s/${share.token}`)
	await userPage.waitForEvent('console', msg => msg.text() === 'ONLYOFFICE Editor is loaded')
})

test('Editor auto-opens for public docx share for guest', async ({ guestPage }) => {
	await guestPage.goto(`index.php/s/${share.token}`)
	await guestPage.waitForEvent('console', msg => msg.text() === 'ONLYOFFICE Editor is loaded')
})
