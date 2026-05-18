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
