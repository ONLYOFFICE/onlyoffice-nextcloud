import { test, expect } from '../fixtures'
import { deleteFile } from '../helpers/webdav'
import { createPublicShare, deleteShare, ShareInfo } from '../helpers/shares'
import { uploadNewTemplate } from '../helpers/templates'
import { EditorPage } from '../fixtures/EditorPage'

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

test('Editor auto-opens for public docx share for user', async ({ userPage, editorPage }) => {
	await userPage.goto(`index.php/s/${share.token}`)
	await expect(editorPage.editorIFrame()).toBeVisible()
	await expect(editorPage.documentContent()).toBeVisible()
})

test('Editor auto-opens for public docx share for guest', async ({ guestPage }) => {
	await guestPage.goto(`index.php/s/${share.token}`)
	const editorPage = new EditorPage(guestPage)
	await expect(editorPage.editorIFrame()).toBeVisible()
	await expect(editorPage.documentContent()).toBeVisible()
})
