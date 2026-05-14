<!--
  Copyright (C) Ascensio System SIA, 2009-2026

  This program is a free software product. You can redistribute it and/or
  modify it under the terms of the GNU Affero General Public License (AGPL)
  version 3 as published by the Free Software Foundation, together with the
  additional terms provided in the LICENSE file.

  This program is distributed WITHOUT ANY WARRANTY; without even the implied
  warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
  details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html

  You can contact Ascensio System SIA by email at info@onlyoffice.com
  or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
  LV-1050, Latvia, European Union.

  The interactive user interfaces in modified versions of the Program
  are required to display Appropriate Legal Notices in accordance with
  Section 5 of the GNU AGPL version 3.

  No trademark rights are granted under this License.

  All non-code elements of the Product, including illustrations,
  icon sets, and technical writing content, are licensed under the
  Creative Commons Attribution-ShareAlike 4.0 International License:
  https://creativecommons.org/licenses/by-sa/4.0/legalcode

  This license applies only to such non-code elements and does not
  modify or replace the licensing terms applicable to the Program's
  source code, which remains licensed under the GNU Affero General
  Public License v3.

  SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { saveAddressSettings } from '../../services/SettingsService'
import AppDescription from './AppDescription.vue'
import EmptyJwtInfoDialog from '../EmptyJwtInfoDialog.vue'

const props = defineProps<{
	documentserver: string
	documentserverInternal: string
	storageUrl: string
	verifyPeerOff: boolean
	secret: string
	jwtHeader: string
	demo: { enabled: boolean, available: boolean }
}>()

const emit = defineEmits<{
	(e: 'address-saved', payload: { showSections: boolean }): void
}>()

const url = ref(props.documentserver ?? '')
const internalUrl = ref(props.documentserverInternal ?? '')
const storageUrl = ref(props.storageUrl ?? '')
const verifyPeerOff = ref(props.verifyPeerOff)
const secret = ref(props.secret ?? '')
const jwtHeader = ref(props.jwtHeader ?? '')
const demoEnabled = ref(props.demo.enabled)
const showAdvanced = ref(
	!!(props.documentserverInternal || props.storageUrl || props.jwtHeader),
)
const saving = ref(false)
const showSecret = ref(false)
const currentServer = window.location.origin + '/'

/**
 * Persists the Document Server address and JWT settings to the backend.
 * Clears advanced fields (internal URL, storage URL, secret) when the server URL is removed.
 */
