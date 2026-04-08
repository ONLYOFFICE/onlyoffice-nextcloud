import { test, expect } from '../fixtures'
import { Locator } from '@playwright/test'
import path from 'node:path'
import { DOCUMENT_TEMPLATES_PATH } from '../helpers/templates'
import { createGroup, deleteGroup } from '../helpers/groups'
import { randomName } from '../helpers/utils'
import { saveCommonSettings } from '../helpers/adminSettings'

const docsUrl = process.env.DOCUMENT_SERVER_URL ?? 'http://localhost:8080'
const internalUrl = process.env.DOCUMENT_SERVER_INTERNAL_URL ?? ''
const storageUrl = process.env.DOCUMENT_STORAGE_URL ?? ''
const jwtSecret = process.env.JWT_SECRET ?? 'secret'
const jwtHeader = process.env.JWT_HEADER ?? ''

const TEMPLATE_NAME = 'new.docx'
const TEMPLATE_PATH = path.join(`${DOCUMENT_TEMPLATES_PATH}/${TEMPLATE_NAME}`)


test.describe('Admin settings', () => {
	test.beforeEach(async ({ adminPage }) => {
		await adminPage.goto()
	})

	test.describe('Server settings', () => {
		test('Save with invalid url', async ({ adminPage }) => {
			await adminPage.clearServerSettings()
			await adminPage.fillUrl('http://onlyoffice.example.invalid')
			await adminPage.fillSecret(jwtSecret)
			await adminPage.saveServerSettings()

			await adminPage.waitForError()
			await expect(adminPage.commonSettingsSection()).toBeHidden()
			await expect(adminPage.templatesSection()).toBeHidden()
			await expect(adminPage.securitySection()).toBeHidden()
		})

		test('Save with valid url', async ({ adminPage }) => {
			await adminPage.clearServerSettings()
			await adminPage.fillUrl(docsUrl)
			await adminPage.fillSecret(jwtSecret)
			await adminPage.fillInternalUrl(internalUrl)
			await adminPage.fillStorageUrl(storageUrl)
			await adminPage.fillJwtHeader(jwtHeader)
			await adminPage.saveServerSettings()

			await adminPage.waitForSuccess()
			await expect(adminPage.commonSettingsSection()).toBeVisible()
			await expect(adminPage.templatesSection()).toBeVisible()
			await expect(adminPage.securitySection()).toBeVisible()

			await adminPage.goto()
			await expect(adminPage.url()).toHaveValue(docsUrl.replace(/\/?$/, '/'))
		})
	})

	test.describe('Common settings', () => {
		test.afterEach(async () => {
			await saveCommonSettings({})
		})

		test('Settings persist after save and reload', async ({ adminPage }) => {
			type CheckboxSetting = { checkbox: Locator; label: Locator; value: boolean }

			const settings: CheckboxSetting[] = [
				{ checkbox: adminPage.forcesaveCheckbox(), label: adminPage.forcesaveLabel(), value: true },
			]

			for (const setting of settings) {
				const current = await setting.checkbox.evaluate((el: HTMLInputElement) => el.checked)
				if (current !== setting.value) await setting.label.click()
			}

			await adminPage.saveCommonSettings()
			await adminPage.waitForSuccess()

			await adminPage.goto()

			for (const setting of settings) {
				await expect(setting.checkbox).toBeChecked({ checked: setting.value })
			}
		})

		test.describe('Allow groups', () => {
			let group: string

			test.beforeAll(async () => {
				group = `group-${randomName()}`
				await createGroup(group)
			})

			test.afterAll(async () => {
				await deleteGroup(group)
			})

			test('selector appears and selected group persists', async ({ adminPage }) => {
				await expect(adminPage.groupsSelector()).toBeHidden()
				await adminPage.useGroupsLabel().click()
				await expect(adminPage.groupsSelector()).toBeVisible()

				await adminPage.groupsSelector().fill(group)
				await adminPage.groupsOption(group).waitFor()
				await adminPage.groupsOption(group).click()

				await adminPage.saveCommonSettings()
				await adminPage.waitForSuccess()

				await adminPage.goto()
				await expect(adminPage.groupsSelected(group)).toBeVisible()
			})
		})
	})

	test.describe('Security settings', () => {
		test('Plugins setting persists after reload', async ({ adminPage }) => {
			const checkbox = adminPage.pluginsCheckbox()
			const initial = await checkbox.evaluate((el: HTMLInputElement) => el.checked)

			await adminPage.pluginsLabel().click()
			await adminPage.saveSecuritySettings()
			await adminPage.waitForSuccess()

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

			await adminPage.waitForSuccess()
			await expect(adminPage.templateItem(TEMPLATE_NAME)).toBeVisible()
		})

		test('Delete template', async ({ adminPage }) => {
			await adminPage.deleteTemplate(TEMPLATE_NAME)

			await adminPage.waitForSuccess()
			await expect(adminPage.templateItem(TEMPLATE_NAME)).toBeHidden()
		})
	})
})
