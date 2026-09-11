(() => {
	'use strict';

	const bar = document.querySelector('[data-dimitrium-now-playing]');
	const full = document.querySelector('[data-dimitrium-full-player]');
	const reward = document.querySelector('[data-dimitrium-listening-reward]');
	const playlist = document.querySelector('.wp-block-playlist');
	if (!bar || !full || !reward || !playlist) return;
	const rewardClose = reward.querySelector('[data-reward-close]');
	const rewardTrigger = document.querySelector('[data-reward-trigger]');
	const rewardImage = reward.querySelector('[data-dimitrium-reward-image]');
	const rewardStorageKey = 'dimitrium-listening-reward-unlocked-v2';
	const rewardCookieName = 'dimitrium_listening_reward_unlocked';
	let rewardUnlockedThisPage = false;
	const hasRewardCookie = () => document.cookie.split('; ').some((cookie) => cookie === `${rewardCookieName}=1`);
	const hasUnlockedReward = () => {
		if (rewardUnlockedThisPage || hasRewardCookie()) return true;
		try {
			return window.localStorage.getItem(rewardStorageKey) === '1';
		} catch (error) {
			return false;
		}
	};
	const persistRewardUnlock = () => {
		rewardUnlockedThisPage = true;
		try {
			window.localStorage.setItem(rewardStorageKey, '1');
		} catch (error) {
			// The cookie below is the fallback for private browsing or strict storage settings.
		}
		// Keep the unlock for a year without attaching it to analytics or a user account.
		document.cookie = `${rewardCookieName}=1; Max-Age=31536000; Path=/; SameSite=Lax; Secure`;
	};
	const revealRewardTrigger = () => {
		if (rewardTrigger) rewardTrigger.hidden = false;
	};
	const playbackRevealSections = Array.from(document.querySelectorAll('.dimitrium-playback-reveal'));
	let hasPlaybackStarted = false;
	const unlockPlaybackSections = () => {
		hasPlaybackStarted = true;
		playbackRevealSections.forEach((section) => section.classList.add('is-unlocked'));
	};
	const lockPlaybackSectionsAfterPause = () => {
		if (!hasPlaybackStarted) return;
		const pauseCopy = document.documentElement.lang?.startsWith('sr')
			? 'Znam da si prestao da slušaš moju pesmu. Odmah je ponovo pusti!'
			: 'I know you stopped listening to my song. Play it right now!';
		playbackRevealSections.forEach((section) => {
			section.querySelector('.dimitrium-playback-reveal__prompt')?.replaceChildren(pauseCopy);
			section.classList.remove('is-unlocked');
		});
	};
	const homeAlbumTitle = Array.from(document.querySelectorAll('.home-hero-section h1, .home-hero-section h2')).find((element) => element.textContent.trim().toLowerCase() === 'unendlich');
	if (homeAlbumTitle) {
		const titleText = homeAlbumTitle.textContent.trim();
		const typedText = document.createElement('span');
		const cursor = document.createElement('span');
		homeAlbumTitle.classList.add('dimitrium-album-logo');
		typedText.className = 'dimitrium-album-logo__text';
		cursor.className = 'dimitrium-album-logo__cursor';
		cursor.textContent = '_';
		homeAlbumTitle.replaceChildren(typedText, cursor);
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			typedText.textContent = titleText;
			homeAlbumTitle.classList.add('finished');
		} else {
			let titleIndex = 0;
			const typeNextTitleCharacter = () => {
				if (titleIndex < titleText.length) {
					typedText.textContent += titleText.charAt(titleIndex);
					titleIndex += 1;
				window.setTimeout(typeNextTitleCharacter, 110);
				} else {
					window.setTimeout(() => homeAlbumTitle.classList.add('finished'), 500);
				}
			};
			window.setTimeout(typeNextTitleCharacter, 300);
		}
	}
	const overlayBackground = document.createElement('div');
	overlayBackground.className = 'dimitrium-full-player__background';
	overlayBackground.setAttribute('aria-hidden', 'true');
	full.prepend(overlayBackground);

	const artwork = bar.querySelector('.dimitrium-now-playing__artwork');
	const frost = bar.querySelector('.dimitrium-now-playing__frost');
	const title = bar.querySelector('.dimitrium-now-playing__title');
	const artist = bar.querySelector('.dimitrium-now-playing__artist');
	const current = bar.querySelector('.dimitrium-now-playing__current');
	const duration = bar.querySelector('.dimitrium-now-playing__duration');
	const seek = bar.querySelector('.dimitrium-now-playing__seek');
	const toggle = bar.querySelector('[data-action="toggle"]');
	const previous = bar.querySelector('[data-action="previous"]');
	const next = bar.querySelector('[data-action="next"]');
	const expand = bar.querySelector('[data-action="expand"]');
	const fullArtwork = full.querySelector('.dimitrium-full-player__artwork');
	const fullArtworkWrap = full.querySelector('.dimitrium-full-player__artwork-wrap');
	const fullAlbumTitle = full.querySelector('.dimitrium-full-player__album-title');
	const fullAlbumArtist = full.querySelector('.dimitrium-full-player__album-artist');
	const fullAlbumBack = full.querySelector('.dimitrium-full-player__album-back');
	const fullAlbumBackArt = full.querySelector('.dimitrium-full-player__album-back-art');
	const fullAlbumTracks = full.querySelector('.dimitrium-full-player__album-tracks');
	const fullAlbumDuration = full.querySelector('.dimitrium-full-player__album-duration');
	const fullAlbumWaveform = full.querySelector('.dimitrium-full-player__album-waveform');
	const fullTitle = full.querySelector('.dimitrium-full-player__title');
	const fullArtist = full.querySelector('.dimitrium-full-player__artist');
	const fullCurrent = full.querySelector('.dimitrium-full-player__current');
	const fullDuration = full.querySelector('.dimitrium-full-player__duration');
	const fullSeek = full.querySelector('.dimitrium-full-player__seek');
	const fullTimeline = full.querySelector('.dimitrium-full-player__timeline');
	const fullToggle = full.querySelector('[data-full-action="toggle"]');
	const fullPrevious = full.querySelector('[data-full-action="previous"]');
	const fullNext = full.querySelector('[data-full-action="next"]');
	const close = full.querySelector('[data-full-action="close"]');
	const fullDownload = full.querySelector('[data-full-download]');
	const fullButtons = full.querySelector('.dimitrium-full-player__buttons');
	const trackList = playlist.querySelector('.wp-block-playlist__tracklist');
	const isAlbumPagePlaylist = playlist.classList.contains('dimitrium-album-page-playlist');
	const albumPage = playlist.closest('.dimitrium-album-page');
	const albumPageArtwork = albumPage?.querySelector('.dimitrium-album-page__front-art');
	const albumPageBackArt = albumPage?.querySelector('.dimitrium-album-page__back-art');
	const albumPageTitle = albumPage?.querySelector('.dimitrium-album-page__title');
	const albumPageArtist = albumPage?.querySelector('.dimitrium-album-page__artist');
	const albumPageMeta = albumPage?.querySelector('.dimitrium-album-page__meta');
	const playlistCoverButton = document.createElement('button');
	const playlistCoverImage = document.createElement('img');
	const playlistCoverHint = document.createElement('span');
	const albumOverview = document.createElement('div');
	const albumTitle = document.createElement('strong');
	const albumArtist = document.createElement('span');
	const albumMeta = document.createElement('span');
	const desktopPlaylist = window.matchMedia('(min-width: 701px)');
	playlistCoverButton.type = 'button';
	playlistCoverButton.className = 'dimitrium-playlist-cover';
	playlistCoverButton.setAttribute('aria-expanded', 'false');
	playlistCoverButton.setAttribute('aria-label', document.documentElement.lang?.startsWith('sr') ? 'Prikaži listu pesama' : 'Show track list');
	playlistCoverImage.className = 'dimitrium-playlist-cover__image';
	playlistCoverImage.alt = '';
	playlistCoverHint.className = 'dimitrium-playlist-cover__hint';
	playlistCoverHint.setAttribute('aria-hidden', 'true');
	playlistCoverHint.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12,17a1,1,0,0,1-.707-.293l-7-7A1,1,0,0,1,5.707,8.293L12,14.586l6.293-6.293a1,1,0,0,1,1.414,1.414l-7,7A1,1,0,0,1,12,17Z"/></svg>';
	playlistCoverButton.append(playlistCoverImage, playlistCoverHint);
	if (!isAlbumPagePlaylist) trackList?.before(playlistCoverButton);
	albumOverview.className = 'dimitrium-playlist-album';
	albumTitle.className = 'dimitrium-playlist-album__title';
	albumArtist.className = 'dimitrium-playlist-album__artist';
	albumMeta.className = 'dimitrium-playlist-album__meta';
	albumOverview.append(albumTitle, albumArtist, albumMeta);
	if (!isAlbumPagePlaylist) {
		trackList?.before(albumOverview);
		albumOverview.hidden = true;
		if (trackList) trackList.hidden = true;
	}
	const controlRow = document.createElement('div');
	controlRow.className = 'dimitrium-full-player__control-row';
	fullButtons.before(controlRow);
	controlRow.append(fullDownload, fullButtons, close);
	const seeks = [seek, fullSeek];
	let player = null;
	let seeking = false;
	let listeningRun = { source: '', startedAtBeginning: false, seeked: false };
	let rewardFocusBeforeOpen = null;
	let completionGuardUntil = 0;
	let waveformProgress = 0;
	let waveformBuffered = 0;
	let activeSeekColors = ['#f7f8f8', '#f7f8f8'];
	let paletteRequest = 0;
	const paletteCache = new Map();
	let focusBeforeFull = null;
	let fullTransitionTimer = null;
	let openingAnimation = null;
	let openingAuxAnimations = [];
	let openingClone = null;
	let openingTimer = null;
	let closingBackgroundAnimation = null;
	let closingElementAnimations = [];
	let closingTextClones = [];
	let frostFrameOne = null;
	let frostFrameTwo = null;
	let frostAnimation = null;
	let frostStartTimer = null;
	const connectedAudio = new WeakSet();
	const audioSource = (audio) => audio?.currentSrc || audio?.src || '';
	const syncListeningRun = (audio) => {
		const source = audioSource(audio);
		if (source !== listeningRun.source) {
			listeningRun = { source, startedAtBeginning: false, seeked: false };
		}
		return listeningRun;
	};
	const markPlaybackStart = (audio) => {
		const run = syncListeningRun(audio);
		if (!run.startedAtBeginning) run.startedAtBeginning = (Number(audio?.currentTime) || 0) <= 1.25;
	};
	const markSeeked = () => {
		if (listeningRun.startedAtBeginning) listeningRun.seeked = true;
	};
	const closeReward = () => {
		if (reward.hidden) return;
		reward.hidden = true;
		document.documentElement.classList.remove('has-dimitrium-listening-reward');
		document.body.classList.remove('has-dimitrium-listening-reward');
		rewardFocusBeforeOpen?.focus?.({ preventScroll: true });
	};
	const openReward = () => {
		if (rewardImage && !rewardImage.getAttribute('src')) {
			rewardImage.src = rewardImage.dataset.src || '';
		}
		rewardFocusBeforeOpen = document.activeElement;
		reward.hidden = false;
		document.documentElement.classList.add('has-dimitrium-listening-reward');
		document.body.classList.add('has-dimitrium-listening-reward');
		rewardClose?.focus({ preventScroll: true });
	};
	const positionRewardTrigger = () => {
		if (!rewardTrigger) return;
		const languageSwitcher = document.querySelector('ul.dimitrium-language-switcher');
		if (languageSwitcher) rewardTrigger.style.setProperty('--dimitrium-language-switcher-width', `${languageSwitcher.getBoundingClientRect().width}px`);
	};

	const formatTime = (seconds) => {
		if (!Number.isFinite(seconds) || seconds < 0) return '00:00';
		const whole = Math.floor(seconds);
		return `${String(Math.floor(whole / 60)).padStart(2, '0')}:${String(whole % 60).padStart(2, '0')}`;
	};

	const applyArtworkPalette = (imageUrl) => {
		const request = ++paletteRequest;
		const apply = (colors) => {
			if (request !== paletteRequest) return;
			activeSeekColors = colors;
			[bar, full, ...seeks].forEach((element) => {
				element.style.setProperty('--seek-color-start', colors[0]);
				element.style.setProperty('--seek-color-end', colors[1]);
			});
			albumPage?.style.setProperty('--dimitrium-album-glow', colors[0]);
			drawAlbumWaveform();
		};
		if (!imageUrl) return apply(['#f7f8f8', '#f7f8f8']);
		if (paletteCache.has(imageUrl)) return apply(paletteCache.get(imageUrl));

		const source = new Image();
		source.crossOrigin = 'anonymous';
		source.onload = () => {
			try {
				const canvas = document.createElement('canvas');
				canvas.width = 40;
				canvas.height = 40;
				const context = canvas.getContext('2d', { willReadFrequently: true });
				context.drawImage(source, 0, 0, 40, 40);
				const pixels = context.getImageData(0, 0, 40, 40).data;
				const buckets = new Map();
				for (let index = 0; index < pixels.length; index += 16) {
					const r = pixels[index];
					const g = pixels[index + 1];
					const b = pixels[index + 2];
					if (pixels[index + 3] < 160) continue;
					const max = Math.max(r, g, b);
					const min = Math.min(r, g, b);
					const light = (max + min) / 2;
					if (light < 24 || light > 238) continue;
					const key = `${Math.round(r / 32)},${Math.round(g / 32)},${Math.round(b / 32)}`;
					const entry = buckets.get(key) || { r: 0, g: 0, b: 0, count: 0, score: 0 };
					entry.r += r;
					entry.g += g;
					entry.b += b;
					entry.count += 1;
					entry.score += 1 + ((max - min) / 180);
					buckets.set(key, entry);
				}
				const candidates = [...buckets.values()].sort((a, b) => b.score - a.score).map((entry) => [entry.r / entry.count, entry.g / entry.count, entry.b / entry.count]);
				const first = candidates[0] || [247, 248, 248];
				const second = candidates.find((color) => Math.hypot(color[0] - first[0], color[1] - first[1], color[2] - first[2]) > 72) || candidates[1] || first;
				const brighten = (color) => {
					const peak = Math.max(...color);
					const scale = peak < 150 ? 150 / Math.max(1, peak) : 1;
					return `rgb(${color.map((channel) => Math.min(255, Math.round(channel * scale))).join(' ')})`;
				};
				const colors = [brighten(first), brighten(second)];
				paletteCache.set(imageUrl, colors);
				apply(colors);
			} catch (error) {
				apply(['#f7f8f8', '#f7f8f8']);
			}
		};
		source.onerror = () => apply(['#f7f8f8', '#f7f8f8']);
		source.src = imageUrl;
	};

	const setSeekProgress = (value) => {
		const normalized = Math.max(0, Math.min(1000, Number(value) || 0));
		waveformProgress = normalized / 1000;
		seeks.forEach((element) => {
			element.value = String(normalized);
			element.style.setProperty('--seek-progress', `${normalized / 10}%`);
		});
		drawAlbumWaveform();
	};

	const setBufferedProgress = (audio) => {
		const total = Number(audio?.duration) || 0;
		let bufferedEnd = 0;
		if (total > 0 && audio?.buffered?.length) bufferedEnd = audio.buffered.end(audio.buffered.length - 1);
		const percent = total > 0 ? Math.max(0, Math.min(100, (bufferedEnd / total) * 100)) : 0;
		waveformBuffered = percent / 100;
		seeks.forEach((element) => element.style.setProperty('--buffered-progress', `${percent}%`));
		drawAlbumWaveform();
	};

	const drawAlbumWaveform = () => {
		if (!fullAlbumWaveform || full.hidden || full.classList.contains('is-opening-motion')) return;
		const peaks = Array.isArray(player?.waveformData) ? player.waveformData : [];
		const width = fullAlbumWaveform.clientWidth;
		const height = fullAlbumWaveform.clientHeight;
		if (!width || !height) return;
		const ratio = window.devicePixelRatio || 1;
		const pixelWidth = Math.round(width * ratio);
		const pixelHeight = Math.round(height * ratio);
		if (fullAlbumWaveform.width !== pixelWidth || fullAlbumWaveform.height !== pixelHeight) {
			fullAlbumWaveform.width = pixelWidth;
			fullAlbumWaveform.height = pixelHeight;
		}
		const context = fullAlbumWaveform.getContext('2d');
		context.clearRect(0, 0, pixelWidth, pixelHeight);
		if (!peaks.length) return;
		const barWidth = 2 * ratio;
		const gap = 3 * ratio;
		const count = Math.max(1, Math.floor(pixelWidth / (barWidth + gap)));
		const center = pixelHeight / 2;
		const playedGradient = context.createLinearGradient(0, 0, pixelWidth, 0);
		playedGradient.addColorStop(0, activeSeekColors[0]);
		playedGradient.addColorStop(1, activeSeekColors[1]);
		for (let index = 0; index < count; index += 1) {
			const start = Math.floor((index / count) * peaks.length);
			const end = Math.max(start + 1, Math.floor(((index + 1) / count) * peaks.length));
			let peak = 0;
			for (let sample = start; sample < end; sample += 1) peak = Math.max(peak, Number(peaks[sample]) || 0);
			const amplitude = Math.max(2 * ratio, peak * pixelHeight * 0.43);
			const fraction = index / Math.max(1, count - 1);
			context.fillStyle = fraction <= waveformProgress
				? playedGradient
				: fraction <= waveformBuffered
					? 'rgba(247, 248, 248, 0.52)'
					: 'rgba(247, 248, 248, 0.32)';
			const x = index * (barWidth + gap);
			context.fillRect(x, center - amplitude, barWidth, amplitude);
			context.fillRect(x, center, barWidth, amplitude);
		}
	};

	const trackButtons = () => Array.from(playlist.querySelectorAll('.wp-block-playlist-track__button'));
	trackButtons().forEach((button) => {
		const playIndicator = document.createElement('span');
		playIndicator.className = 'dimitrium-playlist-track__play';
		playIndicator.setAttribute('aria-hidden', 'true');
		playIndicator.innerHTML = '<svg class="dimitrium-playlist-track__play-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.661,14.386,8.584,20.552A3.01,3.01,0,0,1,4,17.994V6.006A3.01,3.01,0,0,1,8.584,3.448L18.661,9.614A2.8,2.8,0,0,1,18.661,14.386Z"/></svg><svg class="dimitrium-playlist-track__pause-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5,19.5V4.5A1.326,1.326,0,0,1,6.5,3h2A1.326,1.326,0,0,1,10,4.5v15A1.326,1.326,0,0,1,8.5,21h-2A1.326,1.326,0,0,1,5,19.5ZM15.5,21h2A1.326,1.326,0,0,0,19,19.5V4.5A1.326,1.326,0,0,0,17.5,3h-2A1.326,1.326,0,0,0,14,4.5v15A1.326,1.326,0,0,0,15.5,21Z"/></svg>';
		button.append(playIndicator);
	});
	const activeButton = () => playlist.querySelector('.wp-block-playlist-track__button[aria-current="true"]') || trackButtons()[0] || null;
	const playlistTracks = (() => {
		try {
			const context = JSON.parse(playlist.dataset.wpContext || '{}');
			const stateElement = document.getElementById('wp-script-module-data-@wordpress/interactivity');
			const state = JSON.parse(stateElement?.textContent || '{}');
			return state?.state?.['core/playlist']?.playlists?.[context.playlistId]?.tracks || {};
		} catch (error) {
			return {};
		}
	})();
	const trackIdForButton = (button) => {
		try {
			return JSON.parse(button?.dataset.wpContext || '{}').trackId || '';
		} catch (error) {
			return '';
		}
	};
	const durationInSeconds = (value) => {
		const time = String(value || '').match(/\d+(?::\d{2}){1,2}/)?.[0] || '';
		const parts = time.split(':').map(Number);
		if (!parts.length || parts.some((part) => !Number.isFinite(part))) return 0;
		return parts.reduce((total, part) => (total * 60) + part, 0);
	};
	const formatAlbumDuration = (seconds) => {
		const hours = Math.floor(seconds / 3600);
		const minutes = Math.floor((seconds % 3600) / 60);
		const remaining = seconds % 60;
		return hours > 0
			? `${hours}:${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}`
			: `${minutes}:${String(remaining).padStart(2, '0')}`;
	};

	const updateMetadata = () => {
		const button = activeButton();
		const image = button?.querySelector('.wp-block-playlist-track__image');
		const nextTitle = button?.querySelector('.wp-block-playlist-track__title')?.textContent?.trim() || player?.options?.title || bar.dataset.labelUnknown;
		const nextArtist = button?.querySelector('.wp-block-playlist-track__artist')?.textContent?.trim() || player?.options?.artist || '';
		const imageUrl = image?.currentSrc || image?.src || player?.options?.artwork || '';
		const selectedTrack = playlistTracks[trackIdForButton(button)];
		const backCoverUrl = window.dimitriumBackCovers?.[selectedTrack?.url] || '';
		if (selectedTrack?.url) {
			fullDownload.href = selectedTrack.url;
			const extension = selectedTrack.url.split('?')[0].split('.').pop() || 'mp3';
			fullDownload.setAttribute('download', `${nextTitle}.${extension}`);
			fullDownload.removeAttribute('aria-disabled');
		} else {
			fullDownload.href = '#';
			fullDownload.setAttribute('aria-disabled', 'true');
		}
		const buttons = trackButtons();
		const firstButton = buttons[0];
		const overviewTitle = Object.values(playlistTracks)[0]?.album || player?.options?.album || firstButton?.querySelector('.wp-block-playlist-track__title')?.textContent?.trim() || nextTitle;
		const overviewArtist = firstButton?.querySelector('.wp-block-playlist-track__artist')?.textContent?.trim() || nextArtist;
		const totalSeconds = buttons.reduce((sum, trackButton) => {
			return sum + durationInSeconds(trackButton.querySelector('.wp-block-playlist-track__length')?.textContent);
		}, 0);
		const isSerbian = document.documentElement.lang?.startsWith('sr');
		const trackLabel = isSerbian
			? `${buttons.length} ${buttons.length === 1 ? 'pesma' : 'pesama'}`
			: `${buttons.length} ${buttons.length === 1 ? 'track' : 'tracks'}`;
		albumTitle.textContent = overviewTitle;
		albumArtist.textContent = overviewArtist;
		albumArtist.hidden = !overviewArtist;
		albumMeta.textContent = `${trackLabel} · ${formatAlbumDuration(totalSeconds)} · 2026`;
		if (albumPageTitle) albumPageTitle.textContent = overviewTitle;
		if (albumPageArtist) {
			albumPageArtist.textContent = overviewArtist;
			albumPageArtist.hidden = !overviewArtist;
		}
		if (albumPageMeta) albumPageMeta.textContent = `${trackLabel} · ${formatAlbumDuration(totalSeconds)} · 2026`;
		fullAlbumTitle.textContent = overviewTitle;
		fullAlbumArtist.textContent = overviewArtist;
		fullAlbumArtist.hidden = !overviewArtist;
		fullAlbumTracks.textContent = String(buttons.length);
		fullAlbumDuration.textContent = formatAlbumDuration(totalSeconds);
		if (backCoverUrl) {
			fullAlbumBackArt.src = backCoverUrl;
			fullAlbumBackArt.hidden = false;
			fullAlbumBack.classList.add('has-back-art');
		} else {
			fullAlbumBackArt.removeAttribute('src');
			fullAlbumBackArt.hidden = true;
			fullAlbumBack.classList.remove('has-back-art');
		}

		[title, fullTitle].forEach((element) => { element.textContent = nextTitle; });
		[artist, fullArtist].forEach((element) => {
			element.textContent = nextArtist;
			element.hidden = !nextArtist;
		});
		[artwork, fullArtwork, playlistCoverImage].forEach((element) => {
			if (imageUrl) {
				element.src = imageUrl;
				element.hidden = false;
			} else {
				element.removeAttribute('src');
				element.hidden = true;
			}
		});
		[albumPageArtwork].filter(Boolean).forEach((element) => {
			if (imageUrl) {
				element.src = imageUrl;
				element.hidden = false;
			}
		});
		[albumPageBackArt].filter(Boolean).forEach((element) => {
			const pageBackArt = backCoverUrl || imageUrl;
			if (pageBackArt) {
				element.src = pageBackArt;
				element.hidden = false;
			}
		});
		if (imageUrl) {
			full.style.setProperty('--dimitrium-artwork', `url(${JSON.stringify(imageUrl)})`);
			bar.style.setProperty('--dimitrium-artwork', `url(${JSON.stringify(imageUrl)})`);
			playlist.style.setProperty('--dimitrium-artwork', `url(${JSON.stringify(imageUrl)})`);
			applyArtworkPalette(imageUrl);
		} else {
			full.style.removeProperty('--dimitrium-artwork');
			bar.style.removeProperty('--dimitrium-artwork');
			playlist.style.removeProperty('--dimitrium-artwork');
			applyArtworkPalette('');
		}

		const index = Math.max(0, buttons.indexOf(button));
		[previous, fullPrevious].forEach((element) => { element.disabled = buttons.length === 0; });
		[next, fullNext].forEach((element) => { element.disabled = buttons.length < 2 || index === buttons.length - 1; });
	};

	const setTrackListOpen = (open) => {
		if (!trackList) return;
		if (isAlbumPagePlaylist) {
			trackList.hidden = false;
			return;
		}
		trackList.hidden = !open;
		albumOverview.hidden = !open;
		playlist.classList.toggle('is-tracklist-open', open);
		playlistCoverButton.setAttribute('aria-expanded', String(open));
		playlistCoverButton.setAttribute(
			'aria-label',
			document.documentElement.lang?.startsWith('sr')
				? (open ? 'Sakrij listu pesama' : 'Prikaži listu pesama')
				: (open ? 'Hide track list' : 'Show track list')
		);
	};

	const updatePlaying = (isPlaying) => {
		bar.classList.toggle('is-playing', isPlaying);
		full.classList.toggle('is-playing', isPlaying);
		playlist.classList.toggle('is-playing', isPlaying);
		[toggle, fullToggle].forEach((element) => element.setAttribute('aria-label', isPlaying ? bar.dataset.labelPause : bar.dataset.labelPlay));
	};

	const updateTimes = (elapsed, total) => {
		[current, fullCurrent].forEach((element) => { element.textContent = formatTime(elapsed); });
		[duration, fullDuration].forEach((element) => { element.textContent = formatTime(total); });
	};

	const revealBar = () => {
		unlockPlaybackSections();
		if (!bar.hidden) return;
		bar.hidden = false;
		document.body.classList.add('has-dimitrium-now-playing');
	};

	const connect = (instance) => {
		if (!instance) return;
		player = instance;
		if (player.audio && !connectedAudio.has(player.audio)) {
			connectedAudio.add(player.audio);
			const refreshAudioState = () => {
				updateTimes(Number(player.audio?.currentTime) || 0, Number(player.audio?.duration) || 0);
				setBufferedProgress(player.audio);
			};
			player.audio.addEventListener('loadedmetadata', refreshAudioState);
			player.audio.addEventListener('durationchange', refreshAudioState);
			player.audio.addEventListener('progress', () => setBufferedProgress(player.audio));
			player.audio.addEventListener('emptied', () => setBufferedProgress(null));
			player.audio.addEventListener('play', () => {
				if (Date.now() < completionGuardUntil) {
					player.audio.pause();
					return;
				}
				markPlaybackStart(player.audio);
				revealBar();
			});
			player.audio.addEventListener('pause', lockPlaybackSectionsAfterPause);
			player.audio.addEventListener('seeking', markSeeked);
			refreshAudioState();
		}
		updateMetadata();
		updatePlaying(Boolean(player.isPlaying));
		if (player.isPlaying || (player.audio && !player.audio.paused)) revealBar();
		window.requestAnimationFrame(drawAlbumWaveform);
	};

	const changeTrack = (direction) => {
		const buttons = trackButtons();
		const index = Math.max(0, buttons.indexOf(activeButton()));
		if (direction === 'previous') {
			if ((Number(player?.audio?.currentTime) || 0) > 3 || index === 0) {
				player?.seekTo(0);
				setSeekProgress(0);
				updateTimes(0, Number(player?.audio?.duration) || 0);
				return;
			}
			buttons[index - 1]?.click();
			return;
		}
		buttons[index + 1]?.click();
	};

	const openFull = () => {
		if (!full.hidden) return;
		window.clearTimeout(fullTransitionTimer);
		openingAnimation?.cancel();
		closingBackgroundAnimation?.cancel();
		closingBackgroundAnimation = null;
		closingElementAnimations.forEach((animation) => animation.cancel());
		closingElementAnimations = [];
		closingTextClones.forEach((clone) => clone.remove());
		closingTextClones = [];
		openingAuxAnimations.forEach((animation) => animation.cancel());
		openingAuxAnimations = [];
		openingClone?.remove();
		openingClone = null;
		window.clearTimeout(openingTimer);
		window.cancelAnimationFrame(frostFrameOne);
		window.cancelAnimationFrame(frostFrameTwo);
		frostAnimation?.cancel();
		frostAnimation = null;
		window.clearTimeout(frostStartTimer);
		frost.style.removeProperty('background-color');
		frost.style.removeProperty('backdrop-filter');
		frost.style.removeProperty('-webkit-backdrop-filter');
		bar.classList.remove('is-frost-entering');
		focusBeforeFull = document.activeElement;
		const sourceRect = artwork.getBoundingClientRect();
		const barRect = bar.getBoundingClientRect();
		bar.classList.add('is-artwork-transitioning');
		full.hidden = false;
		full.classList.remove('is-leaving');
		full.classList.add('is-opening-motion');
		document.documentElement.classList.add('has-dimitrium-full-player');
		document.body.classList.add('has-dimitrium-full-player');
		updateMetadata();
		const targetRect = fullArtworkWrap.getBoundingClientRect();
		const sourceX = sourceRect.left + sourceRect.width / 2;
		const sourceY = sourceRect.top + sourceRect.height / 2;
		const targetX = targetRect.left + targetRect.width / 2;
		const targetY = targetRect.top + targetRect.height / 2;
		full.style.setProperty('--dimitrium-open-x', `${sourceX - targetX}px`);
		full.style.setProperty('--dimitrium-open-y', `${sourceY - targetY}px`);
		full.style.setProperty('--dimitrium-open-scale', String(sourceRect.width / Math.max(1, targetRect.width)));
		const animateFromBar = (element, source) => {
			const from = source.getBoundingClientRect();
			const to = element.getBoundingClientRect();
			const x = (from.left + from.width / 2) - (to.left + to.width / 2);
			const y = (from.top + from.height / 2) - (to.top + to.height / 2);
			const scaleX = from.width / Math.max(1, to.width);
			const scaleY = from.height / Math.max(1, to.height);
			return element.animate(
				[
					{ opacity: 1, transform: `translate3d(${x}px, ${y}px, 0) scale(${scaleX}, ${scaleY})` },
					{ opacity: 1, transform: 'translate3d(0, 0, 0) scale(1)' }
				],
				{ duration: 430, easing: 'cubic-bezier(0.22, 0.8, 0.25, 1)', fill: 'none' }
			);
		};
		bar.classList.add('is-elements-transitioning');
		openingAuxAnimations = [
			overlayBackground.animate(
				[{ clipPath: `inset(${Math.max(0, barRect.top)}px 0 0 0)` }, { clipPath: 'inset(0 0 0 0)' }],
				{ duration: 460, easing: 'cubic-bezier(0.22, 0.8, 0.25, 1)', fill: 'none' }
			),
			animateFromBar(fullTitle, title),
			animateFromBar(fullArtist, artist),
			animateFromBar(fullPrevious, previous),
			animateFromBar(fullToggle, toggle),
			animateFromBar(fullNext, next),
			animateFromBar(close, expand),
			animateFromBar(fullCurrent, current),
			animateFromBar(fullSeek, seek),
			animateFromBar(fullDuration, duration),
			fullDownload.animate(
				[{ opacity: 0 }, { opacity: 0.72 }],
				{ duration: 300, easing: 'ease-out', fill: 'none' }
			),
			...[...close.querySelectorAll('[data-corner]')].map((corner) => corner.animate(
				[
					{ transform: 'rotate(0deg) scaleX(1)' },
					{ offset: 0.49, transform: 'rotate(0deg) scaleX(0)' },
					{ offset: 0.51, transform: 'rotate(180deg) scaleX(0)' },
					{ transform: 'rotate(180deg) scaleX(1)' }
				],
				{ duration: 500, easing: 'cubic-bezier(0.22, 0.8, 0.25, 1)', fill: 'none' }
			))
		];
		openingAnimation = fullArtworkWrap.animate(
			[
				{ transform: `translate3d(${sourceX - targetX}px, ${sourceY - targetY}px, 0) scale(${sourceRect.width / Math.max(1, targetRect.width)})` },
				{ transform: 'translate3d(0, 0, 0) scale(1)' }
			],
			{ duration: 460, easing: 'cubic-bezier(0.22, 0.8, 0.25, 1)', fill: 'none' }
		);
		openingAnimation.finished.then(() => {
			window.requestAnimationFrame(() => {
				window.clearTimeout(openingTimer);
				openingTimer = window.setTimeout(() => {
					full.classList.remove('is-opening-motion');
					drawAlbumWaveform();
				}, 220);
			});
		}).catch(() => {});
		close.focus({ preventScroll: true });
	};

	const closeFull = () => {
		openingAnimation?.cancel();
		openingAnimation = null;
		openingAuxAnimations.forEach((animation) => animation.cancel());
		openingAuxAnimations = [];
		openingClone?.remove();
		openingClone = null;
		window.clearTimeout(openingTimer);
		full.classList.remove('is-entering', 'is-opening-clone', 'is-opening-motion');
		const barRect = bar.getBoundingClientRect();
		const animateTextCloneToBar = (element, target) => {
			const from = element.getBoundingClientRect();
			const to = target.getBoundingClientRect();
			const targetStyle = window.getComputedStyle(target);
			const clone = target.cloneNode(true);
			clone.removeAttribute('id');
			clone.setAttribute('aria-hidden', 'true');
			Object.assign(clone.style, {
				position: 'fixed',
				zIndex: '1000004',
				left: `${to.left}px`,
				top: `${to.top}px`,
				boxSizing: 'border-box',
				width: `${to.width}px`,
				height: `${to.height}px`,
				margin: '0',
				color: targetStyle.color,
				fontFamily: targetStyle.fontFamily,
				fontSize: targetStyle.fontSize,
				fontStyle: targetStyle.fontStyle,
				fontWeight: targetStyle.fontWeight,
				fontVariantNumeric: targetStyle.fontVariantNumeric,
				letterSpacing: targetStyle.letterSpacing,
				lineHeight: targetStyle.lineHeight,
				textAlign: targetStyle.textAlign,
				pointerEvents: 'none',
				transformOrigin: 'center center',
				willChange: 'transform'
			});
			document.body.append(clone);
			closingTextClones.push(clone);
			const x = (from.left + from.width / 2) - (to.left + to.width / 2);
			const y = (from.top + from.height / 2) - (to.top + to.height / 2);
			const animation = clone.animate(
				[
					{ transform: `translate3d(${x}px, ${y}px, 0)` },
					{ offset: 0.76, transform: `translate3d(${x * 0.24}px, ${y * 0.24}px, 0)` },
					{ transform: 'translate3d(0, 0, 0)' }
				],
				{ duration: 430, easing: 'cubic-bezier(0.22, 0.8, 0.25, 1)', fill: 'forwards' }
			);
			const hideSource = element.animate([{ opacity: 0 }, { opacity: 0 }], { duration: 430, fill: 'forwards' });
			return [animation, hideSource];
		};
		const animateToBar = (element, target, uniformScale = false, textAnchor = false) => {
			const from = element.getBoundingClientRect();
			const to = target.getBoundingClientRect();
			let x = (to.left + to.width / 2) - (from.left + from.width / 2);
			let y = (to.top + to.height / 2) - (from.top + from.height / 2);
			let scaleX = to.width / Math.max(1, from.width);
			let scaleY = to.height / Math.max(1, from.height);
			let transformOrigin = 'center center';
			if (textAnchor) {
				const fromFontSize = Number.parseFloat(window.getComputedStyle(element).fontSize) || 1;
				const toFontSize = Number.parseFloat(window.getComputedStyle(target).fontSize) || fromFontSize;
				scaleX = toFontSize / fromFontSize;
				scaleY = scaleX;
				x = to.left - from.left;
				y = to.bottom - from.bottom;
				transformOrigin = 'left bottom';
			}
			if (uniformScale) {
				scaleX = scaleY;
			}
			return element.animate(
				[
					{ opacity: 1, transformOrigin, transform: 'translate3d(0, 0, 0) scale(1)' },
					{ offset: 0.76, opacity: 1, transformOrigin, transform: `translate3d(${x * 0.76}px, ${y * 0.76}px, 0) scale(${1 + (scaleX - 1) * 0.76}, ${1 + (scaleY - 1) * 0.76})` },
					{ opacity: 1, transformOrigin, transform: `translate3d(${x}px, ${y}px, 0) scale(${scaleX}, ${scaleY})` }
				],
				{ duration: 430, easing: 'cubic-bezier(0.22, 0.8, 0.25, 1)', fill: 'forwards' }
			);
		};
		closingElementAnimations.forEach((animation) => animation.cancel());
		closingTextClones.forEach((clone) => clone.remove());
		closingTextClones = [];
		const currentAnimations = animateTextCloneToBar(fullCurrent, current);
		const durationAnimations = animateTextCloneToBar(fullDuration, duration);
		closingElementAnimations = [
			animateToBar(fullTitle, title, true, true),
			animateToBar(fullArtist, artist, true, true),
			animateToBar(fullPrevious, previous, true),
			animateToBar(fullToggle, toggle, true),
			animateToBar(fullNext, next, true),
			animateToBar(close, expand, true),
			...currentAnimations,
			animateToBar(fullSeek, seek),
			...durationAnimations,
			...[...close.querySelectorAll('[data-corner]')].map((corner) => corner.animate(
				[
					{ transform: 'rotate(180deg) scaleX(1)' },
					{ offset: 0.49, transform: 'rotate(180deg) scaleX(0)' },
					{ offset: 0.51, transform: 'rotate(360deg) scaleX(0)' },
					{ transform: 'rotate(360deg) scaleX(1)' }
				],
				{ duration: 500, easing: 'cubic-bezier(0.22, 0.8, 0.25, 1)', fill: 'forwards' }
			))
		];
		bar.classList.add('is-elements-transitioning');
		closingBackgroundAnimation?.cancel();
		closingBackgroundAnimation = overlayBackground.animate(
			[{ clipPath: 'inset(0 0 0 0)' }, { clipPath: `inset(${Math.max(0, barRect.top)}px 0 0 0)` }],
			{ duration: 430, easing: 'cubic-bezier(0.22, 0.8, 0.25, 1)', fill: 'forwards' }
		);
		fullArtworkWrap.classList.remove('is-flipped');
		fullArtworkWrap.setAttribute('aria-pressed', 'false');
		full.classList.add('is-leaving');
		window.clearTimeout(fullTransitionTimer);
		fullTransitionTimer = window.setTimeout(() => {
			closingBackgroundAnimation?.cancel();
			closingBackgroundAnimation = null;
			closingElementAnimations.forEach((animation) => animation.cancel());
			closingElementAnimations = [];
			full.hidden = true;
			bar.classList.remove('is-elements-transitioning', 'is-artwork-transitioning');
			closingTextClones.forEach((clone) => clone.remove());
			closingTextClones = [];
			full.classList.remove('is-leaving');
			bar.classList.remove('is-frost-entering');
			window.clearTimeout(frostStartTimer);
			frost.style.backgroundColor = 'rgba(13, 14, 16, 1)';
			frost.style.backdropFilter = 'blur(0) saturate(1)';
			frost.style.webkitBackdropFilter = 'blur(0) saturate(1)';
			frostStartTimer = window.setTimeout(() => {
				frostAnimation?.cancel();
				frostAnimation = frost.animate(
					[
						{ backgroundColor: 'rgba(13, 14, 16, 1)', backdropFilter: 'blur(0) saturate(1)', webkitBackdropFilter: 'blur(0) saturate(1)' },
						{ backgroundColor: 'rgba(20, 21, 22, 0.714)', backdropFilter: 'blur(22px) saturate(1.18)', webkitBackdropFilter: 'blur(22px) saturate(1.18)' }
					],
					{ duration: 1500, easing: 'cubic-bezier(0.45, 0, 0.55, 1)', fill: 'both' }
				);
				frostAnimation.finished.then(() => {
					frost.style.backgroundColor = 'rgba(20, 21, 22, 0.714)';
					frostAnimation?.cancel();
					frost.style.removeProperty('background-color');
					frost.style.removeProperty('backdrop-filter');
					frost.style.removeProperty('-webkit-backdrop-filter');
				}).catch(() => {});
			}, 180);
			document.documentElement.classList.remove('has-dimitrium-full-player');
			document.body.classList.remove('has-dimitrium-full-player');
			focusBeforeFull?.focus?.({ preventScroll: true });
		}, 430);
	};

	const handleAction = (action) => {
		if (action === 'expand') return openFull();
		if (action === 'close') return closeFull();
		if (!player) return;
		if (action === 'toggle') return player.togglePlay();
		if (action === 'previous' || action === 'next') changeTrack(action);
	};

	document.addEventListener('waveformplayer:ready', (event) => {
		if (playlist.contains(event.target)) connect(event.detail?.player);
	});
	document.addEventListener('waveformplayer:play', (event) => {
		if (!playlist.contains(event.target)) return;
		connect(event.detail?.player || player);
		if (Date.now() < completionGuardUntil) {
			player?.audio?.pause();
			updatePlaying(false);
			return;
		}
		markPlaybackStart(player?.audio);
		revealBar();
		updatePlaying(true);
	});
	document.addEventListener('waveformplayer:pause', (event) => {
		if (playlist.contains(event.target)) {
			updatePlaying(false);
			lockPlaybackSectionsAfterPause();
		}
	});
	document.addEventListener('waveformplayer:ended', (event) => {
		if (!playlist.contains(event.target)) return;
		updatePlaying(false);
		const audio = event.detail?.player?.audio || player?.audio;
		const run = syncListeningRun(audio);
		// Core resets audio.currentTime to 0 before emitting waveformplayer:ended.
		// The event detail preserves the actual final position and duration.
		const total = Number(event.detail?.duration) || Number(audio?.duration) || 0;
		const elapsed = Number(event.detail?.currentTime) || Number(audio?.currentTime) || 0;
		const completedHonestly = run.startedAtBeginning && !run.seeked && total > 0 && elapsed >= total - 1.5;
		if (!completedHonestly) return;
		if (hasUnlockedReward()) return;
		persistRewardUnlock();
		revealRewardTrigger();
		completionGuardUntil = Date.now() + 1800;
		audio?.pause();
		window.setTimeout(() => player?.audio?.pause(), 50);
		window.setTimeout(() => player?.audio?.pause(), 250);
		const wasFullScreen = !full.hidden;
		if (wasFullScreen) closeFull();
		window.setTimeout(openReward, wasFullScreen ? 520 : 80);
	});
	document.addEventListener('waveformplayer:timeupdate', (event) => {
		if (!playlist.contains(event.target)) return;
		const elapsed = Number(event.detail?.currentTime) || 0;
		const total = Number(event.detail?.duration) || 0;
		updateTimes(elapsed, total);
		if (!seeking) setSeekProgress(total > 0 ? Math.round((elapsed / total) * 1000) : 0);
		setBufferedProgress(player?.audio);
	});

	bar.addEventListener('click', (event) => {
		const action = event.target.closest('[data-action]')?.dataset.action;
		if (action) return handleAction(action);
		if (event.target.closest('input, button, a')) return;
		openFull();
	});
	full.addEventListener('click', (event) => handleAction(event.target.closest('[data-full-action]')?.dataset.fullAction));
	fullArtworkWrap.addEventListener('click', () => {
		const flipped = fullArtworkWrap.classList.toggle('is-flipped');
		fullArtworkWrap.setAttribute('aria-pressed', String(flipped));
		if (flipped) window.requestAnimationFrame(drawAlbumWaveform);
	});
	window.addEventListener('resize', drawAlbumWaveform);
	if (!isAlbumPagePlaylist) playlistCoverButton.addEventListener('click', () => setTrackListOpen(trackList?.hidden));
	setTrackListOpen(isAlbumPagePlaylist);
	playlist.addEventListener('click', (event) => {
		if (!desktopPlaylist.matches) return;
		const button = event.target.closest('.wp-block-playlist-track__button');
		if (!button || event.detail === 0 || event.target.closest('.dimitrium-playlist-track__play')) return;
		event.preventDefault();
		event.stopImmediatePropagation();
	}, true);
	trackList?.addEventListener('click', (event) => {
		const button = event.target.closest('.wp-block-playlist-track__button');
		if (button) {
			const selectedTrack = playlistTracks[trackIdForButton(button)];
			window.setTimeout(() => {
				if (!player || !selectedTrack?.url) return;
				const loadedUrl = player.audio?.currentSrc || player.audio?.src || '';
				if (loadedUrl === selectedTrack.url) return;
				player.loadTrack(selectedTrack.url, selectedTrack.title, selectedTrack.artist, {
					artwork: '',
					artworkAlt: selectedTrack.imageAlt || ''
				}).then(() => {
					player.play()?.catch(() => {});
					updateMetadata();
				}).catch(() => {});
			}, 150);
			if (!isAlbumPagePlaylist && !desktopPlaylist.matches) window.setTimeout(() => setTrackListOpen(false), 180);
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !full.hidden) closeFull();
	});

	seeks.forEach((element) => {
		element.addEventListener('pointerdown', () => { seeking = true; markSeeked(); });
		element.addEventListener('input', () => {
			if (!player) return;
			const percent = Number(element.value) / 1000;
			setSeekProgress(element.value);
			const total = Number(player.audio?.duration) || 0;
			[current, fullCurrent].forEach((time) => { time.textContent = formatTime(total * percent); });
			player.seekToPercent(percent);
		});
		element.addEventListener('change', () => { seeking = false; });
		element.addEventListener('pointerup', () => { seeking = false; });
	});

	rewardClose?.addEventListener('click', closeReward);
	rewardTrigger?.addEventListener('click', openReward);
	if (hasUnlockedReward()) revealRewardTrigger();
	positionRewardTrigger();
	window.addEventListener('resize', positionRewardTrigger, { passive: true });
	reward.addEventListener('click', (event) => {
		if (event.target === reward) closeReward();
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !reward.hidden) closeReward();
	});

	const observer = new MutationObserver(() => window.requestAnimationFrame(updateMetadata));
	observer.observe(playlist, { subtree: true, attributes: true, attributeFilter: ['aria-current'] });
	setSeekProgress(0);
	setBufferedProgress(null);
})();
