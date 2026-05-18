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
import fs from 'node:fs';
import path from 'node:path';
import { uploadFile, deleteFile } from './webdav';
import { User, admin } from './users';
import { createAPIRequest, ocsHeadersWithAuth } from './ocs';

export const DOCUMENT_TEMPLATES_PATH = path.join(__dirname, '../../../assets/document-templates/default');

export async function uploadNewTemplate(
	filename: string,
	fileType: string,
	user: User
): Promise<void> {
	await uploadFile(
		`/${filename}.${fileType}`,
		path.join(DOCUMENT_TEMPLATES_PATH,`new.${fileType}`),
		user,
	);
}

export async function uploadTemplate(
	filename: string,
	user: User,
): Promise<void> {
	await uploadFile(`/${filename}`, path.join(DOCUMENT_TEMPLATES_PATH, filename), user);
}

export async function deleteTemplate(
	filename: string,
	user: User,
): Promise<void> {
	await deleteFile(`/${filename}`, user);
}

export async function addGlobalTemplate(templateName: string, fileType: string): Promise<number> {
	const request = await createAPIRequest()
	const response = await request.post('/index.php/apps/onlyoffice/ajax/template', {
		headers: ocsHeadersWithAuth(admin.username, admin.password),
		multipart: {
			file: {
				name: `${templateName}.${fileType}`,
				mimeType: 'application/octet-stream',
				buffer: fs.readFileSync(path.join(DOCUMENT_TEMPLATES_PATH, `new.${fileType}`)),
			},
		},
		failOnStatusCode: true,
	})
	const body = await response.json()
	await request.dispose()
	return body.id
}

export async function deleteGlobalTemplate(templateId: number): Promise<void> {
	const request = await createAPIRequest()
	await request.delete('/index.php/apps/onlyoffice/ajax/template', {
		headers: ocsHeadersWithAuth(admin.username, admin.password),
		params: { templateId },
		failOnStatusCode: true,
	})
	await request.dispose()
}
