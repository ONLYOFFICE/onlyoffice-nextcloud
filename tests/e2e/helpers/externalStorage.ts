import { createAPIRequest, ocsHeadersWithAuth } from './ocs'
import { admin } from './users'

export async function createGlobalStorage(config: {
	mountPoint: string
	backend: string
	authMechanism: string
	backendOptions: Record<string, string>
	mountOptions?: Record<string, unknown>
}): Promise<number> {
	const request = await createAPIRequest()
	const response = await request.post('/index.php/apps/files_external/globalstorages', {
		headers: {
			...ocsHeadersWithAuth(admin.username, admin.password),
			'Content-Type': 'application/json',
		},
		data: config,
		failOnStatusCode: true,
	})
	const body = await response.json()
	await request.dispose()
	return body.id
}

export async function deleteGlobalStorage(storageId: number): Promise<void> {
	const request = await createAPIRequest()
	await request.delete(`/index.php/apps/files_external/globalstorages/${storageId}`, {
		headers: ocsHeadersWithAuth(admin.username, admin.password),
		failOnStatusCode: true,
	})
	await request.dispose()
}
