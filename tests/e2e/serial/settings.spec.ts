import { test, expect } from '../fixtures'
import path from 'node:path'
import { DOCUMENT_TEMPLATES_PATH } from '../helpers/templates'

const docsUrl = process.env.DOCUMENT_SERVER_URL ?? 'http://localhost:8080'
const jwtSecret = process.env.JWT_SECRET ?? 'secret'

const TEMPLATE_NAME = 'new.docx'
const TEMPLATE_PATH = path.join(`${DOCUMENT_TEMPLATES_PATH}/${TEMPLATE_NAME}`)


test.describe.serial('Admin settings', () => {
	test.beforeEach(async ({ adminPage }) => {
		await adminPage.goto()
	})

	test.describe('Server settings', () => {
		test('Save with invalid url', async ({ adminPage }) => {
			await adminPage.clearServerSettings()
			await adminPage.fillUrl('http://onlyoffice.example.invalid')
			await adminPage.fillSecret(jwtSecret)
			await adminPage.saveServerSettings()

			await expect(adminPage.errorToast()).toBeVisible()
			await expect(adminPage.commonSettingsSection()).toBeHidden()
			await expect(adminPage.templatesSection()).toBeHidden()
			await expect(adminPage.securitySection()).toBeHidden()
		})

		test('Save with valid url', async ({ adminPage }) => {
			await adminPage.fillUrl(docsUrl)
			await adminPage.fillSecret(jwtSecret)
			await adminPage.saveServerSettings()

			await expect(adminPage.successToast()).toBeVisible()
			await expect(adminPage.commonSettingsSection()).toBeVisible()
			await expect(adminPage.templatesSection()).toBeVisible()
			await expect(adminPage.securitySection()).toBeVisible()

			await adminPage.goto()
			await expect(adminPage.url()).toHaveValue(docsUrl.replace(/\/?$/, '/'))
		})
	})

	test.describe('Common settings', () => {
		test('Forcesave setting persists after reload', async ({ adminPage }) => {
			const checkbox = adminPage.forcesaveCheckbox()
			const initial = await checkbox.evaluate((el: HTMLInputElement) => el.checked)

			await adminPage.forcesaveLabel().click()
			await adminPage.saveCommonSettings()
			await expect(adminPage.successToast()).toBeVisible()

			await adminPage.goto()
			await expect(checkbox).toBeChecked({ checked: !initial })

			// restore
			await adminPage.forcesaveLabel().click()
			await adminPage.saveCommonSettings()
		})
	})

	test.describe('Security settings', () => {
		test('Plugins setting persists after reload', async ({ adminPage }) => {
			const checkbox = adminPage.pluginsCheckbox()
			const initial = await checkbox.evaluate((el: HTMLInputElement) => el.checked)

			await adminPage.pluginsLabel().click()
			await adminPage.saveSecuritySettings()
			await expect(adminPage.successToast()).toBeVisible()

			await adminPage.goto()
			await expect(checkbox).toBeChecked({ checked: !initial })

			// restore
			await adminPage.pluginsLabel().click()
			await adminPage.saveSecuritySettings()
		})
	})

	test.describe('Common templates', () => {
		test('Upload template', async ({ adminPage }) => {
			await adminPage.uploadTemplate(TEMPLATE_PATH)

			await expect(adminPage.successToast()).toBeVisible()
			await expect(adminPage.templateItem(TEMPLATE_NAME)).toBeVisible()
		})

		test('Delete template', async ({ adminPage }) => {
			await adminPage.deleteTemplate(TEMPLATE_NAME)

			await expect(adminPage.successToast()).toBeVisible()
			await expect(adminPage.templateItem(TEMPLATE_NAME)).toBeHidden()
		})
	})
})
