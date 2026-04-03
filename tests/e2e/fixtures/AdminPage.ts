import { Locator, Page, expect } from "@playwright/test";

export class AdminPage {
	readonly page: Page

	constructor(page: Page) {
		this.page = page
	}

	async goto(): Promise<void> {
		await this.page.goto('index.php/settings/admin/onlyoffice')
	}

	url(): Locator {
		return this.page.locator('#onlyoffice-url')
	}

	secret(): Locator {
		return this.page.locator('#onlyoffice-secret')
	}

	saveServerSettingsButton(): Locator {
		return this.page.locator('#onlyoffice-server-save')
	}

	errorToast(): Locator {
		return this.page.locator('.toast-error')
	}

	successToast(): Locator {
		return this.page.locator('.toast-success')
	}

	async waitForSuccess(): Promise<void> {
		await expect(this.successToast()).toBeVisible()
	}

	commonSettingsSection(): Locator {
		return this.page.locator('.section-onlyoffice-common')
	}

	templatesSection(): Locator {
		return this.page.locator('.section-onlyoffice-templates')
	}

	securitySection(): Locator {
		return this.page.locator('.section-onlyoffice-watermark')
	}

	templateItem(name: string): Locator {
		return this.page.locator('.onlyoffice-template-item').filter({ has: this.page.locator('p', { hasText: name }) })
	}

	forcesaveCheckbox(): Locator {
		return this.page.locator('#onlyoffice-forcesave')
	}

	forcesaveLabel(): Locator {
		return this.page.locator('label[for="onlyoffice-forcesave"]')
	}

	pluginsCheckbox(): Locator {
		return this.page.locator('#onlyoffice-plugins')
	}

	pluginsLabel(): Locator {
		return this.page.locator('label[for="onlyoffice-plugins"]')
	}

	advancedToggle(): Locator {
		return this.page.locator('.onlyoffice-adv a')
	}

	internalUrl(): Locator {
		return this.page.locator('#onlyoffice-internal-url')
	}

	storageUrl(): Locator {
		return this.page.locator('#onlyoffice-storage-url')
	}

	jwtHeader(): Locator {
		return this.page.locator('#onlyoffice-jwt-header')
	}

	async fillUrl(url: string): Promise<void> {
		await this.url().fill(url)
	}

	async fillSecret(secret: string): Promise<void> {
		await this.secret().fill(secret)
	}

	async fillInternalUrl(url: string): Promise<void> {
		const input = this.internalUrl()
		if (!await input.isVisible()) {
			await this.advancedToggle().click()
		}
		await input.fill(url)
	}

	async clearServerSettings(): Promise<void> {
		await this.url().fill('')
		await this.secret().fill('')
		await this.fillInternalUrl('')
		await this.storageUrl().fill('')
		await this.jwtHeader().fill('')
	}

	async saveServerSettings(): Promise<void> {
		await this.saveServerSettingsButton().click()
	}

	async deleteTemplate(name: string): Promise<void> {
		await this.templateItem(name).locator('.onlyoffice-template-delete').click()
	}

	async uploadTemplate(filePath: string): Promise<void> {
		await this.page.locator('#onlyofficeAddTemplate').setInputFiles(filePath)
	}

	async saveCommonSettings(): Promise<void> {
		await this.page.locator('#onlyoffice-common-save').click()
	}

	async saveSecuritySettings(): Promise<void> {
		await this.page.locator('#onlyoffice-security-save').click()
	}
}