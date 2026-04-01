import { Locator, Page } from "@playwright/test";

export class AdminPage {
	readonly page: Page

	constructor(page: Page) {
		this.page = page
	}

	async goto(): Promise<void> {
		await this.page.goto('index.php/settings/admin/onlyoffice')
	}

	url(): Locator {
		return this.page.locator('#onlyofficeUrl')
	}

	secret(): Locator {
		return this.page.locator('#onlyofficeSecret')
	}

	saveServerSettingsButton(): Locator {
		return this.page.locator('#onlyofficeAddrSave')
	}

	errorToast(): Locator {
		return this.page.locator('.toast-error')
	}

	successToast(): Locator {
		return this.page.locator('.toast-success')
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
		return this.page.locator('#onlyofficeForcesave')
	}

	forcesaveLabel(): Locator {
		return this.page.locator('label[for="onlyofficeForcesave"]')
	}

	pluginsCheckbox(): Locator {
		return this.page.locator('#onlyofficePlugins')
	}

	pluginsLabel(): Locator {
		return this.page.locator('label[for="onlyofficePlugins"]')
	}

	async fillUrl(url: string): Promise<void> {
		await this.url().fill(url)
	}

	async fillSecret(secret: string): Promise<void> {
		await this.secret().fill(secret)
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
		await this.page.locator('#onlyofficeSave').click()
	}

	async saveSecuritySettings(): Promise<void> {
		await this.page.locator('#onlyofficeSecuritySave').click()
	}
}