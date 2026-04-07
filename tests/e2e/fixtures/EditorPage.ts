import { Page, Locator } from '@playwright/test';

export class EditorPage {
	readonly page: Page;

	constructor(page: Page) {
		this.page = page;
	}

	editorIFrame(): Locator {
		return this.page.locator('iframe[id="onlyofficeFrame"]');
	}

	documentContent(): Locator {
		return this.editorIFrame()
			.contentFrame()
			.locator('iframe[name="frameEditor"]')
			.contentFrame()
			.locator('body');
	}

	closeButton(): Locator {
		return this.page.locator('#onlyofficeFrame')
			.contentFrame()
			.locator('iframe[name="frameEditor"]')
			.contentFrame()
			.getByRole('button', { name: 'Close file' })
	}

	async waitForEditor(): Promise<void> {
		await this.page.waitForEvent('console', msg => msg.text() === 'ONLYOFFICE Editor is loaded')
	}
}
