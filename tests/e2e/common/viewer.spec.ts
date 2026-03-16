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

test('docx shared in Talk opens in ONLYOFFICE viewer', async ({ userPage }) => {
	await userPage.goto(`/index.php/call/${roomToken}`)

	const file = userPage.getByLabel('Conversation messages').getByText(FILE_NAME)
	await expect(file).toBeVisible()
	await file.click()

	const viewerFrame = userPage.locator('#onlyofficeViewerFrame')
	await expect(viewerFrame).toBeVisible()
	await expect(viewerFrame.contentFrame().locator('iframe[name="frameEditor"]').contentFrame().locator('body')).toBeVisible()
})
