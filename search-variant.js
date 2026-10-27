(function () {
	function preserveVariant(event) {
		var form = event.target;
		if (!window.wpcc_target_lang || !form.querySelector('input[name="s"]')) {
			return;
		}
		var input = form.querySelector('input[name="variant"]');
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'variant';
			form.appendChild(input);
		}
		input.value = window.wpcc_target_lang;
	}

	document.addEventListener('submit', preserveVariant, true);
})();