async function save() {
	if (!url.value && !demoEnabled.value) {
		// Clearing the server — reset advanced fields too
		internalUrl.value = ''
		storageUrl.value = ''
		secret.value = ''
		jwtHeader.value = ''
	}

	saving.value = true
	try {
		const response = await saveAddressSettings({
			documentserver: url.value.trim(),
			documentserverInternal: internalUrl.value.trim(),
			storageUrl: storageUrl.value.trim(),
			verifyPeerOff: verifyPeerOff.value,
			secret: secret.value.trim(),
			jwtHeader: jwtHeader.value.trim(),
			demo: demoEnabled.value,
		})

		if (response.documentserver != null || demoEnabled.value) {
			url.value = response.documentserver ?? ''
			internalUrl.value = response.documentserverInternal ?? ''
			storageUrl.value = response.storageUrl ?? ''
			secret.value = response.secret ?? ''
			jwtHeader.value = response.jwtHeader ?? ''

			const versionMessage = response.version
				? (' (' + t('onlyoffice', 'version') + ' ' + response.version + ')')
				: ''

			if (response.error) {
				showError(t('onlyoffice', 'Error when trying to connect') + ' (' + response.error + ')' + versionMessage)
				emit('address-saved', { showSections: false })
			} else {
				const hasSecret = !!response.secret
				if (response.documentserver && !hasSecret) {
					spawnDialog(EmptyJwtInfoDialog)
				} else {
					showSuccess(t('onlyoffice', 'Server settings have been successfully updated') + versionMessage)
				}
				emit('address-saved', { showSections: !!(url.value || demoEnabled.value) })
			}
		} else {
			emit('address-saved', { showSections: false })
		}
	} catch {
		showError(t('onlyoffice', 'Error when trying to connect'))
		emit('address-saved', { showSections: false })
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<div class="section section-onlyoffice section-onlyoffice-addr">
		<AppDescription />

		<h2>{{ t('onlyoffice', 'Server settings') }}</h2>
		<p class="settings-description">
			{{ t('onlyoffice', 'ONLYOFFICE Docs Location specifies the address of the server with the document services installed. Please change the \'\<documentserver\>\' for the server address in the below line.', {}, { sanitize: false }) }}
		</p>

		<p>{{ t('onlyoffice', 'ONLYOFFICE Docs address') }}</p>
		<p>
			<input id="onlyoffice-url"
				v-model="url"
				type="text"
				placeholder="https://<documentserver>/"
				:disabled="demoEnabled"
				@keypress.enter="save">
		</p>

		<p>
			<input id="onlyoffice-verify-peer-off"
				v-model="verifyPeerOff"
				type="checkbox"
				class="checkbox"
				:disabled="demoEnabled">
			<label for="onlyoffice-verify-peer-off">{{ t('onlyoffice', 'Disable certificate verification (insecure)') }}</label>
		</p>

		<p>{{ t('onlyoffice', 'Secret key (leave blank to disable)') }}</p>
		<p class="groupbottom">
			<input id="onlyoffice-secret"
				v-model="secret"
				:type="showSecret ? 'text' : 'password'"
				placeholder="secret"
				:disabled="demoEnabled"
				@keypress.enter="save">
			<input id="personal-show"
				v-model="showSecret"
				type="checkbox"
				class="hidden-visually"
				name="show">
			<label id="onlyoffice-secret-show" for="personal-show" class="personal-show-label" />
		</p>

		<p class="onlyoffice-adv">
			<a @click="showAdvanced = !showAdvanced">
				{{ t('onlyoffice', 'Advanced server settings') }}
				<span :class="showAdvanced ? 'icon-triangle-n' : 'icon-triangle-s'" class="icon" />
			</a>
		</p>

		<div v-show="showAdvanced">
			<p>{{ t('onlyoffice', 'Authorization header (leave blank to use default header)') }}</p>
			<p>
				<input id="onlyoffice-jwt-header"
					v-model="jwtHeader"
					type="text"
					placeholder="Authorization"
					:disabled="demoEnabled"
					@keypress.enter="save">
			</p>

			<p>{{ t('onlyoffice', 'ONLYOFFICE Docs address for internal requests from the server') }}</p>
			<p>
				<input id="onlyoffice-internal-url"
					v-model="internalUrl"
					type="text"
					placeholder="https://<documentserver>/"
					:disabled="demoEnabled"
					@keypress.enter="save">
			</p>

			<p>{{ t('onlyoffice', 'Server address for internal requests from ONLYOFFICE Docs') }}</p>
			<p>
				<input id="onlyoffice-storage-url"
					v-model="storageUrl"
					type="text"
					:placeholder="currentServer"
					:disabled="demoEnabled"
					@keypress.enter="save">
			</p>
		</div>

		<br>

		<div class="onlyoffice-addr-bottom">
			<NcButton id="onlyoffice-server-save"
				:disabled="saving"
				variant="primary"
				@click="save">
				{{ t('onlyoffice', 'Save') }}
			</NcButton>

			<div>
				<input id="onlyoffice-demo"
					v-model="demoEnabled"
					type="checkbox"
					class="checkbox"
					:disabled="!demo.available">
				<label for="onlyoffice-demo">{{ t('onlyoffice', 'Connect to demo ONLYOFFICE Docs server') }}</label>
				<br>
				<em v-if="demo.available">{{ t('onlyoffice', 'This is a public test server, please do not use it for private sensitive data. The server will be available during a 30-day period.') }}</em>
				<em v-else>{{ t('onlyoffice', 'The 30-day test period is over, you can no longer connect to demo ONLYOFFICE Docs server.') }}</em>
			</div>
		</div>
	</div>
</template>

<style scoped>
.onlyoffice-addr-bottom {
	display: flex;
	align-items: flex-start;
	gap: 30px;
}

.onlyoffice-addr-bottom button {
	flex: none;
}

.onlyoffice-adv {
	padding-top: 16px;
}

.onlyoffice-adv a {
	cursor: pointer;
	text-decoration: none;
}

.onlyoffice-adv .icon {
	display: inline-block;
	margin-bottom: -3px;
}

#onlyoffice-secret-show {
    left: 205px;
}
</style>
