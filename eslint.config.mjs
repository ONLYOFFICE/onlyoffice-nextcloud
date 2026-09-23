import { recommended } from '@nextcloud/eslint-config'
import { defineConfig } from 'eslint/config'

export default defineConfig([
	...recommended,
	{
		rules: {
			'no-console': 'off',
			'no-unused-vars': 'off',
			'jsdoc/reject-function-type': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/require-param-description': 'off',
		},
	},
])