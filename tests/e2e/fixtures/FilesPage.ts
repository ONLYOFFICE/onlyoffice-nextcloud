/*
 * Copyright (C) Ascensio System SIA 2026
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
import { Page, Locator, expect } from '@playwright/test';

export class FilesPage {
	readonly page: Page;

	constructor(page: Page) {
		this.page = page;
	}

	async goto(): Promise<void> {
		await this.page.goto('index.php/apps/files/files');
	}

	newButton(): Locator {
		return this.page.locator('form[data-cy-upload-picker=""]');
	}

	menuItem(name: string): Locator {
		return this.page.locator('button[role="menuitem"]').filter({ hasText: name });
	}

	fileRow(name: string): Locator {
		return this.page.locator(`tr[data-cy-files-list-row-name="${name}"]`);
	}

	async openNewMenu(): Promise<void> {
		await this.newButton().click();
	}

	async rightClickFile(name: string): Promise<void> {
		await this.fileRow(name).click({ button: 'right' });
	}

	async dblClickFile(name: string): Promise<void> {
		await this.fileRow(name).dblclick();
	}

	async openSidebar(name: string): Promise<void> {
		await this.rightClickFile(name);
		await this.menuItem('Details').click();
	}

	sidebarTab(tabId: string): Locator {
		return this.page.locator(`#tab-button-${tabId}`);
	}

	successToast(): Locator {
		return this.page.locator('.toast-success');
	}

	async waitForSuccess(): Promise<void> {
		await expect(this.successToast()).toBeVisible()
	}

	errorToast(): Locator {
		return this.page.locator('.toast-error');
	}

	async waitForError(): Promise<void> {
		await expect(this.errorToast()).toBeVisible()
	}

	templatePickerDialog(): Locator {
		return this.page.locator('.templates-picker')
	}

	templatePickerItem(name: string): Locator {
		return this.templatePickerDialog()
			.locator('.template-picker__item')
			.filter({ hasText: name })
	}

	templatePickerSubmit(): Locator {
		return this.templatePickerDialog().locator('input[type="submit"]')
	}

	downloadPickerDialog(): Locator {
		return this.page.locator('.onlyoffice-download-picker');
	}

	downloadPickerSelect(): Locator {
		return this.page.locator('#onlyoffice-download-select');
	}

	downloadPickerButton(): Locator {
		return this.page.getByRole('dialog').getByRole('button', { name: 'Download', exact: true });
	}
}
