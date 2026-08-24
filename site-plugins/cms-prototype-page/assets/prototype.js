(() => {
	'use strict';

	const root = document.querySelector('[data-cms-prototype]');
	if (!root) return;

	const status = root.querySelector('[data-cms-prototype-status]');
	const letters = root.querySelectorAll('[data-cms-prototype-letter]');

	letters.forEach((letter) => {
		letter.addEventListener('click', (event) => {
			event.preventDefault();
			const selected = letter.getAttribute('data-cms-prototype-letter');
			window.history.replaceState(null, '', `#${selected}`);
			letters.forEach((item) => item.removeAttribute('aria-current'));
			letter.setAttribute('aria-current', 'page');
			if (status) status.textContent = `${selected.charAt(0).toUpperCase()}${selected.slice(1)} selected`;
		});
	});
})();

