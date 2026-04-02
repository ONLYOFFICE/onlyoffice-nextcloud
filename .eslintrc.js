module.exports = {
	extends: [
		'@nextcloud',
		'@nextcloud/eslint-config/typescript',
	],
	rules: {
		'import/no-unresolved': [1, { ignore: ['\\.svg\\?raw$'] }],
	},
}
