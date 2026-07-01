/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const POLL_INTERVAL_MS = 1000
const PICKUP_GRACE_MS = 1 * 60 * 1000 // give up if no worker picks the task up
const MAX_WAIT_MS = 10 * 60 * 1000 // overall ceiling once running

/**
 * Wire the editor's AI connector to the Nextcloud-backed AI endpoints.
 *
 * @param {object} connector the editor AI connector
 */
export function setupAi(connector) {
	const aiBase = generateUrl('/apps/onlyoffice/ai')
	const requests = {}

	const sendEvent = (data) => connector.sendEvent('ai_onExternalFetch', data)

	const externalFetch = async (e) => {
		const { id, type } = e

		if (type === 'abort') {
			const req = requests[id]
			req?.controller.abort()
			if (req?.taskId) {
				fetch(`${aiBase}/task/${req.taskId}/cancel`, {
					method: 'POST',
					headers: { requesttoken: getRequestToken() },
				}).catch(() => {})
			}
			delete requests[id]
			return
		}

		const abortController = new AbortController()
		requests[id] = { controller: abortController }

		try {
			// Schedule the task.
			const startUrl = e.url.replace('[external]', aiBase)
			const startRes = await fetch(startUrl, {
				...e.options,
				signal: abortController.signal,
				headers: { ...e.options.headers },
			})
			if (!startRes.ok) {
				throw new Error(`schedule failed: ${startRes.status}`)
			}
			const { id: taskId } = await startRes.json()
			requests[id].taskId = taskId

			// Poll until non-202.
			const streamFlag = e.streaming ? 1 : 0
			const startedAt = Date.now()
			let pickedUp = false
			let result
			for (;;) {
				result = await fetch(`${aiBase}/task/${taskId}?stream=${streamFlag}`, {
					signal: abortController.signal,
					headers: { requesttoken: getRequestToken() },
				})
				if (result.status !== 202) {
					break
				}
				const { status } = await result.json().catch(() => ({}))
				if (status === 'running') {
					pickedUp = true
				}
				const elapsed = Date.now() - startedAt
				if (!pickedUp && elapsed > PICKUP_GRACE_MS) {
					throw new Error('AI task was not picked up — is a background worker running?')
				}
				if (elapsed > MAX_WAIT_MS) {
					throw new Error('AI task timed out')
				}
				await new Promise((resolve) => setTimeout(resolve, POLL_INTERVAL_MS))
			}

			// Forward the terminal response.
			const headers = Object.fromEntries(result.headers.entries())

			if (!result.ok) {
				sendEvent({ type: 'error', id, error: await result.text() })
				return
			}

			if (e.streaming) {
				sendEvent({ type: 'response', id, status: result.status, headers })
				if (!result.body) {
					throw new Error('Response body is null')
				}
				const reader = result.body.getReader()
				const decoder = new TextDecoder()
				let done = false
				while (!done) {
					const { value, done: readDone } = await reader.read()
					done = readDone
					if (value) {
						sendEvent({ type: 'chunk', id, chunk: decoder.decode(value) })
					}
				}
				sendEvent({ type: 'end', id })
			} else {
				sendEvent({
					type: 'response',
					id,
					status: result.status,
					headers,
					body: await result.text(),
				})
			}
		} catch (err) {
			sendEvent({ type: 'error', id, error: String(err) })
		} finally {
			delete requests[id]
		}
	}

	const sendProviders = async () => {
		let config
		try {
			const res = await fetch(`${aiBase}/config`, {
				headers: { requesttoken: getRequestToken() },
			})
			if (!res.ok) {
				return
			}
			config = await res.json()
		} catch (err) {
			return
		}

		if (!config?.models?.length) {
			return
		}

		connector.sendEvent('ai_onCustomProviders', [{ name: config.provider }])
		connector.sendEvent('ai_onCustomInit', {
			settingsLock: undefined,
			actionsOverride: true,
			actions: {
				Chat: {
					model: config.models[0].name,
				},
				Summarization: {
					model: config.models[0].name,
				},
				Translation: {
					model: config.models[0].name,
				},
				TextAnalyze: {
					model: config.models[0].name,
				},
			},
			models: config.models.map((model) => ({
				capabilities: model.capabilities,
				provider: config.provider,
				name: model.name,
				id: model.id,
			})),
		})
	}

	connector.executeMethod('AI', [{ type: 'Actions' }], (data) => {
		if (data && typeof data === 'object' && 'error' in data && data.error) {
			connector.attachEvent('ai_onInit', sendProviders)
		} else {
			sendProviders()
		}
	})

	connector.attachEvent('ai_onExternalFetch', externalFetch)
}
