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
