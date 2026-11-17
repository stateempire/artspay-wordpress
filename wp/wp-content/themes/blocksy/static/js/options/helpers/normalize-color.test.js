import { normalizeColor } from './normalize-color'

test.each([
	['#ff7000 ', '#ff7000'],
	[' \t#fff\r\n', '#fff'],
	[
		' var(--theme-palette-color-2, #fff) ',
		'var(--theme-palette-color-2, #fff)'
	],
	[' rgb(255 112 0 / 100%) ', 'rgb(255 112 0 / 100%)'],
	[' hsl(10 80% 50%) ', 'hsl(10 80% 50%)'],
	[
		' color-mix(in srgb, red 40%, white) ',
		'color-mix(in srgb, red 40%, white)'
	],
	[' #ff700080 ', 'rgba(255, 112, 0, 0.5)'],
	[' CT_CSS_SKIP_RULE ', 'CT_CSS_SKIP_RULE'],
	[' \t\r\n', '']
])('normalizes %j to %j', (color, expected) => {
	expect(normalizeColor(color)).toBe(expected)
})
