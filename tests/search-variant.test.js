const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

for (const file of ['search-variant.js', 'search-variant.min.js']) {
	let listener;
	const context = {
		window: { wpcc_target_lang: 'zh-hant' },
		document: {
			addEventListener(event, callback, capture) {
				assert.equal(event, 'submit');
				assert.equal(capture, true);
				listener = callback;
			},
			createElement(tag) {
				assert.equal(tag, 'input');
				return {};
			},
		},
	};
	vm.runInNewContext(fs.readFileSync(`${__dirname}/../${file}`, 'utf8'), context);
	function form(search) {
		return {
			inputs: [],
			querySelector(selector) {
				return selector === 'input[name="s"]' ? search : this.inputs.find(input => input.name === 'variant');
			},
			appendChild(input) { this.inputs.push(input); },
		};
	}
	const forms = [form(true), form(true), form(false)];
	for (const target of forms) listener({ target });
	assert.equal(forms[0].inputs[0].value, 'zh-hant');
	assert.equal(forms[1].inputs[0].value, 'zh-hant');
	assert.equal(forms[2].inputs.length, 0);
	context.window.wpcc_target_lang = 'zh-hans';
	listener({ target: forms[0] });
	assert.equal(forms[0].inputs.length, 1);
	assert.equal(forms[0].inputs[0].value, 'zh-hans');
	const dynamic = form(true);
	listener({ target: dynamic });
	assert.equal(dynamic.inputs[0].value, 'zh-hans');
	delete context.window.wpcc_target_lang;
	const original = form(true);
	listener({ target: original });
	assert.equal(original.inputs.length, 0);
}
console.log('Search form checks passed for both JavaScript files.');
