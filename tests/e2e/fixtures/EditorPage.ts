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
}
