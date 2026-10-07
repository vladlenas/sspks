(() => {
    'use strict';

    const data = JSON.parse(document.getElementById('data').textContent);
    const t = data.t;
    const lang = data.lang;
    const items = data.items;

    const $ = (id) => document.getElementById(id);
    const list = $('packages');
    const empty = $('empty');
    const toolbar = $('toolbar');
    const search = $('q');
    const platform = $('platform');
    const dsm = $('dsm');

    const STORAGE_KEY = 'sspks.filters';

    // ---------- helpers ----------

    function el(tag, props, ...children) {
        const node = document.createElement(tag);
        if (props) {
            for (const [key, value] of Object.entries(props)) {
                if (value === null || value === undefined || value === false) continue;
                if (key === 'class') node.className = value;
                else if (key === 'text') node.textContent = value;
                else if (key in node && typeof value !== 'string') node[key] = value;
                else node.setAttribute(key, value === true ? '' : value);
            }
        }
        for (const child of children) {
            if (child === null || child === undefined || child === false) continue;
            node.append(child);
        }
        return node;
    }

    function safeUrl(url) {
        return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : null;
    }

    function format(template, value) {
        return template.replace('%d', String(value)).replace('%s', String(value));
    }

    function pluralPackages(n) {
        const rule = new Intl.PluralRules(lang).select(n);
        const key = rule === 'one' ? 'packagesOne' : rule === 'few' ? 'packagesFew' : 'packagesMany';
        return format(t[key], n);
    }

    const sizeUnits = ['kilobyte', 'megabyte', 'gigabyte'];
    function formatSize(bytes) {
        let value = bytes / 1000;
        let unit = 0;
        while (value >= 1000 && unit < sizeUnits.length - 1) {
            value /= 1000;
            unit += 1;
        }
        return new Intl.NumberFormat(lang, {
            style: 'unit',
            unit: sizeUnits[unit],
            unitDisplay: 'short',
            maximumFractionDigits: value < 10 ? 1 : 0,
        }).format(value);
    }

    const relative = new Intl.RelativeTimeFormat(lang, { numeric: 'auto' });
    const absolute = new Intl.DateTimeFormat(lang, { dateStyle: 'long', timeStyle: 'short' });
    function formatAge(unixSeconds) {
        const seconds = unixSeconds - Date.now() / 1000;
        const steps = [
            ['year', 31536000], ['month', 2592000], ['week', 604800],
            ['day', 86400], ['hour', 3600], ['minute', 60],
        ];
        for (const [unit, size] of steps) {
            if (Math.abs(seconds) >= size) return relative.format(Math.round(seconds / size), unit);
        }
        return relative.format(0, 'minute');
    }

    function dsmLabel(minDsm) {
        const version = String(minDsm).split('-')[0];
        return version === '0' || version === '' ? null : `DSM ${version}+`;
    }

    function sanitizeHtml(html) {
        const allowed = new Set(['P', 'BR', 'UL', 'OL', 'LI', 'B', 'STRONG', 'I', 'EM', 'CODE', 'A']);
        const dropped = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'TEMPLATE', 'svg', 'math']);
        const doc = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');
        const out = document.createDocumentFragment();
        const walk = (source, target) => {
            for (const child of source.childNodes) {
                if (child.nodeType === Node.TEXT_NODE) {
                    target.append(child.textContent);
                } else if (child.nodeType === Node.ELEMENT_NODE) {
                    if (dropped.has(child.tagName)) continue;
                    if (allowed.has(child.tagName)) {
                        const copy = document.createElement(child.tagName.toLowerCase());
                        if (child.tagName === 'A') {
                            const href = safeUrl(child.getAttribute('href'));
                            if (href) {
                                copy.href = href;
                                copy.target = '_blank';
                                copy.rel = 'noopener noreferrer';
                            }
                        }
                        walk(child, copy);
                        target.append(copy);
                    } else {
                        walk(child, target);
                    }
                }
            }
        };
        walk(doc.body, out);
        return out;
    }

    // ---------- filters ----------

    function readSavedFilters() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
        } catch {
            return {};
        }
    }

    function saveFilters() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ platform: platform.value, dsm: dsm.value }));
        } catch {
            // Storage can be disabled; the filters just won't be remembered.
        }
    }

    function buildPlatformSelect() {
        platform.append(el('option', { value: '', text: t.allPlatforms }));
        for (const [family, archs] of Object.entries(data.families)) {
            const group = el('optgroup', { label: family });
            group.append(el('option', { value: `family:${family}`, text: format(t.anyInFamily, family) }));
            for (const arch of archs) {
                group.append(el('option', { value: arch, text: arch }));
            }
            platform.append(group);
        }
    }

    function setSelect(select, value) {
        if (value && [...select.options].some((o) => o.value === value)) {
            select.value = value;
        }
    }

    function currentFilter() {
        const terms = search.value.toLowerCase().split(/\s+/).filter(Boolean);
        const value = platform.value;
        let matchArch = () => true;
        if (value.startsWith('family:')) {
            const family = value.slice(7);
            const aliases = data.aliases[family] || [];
            matchArch = (arch) => arch === 'noarch' || arch === family || aliases.includes(arch) || data.archFamily[arch] === family;
        } else if (value) {
            const family = data.archFamily[value] || value;
            const tokens = [value, family, ...(data.aliases[family] || []), 'noarch'];
            matchArch = (arch) => tokens.includes(arch);
        }
        return { terms, matchArch, dsm: dsm.value };
    }

    function buildMatches(build, filter) {
        if (!build.arch.some(filter.matchArch)) return false;
        if (filter.dsm === '7' && !build.dsm7) return false;
        if (filter.dsm === '6' && build.dsm7) return false;
        return true;
    }

    function textMatches(item, terms) {
        if (terms.length === 0) return true;
        const haystack = `${item.name} ${item.id} ${item.description}`.toLowerCase();
        return terms.every((term) => haystack.includes(term));
    }

    // ---------- rendering ----------

    const rows = new Map();

    function renderBuildsTable(item) {
        const head = el('tr', null,
            el('th', { scope: 'col', text: t.version }),
            el('th', { scope: 'col', text: t.platforms }),
            el('th', { scope: 'col', text: t.minDsm }),
            el('th', { scope: 'col', text: t.size }),
            el('th', { scope: 'col', text: t.updated }),
            el('th', { scope: 'col' }),
        );
        const body = el('tbody');
        for (const build of item.builds) {
            const tr = el('tr', null,
                el('td', null, build.version, build.beta ? el('span', { class: 'status', text: ` ${t.beta}` }) : null),
                el('td', { class: 'arch', text: build.arch.join(', ') }),
                el('td', { text: String(build.minDsm).split('-')[0] }),
                el('td', { text: formatSize(build.size) }),
                el('td', { title: absolute.format(new Date(build.updated * 1000)), text: formatAge(build.updated) }),
                el('td', null, el('a', { href: build.url, download: build.file, text: t.download })),
            );
            build.row = tr;
            body.append(tr);
        }
        return el('div', { class: 'builds' }, el('table', null, el('thead', null, head), body));
    }

    function renderDetails(item, id) {
        const details = el('div', { class: 'pkg__details', id, hidden: true });
        details.append(el('h3', { text: t.builds }), renderBuildsTable(item));

        if (item.changelog) {
            details.append(el('h3', { text: t.changelog }), el('div', { class: 'changelog' }, sanitizeHtml(item.changelog)));
        }
        if (item.screenshots.length > 0) {
            const shots = el('div', { class: 'shots' });
            for (const src of item.screenshots) {
                shots.append(el('a', { href: src, target: '_blank', rel: 'noopener' }, el('img', { src, alt: '', loading: 'lazy' })));
            }
            details.append(el('h3', { text: t.screenshots }), shots);
        }

        const links = el('p', { class: 'links' });
        if (item.maintainer) {
            const url = safeUrl(item.maintainerUrl);
            links.append(el('span', null, `${t.maintainer}: `,
                url ? el('a', { href: url, target: '_blank', rel: 'noopener noreferrer', text: item.maintainer }) : item.maintainer));
        }
        const support = safeUrl(item.supportUrl);
        if (support) {
            links.append(el('a', { href: support, target: '_blank', rel: 'noopener noreferrer', text: t.support }));
        }
        if (links.childNodes.length > 0) details.append(links);
        return details;
    }

    function renderItem(item, index) {
        const detailsId = `details-${index}`;
        const li = el('li', { class: 'pkg', id: `pkg-${encodeURIComponent(item.id)}` });

        const title = el('h2', { class: 'pkg__title' },
            el('span', { class: 'pkg__name', text: item.name }),
            el('span', { class: 'pkg__version', text: item.version }),
            item.beta ? el('span', { class: 'status', text: t.beta }) : null,
        );
        const meta = el('ul', { class: 'pkg__meta' });
        const actions = el('div', { class: 'pkg__actions' });
        const toggle = el('button', {
            type: 'button',
            class: 'button button--quiet',
            'aria-expanded': 'false',
            'aria-controls': detailsId,
        });

        const row = el('div', { class: 'pkg__row' },
            el('img', { class: 'pkg__icon', src: item.icon, alt: '', width: 56, height: 56, loading: 'lazy' }),
            el('div', { class: 'pkg__main' },
                title,
                item.description ? el('p', { class: 'pkg__desc', text: item.description }) : null,
                meta,
            ),
            actions,
        );
        const details = renderDetails(item, detailsId);

        toggle.addEventListener('click', () => {
            const open = details.hidden;
            details.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            updateToggleLabel(entry);
        });

        li.append(row, details);
        const entry = { item, li, meta, actions, toggle, details };
        rows.set(item.id, entry);
        return li;
    }

    function updateToggleLabel(entry) {
        const open = !entry.details.hidden;
        entry.toggle.textContent = open
            ? t.hideDetails
            : entry.matching.length > 1 ? format(t.showBuilds, entry.matching.length) : t.details;
    }

    function updateItem(entry, matching) {
        entry.matching = matching;
        const shown = matching.length > 0 ? matching : entry.item.builds;
        const primary = shown[0];

        const archs = [...new Set(shown.flatMap((b) => b.arch))];
        const archText = archs.length > 4 ? `${archs.slice(0, 3).join(', ')} +${archs.length - 3}` : archs.join(', ');
        const dsmText = dsmLabel(primary.minDsm);

        entry.meta.replaceChildren(
            el('li', { title: archs.join(', '), text: archText }),
            dsmText ? el('li', { text: dsmText }) : null,
            el('li', { text: formatSize(primary.size) }),
            el('li', { title: absolute.format(new Date(primary.updated * 1000)), text: `${t.updated} ${formatAge(primary.updated)}` }),
        );

        entry.actions.replaceChildren();
        if (matching.length === 1) {
            entry.actions.append(el('a', { class: 'button', href: primary.url, download: primary.file, text: t.download }));
        }
        entry.actions.append(entry.toggle);
        updateToggleLabel(entry);

        for (const build of entry.item.builds) {
            build.row.classList.toggle('is-dimmed', !matching.includes(build));
        }
    }

    function apply() {
        const filter = currentFilter();
        let visible = 0;
        for (const entry of rows.values()) {
            const matching = entry.item.builds.filter((b) => buildMatches(b, filter));
            const show = matching.length > 0 && textMatches(entry.item, filter.terms);
            entry.li.hidden = !show;
            if (show) {
                visible += 1;
                updateItem(entry, matching);
            }
        }

        const filtered = visible < items.length;
        $('count').textContent = filtered ? `${visible} / ${pluralPackages(items.length)}` : pluralPackages(items.length);

        if (items.length > 0 && visible === 0) {
            const clear = el('button', { type: 'button', class: 'button button--quiet', text: t.clearFilters });
            clear.addEventListener('click', clearFilters);
            empty.replaceChildren(el('p', { text: t.emptyFilter }), clear);
            empty.hidden = false;
        } else if (items.length > 0) {
            empty.hidden = true;
        }
    }

    function clearFilters() {
        search.value = '';
        platform.value = '';
        dsm.value = '';
        saveFilters();
        apply();
    }

    function renderBroken() {
        const entries = Object.entries(data.broken || {});
        if (entries.length === 0) return;
        const box = $('broken');
        const ul = el('ul');
        for (const [file, reason] of entries) {
            ul.append(el('li', null, el('code', { text: file }), ` — ${reason}`));
        }
        box.append(el('h2', { text: `${t.broken}: ${entries.length}` }), ul);
        box.hidden = false;
    }

    // ---------- front panel ----------

    function setupBays() {
        const bays = [...document.querySelectorAll('#bays .bay')];
        bays.forEach((bay, i) => {
            const item = items[i];
            if (!item) return;
            bay.style.setProperty('--i', String(i));
            bay.title = `${item.name} ${item.version}`;
            bay.addEventListener('click', () => {
                const entry = rows.get(item.id);
                if (!entry) return;
                if (entry.li.hidden) clearFilters();
                entry.li.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
                entry.li.classList.add('is-flashing');
                setTimeout(() => entry.li.classList.remove('is-flashing'), 1200);
            });
        });
        $('bays').classList.add('is-booting');
    }

    function setupCopy() {
        const button = $('copy-source');
        const url = $('source-url').textContent;
        const label = button.textContent;
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(url);
            } catch {
                const range = document.createRange();
                range.selectNodeContents($('source-url'));
                const selection = getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                document.execCommand('copy');
            }
            button.textContent = button.dataset.copied;
            setTimeout(() => { button.textContent = label; }, 1600);
        });
    }

    // ---------- start ----------

    setupCopy();
    renderBroken();

    if (items.length === 0) {
        empty.replaceChildren(el('p', null, el('strong', { text: t.emptyFolder }), t.emptyFolderHint));
        empty.hidden = false;
        return;
    }

    buildPlatformSelect();
    const saved = readSavedFilters();
    const params = new URLSearchParams(location.search);
    setSelect(platform, (params.get('arch') || '').toLowerCase() || saved.platform);
    setSelect(dsm, saved.dsm);

    const fragment = document.createDocumentFragment();
    items.forEach((item, i) => fragment.append(renderItem(item, i)));
    list.append(fragment);
    toolbar.hidden = false;

    search.addEventListener('input', apply);
    platform.addEventListener('change', () => { saveFilters(); apply(); });
    dsm.addEventListener('change', () => { saveFilters(); apply(); });

    apply();
    setupBays();
})();
