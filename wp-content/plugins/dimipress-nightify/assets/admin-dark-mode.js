(function () {
	'use strict';

	var root = document.documentElement;
	var editorFrameSelector = 'iframe[name="editor-canvas"], .edit-site-visual-editor__editor-canvas iframe';
	var adaptiveObserver;

	/* Midnight remains our design; Dark Reader only adapts unknown admin UI. */
	var midnightTheme = {
		brightness: 100,
		contrast: 100,
		sepia: 0,
		darkSchemeBackgroundColor: '#0D1117',
		darkSchemeTextColor: '#E6EDF3',
		scrollbarColor: '#192231',
		selectionColor: '#4C8FE8',
		styleSystemControls: false
	};

	function darkReaderFixes() {
		return {
			invert: [],
			/* Do not re-process Nightify's authored Midnight stylesheet. */
			ignoreCSSUrl: [ 'dimipress-nightify/assets/admin-dark-mode.css' ],
			/* Swatches and previews represent user content, not UI chrome. */
			ignoreInlineStyle: [
				'.wp-picker-container', '.wp-color-result', '.components-color-picker',
				'.components-circular-option-picker__option', '.components-palette-edit__colors',
				'.block-editor-color-gradient-control', '.block-editor-block-preview__container',
				'.block-editor-block-preview__content', '.block-editor-patterns__list',
				'.editor-styles-wrapper'
			],
			/* Never analyse or invert media in wp-admin. */
			ignoreImageAnalysis: [ '*' ]
		};
	}

	function isDark() {
		return root.getAttribute('data-adm-mode') === 'dark';
	}

	function runInEditorFrame(frame) {
		if (!isDark() || !frame || !frame.contentWindow || !frame.contentDocument) return;
		var doc = frame.contentDocument;
		var win = frame.contentWindow;
		try {
			doc.documentElement.setAttribute('data-adm-mode', 'dark');
			if (win.DarkReader) {
				win.DarkReader.enable(midnightTheme, darkReaderFixes());
				return;
			}
			if (doc.getElementById('dimipress-nightify-darkreader')) return;
			var script = doc.createElement('script');
			script.id = 'dimipress-nightify-darkreader';
			script.src = admDarkMode.darkReaderUrl;
			script.onload = function () {
				if (isDark() && win.DarkReader) win.DarkReader.enable(midnightTheme, darkReaderFixes());
			};
			(doc.head || doc.documentElement).appendChild(script);
		} catch (error) {
			/* Cross-origin frames are intentionally left untouched. */
		}
	}

	function disableEditorFrames() {
		document.querySelectorAll(editorFrameSelector).forEach(function (frame) {
			try {
				if (frame.contentWindow && frame.contentWindow.DarkReader) frame.contentWindow.DarkReader.disable();
			} catch (error) {}
		});
	}

	function syncEditorFrames() {
		document.querySelectorAll(editorFrameSelector).forEach(runInEditorFrame);
	}

	function observeEditorFrames() {
		if (adaptiveObserver) return;
		adaptiveObserver = new MutationObserver(function () {
			if (isDark()) syncEditorFrames();
		});
		adaptiveObserver.observe(document.documentElement, { childList: true, subtree: true });
	}

	function syncAdaptiveLayer() {
		if (!window.DarkReader) return;
		if (isDark()) {
			window.DarkReader.enable(midnightTheme, darkReaderFixes());
			observeEditorFrames();
			syncEditorFrames();
		} else {
			window.DarkReader.disable();
			disableEditorFrames();
		}
	}

	function apply(preference) {
		var resolved = preference;
		if (preference === 'system') {
			resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
		}
		root.setAttribute('data-adm-preference', preference);
		root.setAttribute('data-adm-mode', resolved);
		document.cookie = 'dimipress_nightify_resolved_mode=' + resolved + '; path=/; max-age=31536000; SameSite=Lax';
		syncAdaptiveLayer();
	}

	apply(admDarkMode.mode);
	window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
		if (root.getAttribute('data-adm-preference') === 'system') apply('system');
	});
	document.addEventListener('click', function (event) {
		var link = event.target.closest('#wp-admin-bar-adm-color-mode > a');
		if (!link) return;
		event.preventDefault();
		var cycle = { system: 'dark', dark: 'light', light: 'system' };
		var mode = cycle[root.getAttribute('data-adm-preference') || admDarkMode.mode] || 'system';
		var body = new URLSearchParams({ action: 'adm_set_color_mode', nonce: admDarkMode.nonce, mode: mode });
		fetch(admDarkMode.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body })
			.then(function (response) { return response.json(); })
			.then(function (response) { if (response.success) apply(response.data.mode); });
	});
}());
