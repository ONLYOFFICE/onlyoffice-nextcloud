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
