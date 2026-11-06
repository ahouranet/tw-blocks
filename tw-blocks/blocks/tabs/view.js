/**
 * Minimal (<1 KB) Vanilla JS WAI-ARIA controller for `tw-blocks/tabs`.
 * Loaded conditionally ONLY on pages that contain `tw-blocks/tabs` via Block API v3 `viewScript`.
 * Supports RTL and LTR keyboard arrow navigation (`ArrowRight`, `ArrowLeft`, `Home`, `End`).
 */
(function () {
	'use strict';

	function activateTab(container, buttons, panels, nextIdx, focusBtn) {
		buttons.forEach(function (btn, idx) {
			var selected = idx === nextIdx;
			btn.setAttribute('aria-selected', selected ? 'true' : 'false');
			btn.setAttribute('tabindex', selected ? '0' : '-1');
			btn.className = selected
				? 'px-4 py-2 rounded-lg text-sm font-semibold bg-primary text-white transition duration-200'
				: 'px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition duration-200';
			if (selected && focusBtn) {
				btn.focus();
			}
		});
		panels.forEach(function (panel, idx) {
			if (idx === nextIdx) {
				panel.classList.remove('hidden');
			} else {
				panel.classList.add('hidden');
			}
		});
	}

	function initTabs() {
		document.querySelectorAll('[data-twb-tabs="true"]').forEach(function (container) {
			var buttons = Array.prototype.slice.call(container.querySelectorAll('[role="tab"]'));
			var panels = Array.prototype.slice.call(container.querySelectorAll('[role="tabpanel"]'));
			if (!buttons.length) return;

			var isRtl = (container.getAttribute('dir') || document.documentElement.dir || 'rtl').toLowerCase() === 'rtl';

			buttons.forEach(function (btn, idx) {
				btn.addEventListener('click', function () {
					activateTab(container, buttons, panels, idx, false);
				});

				btn.addEventListener('keydown', function (e) {
					var total = buttons.length;
					var target = null;
					if (e.key === 'ArrowRight') {
						target = isRtl ? (idx - 1 + total) % total : (idx + 1) % total;
					} else if (e.key === 'ArrowLeft') {
						target = isRtl ? (idx + 1) % total : (idx - 1 + total) % total;
					} else if (e.key === 'Home') {
						target = 0;
					} else if (e.key === 'End') {
						target = total - 1;
					}
					if (target !== null) {
						e.preventDefault();
						activateTab(container, buttons, panels, target, true);
					}
				});
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTabs);
	} else {
		initTabs();
	}
})();
