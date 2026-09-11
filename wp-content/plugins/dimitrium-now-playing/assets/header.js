(() => {
	'use strict';

	const imageGlowFigures = document.body.matches('.page-id-122, .page-id-123, .page-id-142, .page-id-143, .page-id-434, .page-id-436')
		? Array.from(document.querySelectorAll('.wp-block-post-content .wp-block-image'))
		: [];
	document.querySelectorAll('.unendlich-album-cover').forEach((figure) => {
		if (!imageGlowFigures.includes(figure)) imageGlowFigures.push(figure);
	});
	const philosophyHeroFigure = document.querySelector('body:is(.page-id-580, .page-id-579) .wp-block-post-content > .philosophy-intro-hero > .wp-block-columns > .wp-block-column:last-child > .wp-block-image');
	if (philosophyHeroFigure && !imageGlowFigures.includes(philosophyHeroFigure)) imageGlowFigures.push(philosophyHeroFigure);
	imageGlowFigures.forEach((figure) => {
		const image = figure.querySelector('img');
		if (!image) return;
		const updateGlow = () => {
			// Wait for source selection: image.src would force the PNG fallback even
			// when this page supplies a responsive WebP <picture> source.
			if (image.currentSrc) figure.style.setProperty('--dimitrium-image-glow', `url(${JSON.stringify(image.currentSrc)})`);
		};
		if (image.complete) updateGlow();
		image.addEventListener('load', updateGlow, { once: true });
	});

	const albumPaths = [ '/en/musique/unendlich/', '/sr/muzika/unendlich/' ];
	const removeAlbumSubpages = (root) => {
		root.querySelectorAll('a[href]').forEach((link) => {
			if (!albumPaths.includes(new URL(link.href, window.location.origin).pathname)) return;
			const item = link.closest('.wp-block-navigation-item');
			const submenu = item?.parentElement;
			item?.remove();
			if (!submenu?.classList.contains('wp-block-navigation__submenu-container') || submenu.querySelector(':scope > .wp-block-navigation-item')) return;
			const parent = submenu.parentElement;
			submenu.remove();
			parent?.querySelector(':scope > .wp-block-navigation__submenu-icon')?.remove();
			parent?.classList.remove('has-child');
		});
	};
	document.querySelectorAll('header.wp-block-template-part .wp-block-page-list').forEach(removeAlbumSubpages);

	const albumLinkLabel = document.documentElement.lang?.startsWith('sr') ? 'Otvori album Unendlich' : 'Open Unendlich album';
	document.querySelectorAll('.unendlich-album-cover > a').forEach((link) => {
		link.setAttribute('aria-label', albumLinkLabel);
		if (link.querySelector('.dimitrium-album-cover__link-icon')) return;
		const icon = document.createElement('span');
		icon.className = 'dimitrium-album-cover__link-icon';
		icon.setAttribute('aria-hidden', 'true');
		// Magicoon UI Icons v1.3 — Filled / arrow-up-right.
		icon.innerHTML = '<svg viewBox="0 0 24 24" focusable="false"><path d="M20,5V15a1,1,0,0,1-2,0V7.414L6.707,18.707a1,1,0,0,1-1.414-1.414L16.586,6H9A1,1,0,0,1,9,4H19a1.01,1.01,0,0,1,.382.077A1,1,0,0,1,20,5Z"/></svg>';
		link.append(icon);
	});

	document.querySelectorAll('[data-news-marquee]').forEach((marquee) => {
		const track = marquee.querySelector('.dimitrium-news-marquee__track');
		if (!track || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
		const sets = Array.from(track.querySelectorAll('.dimitrium-news-marquee__set'));
		if (sets.length === 2) {
			const viewport = marquee.querySelector('.dimitrium-news-marquee__viewport');
			const sourceLinks = Array.from(sets[0].children).map((link) => link.cloneNode(true));
			let repeats = 0;
			track.style.animation = 'none';
			while (sets[0].scrollWidth < (viewport ? viewport.clientWidth : marquee.clientWidth) * 1.5 && sourceLinks.length && repeats < 12) {
				sourceLinks.forEach((sourceLink) => {
					sets.forEach((set, setIndex) => {
						const clone = sourceLink.cloneNode(true);
						if (setIndex === 1) clone.tabIndex = -1;
						set.append(clone);
					});
				});
				repeats += 1;
			}
			track.style.setProperty('--dimitrium-news-distance', `${sets[0].getBoundingClientRect().width}px`);
			track.getBoundingClientRect();
			track.style.animation = '';
		}
		let frame = 0;
		let targetRate = 1;
		const easeRate = () => {
			const animation = track.getAnimations()[0];
			if (!animation) {
				frame = window.requestAnimationFrame(easeRate);
				return;
			}
			const current = Number(animation.playbackRate) || 1;
			const next = current + (targetRate - current) * 0.09;
			animation.updatePlaybackRate(Math.abs(next - targetRate) < 0.01 ? targetRate : next);
			if (Math.abs(next - targetRate) >= 0.01) frame = window.requestAnimationFrame(easeRate);
		};
		const setRate = (rate) => {
			targetRate = rate;
			window.cancelAnimationFrame(frame);
			frame = window.requestAnimationFrame(easeRate);
		};
		marquee.addEventListener('pointerenter', () => setRate(0.18));
		marquee.addEventListener('pointerleave', () => setRate(1));
		marquee.addEventListener('focusin', () => setRate(0.18));
		marquee.addEventListener('focusout', (event) => {
			if (!marquee.contains(event.relatedTarget)) setRate(1);
		});
	});

	const mobileNavigation = document.querySelector(
		'header.wp-block-template-part > .main-header:first-child .wp-block-navigation'
	);
	const menuButton = mobileNavigation?.querySelector('.wp-block-navigation__responsive-container-open');
	// WordPress emits a page-list block on some templates and a Navigation block
	// container on others. Either is a valid source for our mobile drawer.
	const sourcePageList = mobileNavigation?.querySelector('.wp-block-page-list, .wp-block-navigation__container');
	if (mobileNavigation && menuButton && sourcePageList) {
		const drawer = document.createElement('aside');
		drawer.className = 'dimitrium-mobile-drawer';
		drawer.setAttribute('aria-hidden', 'true');
		drawer.innerHTML = '<strong class="dimitrium-mobile-drawer__title">Navigation</strong><nav class="dimitrium-mobile-drawer__links" aria-label="Mobile navigation"></nav><p class="dimitrium-mobile-drawer__credit">Created with love by Aleksa Dimitrijević in collaboration with Nala the cat. ♥</p>';
		const clonedList = sourcePageList.cloneNode(true);
		clonedList.querySelectorAll('a[href$="/en/home/"], a[href$="/sr/pocetna/"], a[href$="/en/noir-license/"], a[href$="/sr/noir-licenca/"], a[href$="/en/musique/unendlich/"], a[href$="/sr/muzika/unendlich/"]').forEach((link) => link.closest('li')?.remove());
		drawer.querySelector('.dimitrium-mobile-drawer__links').append(clonedList);
		document.body.append(drawer);

		let closingTimer = 0;
		const isOpen = () => document.body.classList.contains('dimitrium-menu-open');
		const openMenu = () => {
			window.clearTimeout(closingTimer);
			document.body.classList.remove('dimitrium-menu-closing');
			document.body.classList.add('dimitrium-menu-open');
			drawer.setAttribute('aria-hidden', 'false');
			menuButton.setAttribute('aria-label', 'Close menu');
			menuButton.setAttribute('aria-expanded', 'true');
		};
		const closeMenu = () => {
			if (!isOpen() || document.body.classList.contains('dimitrium-menu-closing')) return;
			document.body.classList.add('dimitrium-menu-closing');
			document.body.classList.remove('dimitrium-menu-open');
			menuButton.setAttribute('aria-label', 'Open menu');
			menuButton.setAttribute('aria-expanded', 'false');
			closingTimer = window.setTimeout(() => {
				document.body.classList.remove('dimitrium-menu-closing');
				drawer.setAttribute('aria-hidden', 'true');
			}, 470);
		};
		menuButton.addEventListener('click', (event) => {
			if (!window.matchMedia('(max-width: 700px)').matches) return;
			event.preventDefault();
			event.stopImmediatePropagation();
			isOpen() ? closeMenu() : openMenu();
		}, true);
		document.addEventListener('pointerdown', (event) => {
			if (!isOpen() || drawer.contains(event.target) || menuButton.contains(event.target)) return;
			event.preventDefault();
			closeMenu();
		}, true);
		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && isOpen()) closeMenu();
		});
		document.addEventListener('touchmove', (event) => {
			if (isOpen() && !drawer.contains(event.target)) event.preventDefault();
		}, { passive: false });
	}
})();
