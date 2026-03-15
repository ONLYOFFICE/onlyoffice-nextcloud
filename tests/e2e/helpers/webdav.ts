import fs from 'node:fs';
import { User } from './users';
import { createAPIRequest, ocsHeadersWithAuth } from './ocs';

function davPath(user: string, remotePath: string): string {
	return `/remote.php/dav/files/${encodeURIComponent(user)}${remotePath}`;
}

export async function uploadFile(
	remotePath: string,
	localPath: string,
	user: User,
): Promise<void> {
	const request = await createAPIRequest()
	await request.put(davPath(user.username, remotePath), {
		data: fs.readFileSync(localPath),
		headers: {
			'Content-Type': 'application/octet-stream',
			...ocsHeadersWithAuth(user.username, user.password),
		},
		failOnStatusCode: true,
	});
	await request.dispose()
}

export async function deleteFile(
	remotePath: string,
	user: User,
): Promise<void> {
	const request = await createAPIRequest()
	await request.delete(davPath(user.username, remotePath), {
		headers: ocsHeadersWithAuth(user.username, user.password),
		failOnStatusCode: true,
	});
	await request.dispose()
}

export async function getFileId(
	remotePath: string,
	user: User,
): Promise<number> {
	const request = await createAPIRequest()
	const response = await request.fetch(davPath(user.username, remotePath), {
		method: 'PROPFIND',
		headers: {
			...ocsHeadersWithAuth(user.username, user.password),
			'Depth': '0',
			'Content-Type': 'application/xml',
		},
		data: `<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:fileid/></d:prop></d:propfind>`,
		failOnStatusCode: true,
	})
	const text = await response.text()
	const match = text.match(/<oc:fileid>(\d+)<\/oc:fileid>/)
	await request.dispose()
	if (!match) throw new Error(`Could not find fileid for ${remotePath}`)
	return parseInt(match[1])
}

export async function createDirectory(
	remotePath: string,
	user: User,
): Promise<void> {
	const request = await createAPIRequest()
	await request.fetch(davPath(user.username, remotePath), {
		method: 'MKCOL',
		headers: ocsHeadersWithAuth(user.username, user.password),
		failOnStatusCode: true,
	});
	await request.dispose()
}
