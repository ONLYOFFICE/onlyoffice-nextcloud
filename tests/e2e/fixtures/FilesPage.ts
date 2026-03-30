import { Page, Locator } from '@playwright/test';

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
