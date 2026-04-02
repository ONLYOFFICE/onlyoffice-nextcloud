module.exports = {
	extends: [
		'@nextcloud',
	],
	rules: {
		'import/no-unresolved': [1, { ignore: ['\\.svg\\?raw$'] }],
	},
}
