import { APIRequestContext, request } from "@playwright/test";

export function basicAuth(user: string, password: string): string {
	return `Basic ${Buffer.from(`${user}:${password}`).toString('base64')}`;
}

export function ocsHeadersWithAuth(user: string, password: string): { [key: string]: string; } {
	const headers = {
		...ocsHeaders,
		'Authorization': basicAuth(user, password),
	}
	return headers;
}

export const ocsHeaders = {
	'OCS-APIRequest': 'true',
	'Accept': 'application/json',
};

export async function createAPIRequest(): Promise<APIRequestContext> {
	return await request.newContext({ storageState: undefined })
}