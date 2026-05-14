/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

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

	async waitForError(): Promise<void> {
		await expect(this.errorToast()).toBeVisible()
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

	useGroupsCheckbox(): Locator {
		return this.page.locator('#onlyoffice-groups')
	}

	useGroupsLabel(): Locator {
		return this.page.locator('label[for="onlyoffice-groups"]')
	}

	groupsSelector(): Locator {
		return this.commonSettingsSection().getByPlaceholder('Groups')
	}

	groupsOption(name: string): Locator {
		return this.page.locator('.vs__dropdown-option', { hasText: name })
	}

	groupsSelected(name: string): Locator {
		return this.page.locator('.vs__selected', { hasText: name })
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

	async fillStorageUrl(url: string): Promise<void> {
		const input = this.storageUrl()
		if (!await input.isVisible()) {
			await this.advancedToggle().click()
		}
		await input.fill(url)
	}

	async fillJwtHeader(header: string): Promise<void> {
		const input = this.jwtHeader()
		if (!await input.isVisible()) {
			await this.advancedToggle().click()
		}
		await input.fill(header)
	}

	async clearServerSettings(): Promise<void> {
		await this.url().fill('')
		await this.secret().fill('')
		await this.fillInternalUrl('')
		await this.fillStorageUrl('')
		await this.fillJwtHeader('')
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