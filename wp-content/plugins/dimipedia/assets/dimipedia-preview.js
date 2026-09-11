(() => {
  const config = window.dimipediaPreview;
  if (!config || !config.apiBase) return;
  if (!config.enabled) return;

  const cache = new Map();
  // Keep the floating surface itself out of the site's generic <a>:hover rules.
  // The actual link fills the card, so the complete preview is still clickable.
  const card = document.createElement('aside');
  card.className = 'dimipedia-link-preview';
  card.setAttribute('aria-hidden', 'true');
  const cardLink = document.createElement('a');
  cardLink.className = 'dimipedia-link-preview__link';
  card.appendChild(cardLink);
  document.body.appendChild(card);

  let showTimer;
  let hideTimer;
  let activeLink;

  const targetFromLink = (link) => {
    if (link.closest('.dimipedia-language-switcher, .dimitrium-language-switcher, .pll-switcher, .pll-parent-menu-item')) return null;
    let url;
    try { url = new URL(link.href, window.location.origin); } catch (_) { return null; }
    if (url.origin !== window.location.origin) return null;
    const match = url.pathname.match(/^\/(en|sr)\/dimipedia\/([^/]+)\/?$/i);
    return match ? { language: match[1].toLowerCase(), slug: match[2].toLowerCase() } : null;
  };

  const escapeText = (value) => String(value || '');
  const excerptForImage = (excerpt) => {
    const words = escapeText(excerpt).replace(/…\s*$/, '').trim().split(/\s+/).filter(Boolean);
    if (!words.length) return '';
    // Every preview image is presented at 3:4, so one consistent excerpt
    // length gives every card a predictable visual balance.
    const limit = 30;
    return words.slice(0, limit).join(' ') + (words.length > limit ? '…' : '');
  };
  const render = (data) => {
    cardLink.replaceChildren();
    cardLink.href = data.url || '#';
    if (data.image) {
      const image = document.createElement('img');
      image.src = data.image;
      image.alt = '';
      image.loading = 'lazy';
      cardLink.appendChild(image);
    }
    const content = document.createElement('div');
    content.className = 'dimipedia-link-preview__content';
    if (data.category) {
      const category = document.createElement('p');
      category.className = 'dimipedia-link-preview__category';
      category.textContent = escapeText(data.category);
      content.appendChild(category);
    }
    const title = document.createElement('h2');
    title.textContent = escapeText(data.title);
    content.appendChild(title);
    const excerptText = excerptForImage(data.excerpt);
    if (excerptText) {
      const excerpt = document.createElement('p');
      excerpt.className = 'dimipedia-link-preview__excerpt';
      excerpt.textContent = excerptText;
      content.appendChild(excerpt);
    }
    cardLink.appendChild(content);
  };

  const place = (link) => {
    const rect = link.getBoundingClientRect();
    const width = Math.min(310, window.innerWidth - 32);
    const left = Math.max(16, Math.min(rect.left, window.innerWidth - width - 16));
    let top = rect.bottom;
    card.style.width = `${width}px`;
    card.style.left = `${left}px`;
    card.style.top = `${top}px`;
    const cardRect = card.getBoundingClientRect();
    if (cardRect.bottom > window.innerHeight - 16) {
      top = Math.max(16, rect.top - cardRect.height);
      card.style.top = `${top}px`;
    }
  };

  const hide = () => {
    clearTimeout(showTimer);
    clearTimeout(hideTimer);
    card.classList.remove('is-visible');
    card.setAttribute('aria-hidden', 'true');
    activeLink = null;
  };

  const scheduleHide = () => {
    clearTimeout(hideTimer);
    // This small grace period lets the pointer travel from the article link
    // straight onto its preview without making the card flicker away.
    hideTimer = setTimeout(hide, 220);
  };

  const show = async (link) => {
    const target = targetFromLink(link);
    if (!target) return;
    const key = `${target.language}/${target.slug}`;
    clearTimeout(hideTimer);
    activeLink = link;
    showTimer = setTimeout(async () => {
      try {
        let data = cache.get(key);
        if (!data) {
          const response = await fetch(`${config.apiBase}${encodeURIComponent(target.language)}/${encodeURIComponent(target.slug)}`, { credentials: 'same-origin' });
          if (!response.ok) return;
          data = await response.json();
          cache.set(key, data);
        }
        if (activeLink !== link) return;
        render(data);
        place(link);
        card.classList.add('is-visible');
        card.setAttribute('aria-hidden', 'false');
      } catch (_) {
        // A preview is purely progressive enhancement; the original link remains usable.
      }
    }, 260);
  };

  document.querySelectorAll('a[href]').forEach((link) => {
    if (!targetFromLink(link)) return;
    link.addEventListener('mouseenter', () => {
      show(link);
    });
    link.addEventListener('mouseleave', scheduleHide);
    link.addEventListener('focus', () => {
      show(link);
    });
    link.addEventListener('blur', scheduleHide);
  });

  card.addEventListener('mouseenter', () => clearTimeout(hideTimer));
  card.addEventListener('mouseleave', scheduleHide);

  document.addEventListener('click', (event) => {
    if (!card.contains(event.target) && (!activeLink || !activeLink.contains(event.target))) hide();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') hide();
  });

  // The card deliberately keeps its original side of the link while open.
  // Repositioning on pointer movement made it jump away just as it was reached.
})();
