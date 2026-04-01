import path from 'node:path';
import { uploadFile, deleteFile } from './webdav';
import { User } from './users';

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
