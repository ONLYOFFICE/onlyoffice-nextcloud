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

import type { INode } from '@nextcloud/files'

import { getDialogBuilder, getFilePickerBuilder } from '@nextcloud/dialogs'
import { FileType } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { getFileExtension } from './files.ts'

export const CONFLICT_OVERWRITE = 'overwrite'
export const CONFLICT_KEEP_BOTH = 'keepBoth'

export interface SaveAsTarget {
	dir: string
	name: string
}

/**
 * Ask the user for the location to save the file to.
 * Existing files are listed as well, picking one of the same type means saving over it.
 *
 * @param name file name proposed by the editor
 * @param dir folder path to open the picker at
 * @return the picked target, or null if the picker was closed
 */
export function pickSaveAsTarget(name: string, dir?: string): Promise<SaveAsTarget | null> {
	const extension = getFileExtension(name)

	return new Promise((resolve) => {
		const builder = getFilePickerBuilder(t('onlyoffice', 'Save as'))
			.allowDirectories()
			.setCanPick((node: INode) => node.type === FileType.Folder
				|| getFileExtension(node.basename) === extension)
			.addButton({
				label: t('core', 'Choose'),
				callback: (nodes: INode[]) => {
					const node = nodes[0]
					if (!node) {
						resolve(null)
						return
					}

					if (node.type === FileType.Folder) {
						resolve({ dir: node.path, name })
						return
					}

					resolve({ dir: node.dirname, name: node.basename })
				},
				variant: 'primary',
			})

		if (dir) {
			builder.startAt(dir)
		}

		builder.build().pickNodes().catch(() => resolve(null))
	})
}

/**
 * Ask the user what to do with the file already existing in the selected folder
 *
 * @param name name of the existing file
 * @return the picked resolution, or null if the dialog was dismissed
 */
export function askConflictResolution(name: string): Promise<string | null> {
	return new Promise((resolve) => {
		getDialogBuilder(t('onlyoffice', 'File already exists'))
			.setText(t('onlyoffice', 'The file "{name}" already exists in the selected folder.', { name }))
			.setSeverity('warning')
			.addButton({
				label: t('core', 'Cancel'),
				callback: () => resolve(null),
			})
			.addButton({
				label: t('onlyoffice', 'Keep both'),
				callback: () => resolve(CONFLICT_KEEP_BOTH),
			})
			.addButton({
				label: t('onlyoffice', 'Overwrite'),
				callback: () => resolve(CONFLICT_OVERWRITE),
				variant: 'primary',
			})
			.build()
			.show()
			.catch(() => resolve(null))
	})
}
