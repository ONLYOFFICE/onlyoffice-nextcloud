import { test } from '../fixtures'
import { deleteFile } from '../helpers/webdav'
import { uploadNewTemplate } from '../helpers/templates'
import { saveCommonSettings } from '../helpers/adminSettings'
import { EditorPage } from '../fixtures/EditorPage'

test.describe('Open in new tab', () => {
	const fileName = 'open.docx'

	test.beforeEach(async ({ filesPage, user }) => {
		await uploadNewTemplate('open', 'docx', user)
		await saveCommonSettings({ sameTab: false })
		await filesPage.goto()
	})

	test.afterEach(async ({ user }) => {
		await saveCommonSettings({ sameTab: true })
		await deleteFile(`/${fileName}`, user)
	})

	test('Opens editor in a new tab', async ({ filesPage, userPage }) => {
		const [newPage] = await Promise.all([
			filesPage.page.context().waitForEvent('page'),
			filesPage.dblClickFile(fileName),
		])
		const editorPage = new EditorPage(newPage)
		await editorPage.waitForEditor()
	})
})
