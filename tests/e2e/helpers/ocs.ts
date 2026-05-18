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