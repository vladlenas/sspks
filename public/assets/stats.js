(() => {
    'use strict';

    const data = JSON.parse(document.getElementById('data').textContent);
    const { t, lang, server, github } = data;
    const $ = (id) => document.getElementById(id);
    const SVG = 'http://www.w3.org/2000/svg';
    const RELEASES_SHOWN = 10;

    function el(tag, props, ...children) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(props || {})) {
            if (value === null || value === undefined || value === false) continue;
            if (key === 'class') node.className = value;
            else if (key === 'text') node.textContent = value;
            else node.setAttribute(key, value === true ? '' : String(value));
        }
        for (const child of children) {
            if (child !== null && child !== undefined && child !== false) node.append(child);
        }
        return node;
    }

    function svg(tag, attrs, ...children) {
        const node = document.createElementNS(SVG, tag);
        for (const [key, value] of Object.entries(attrs || {})) node.setAttribute(key, String(value));
        for (const child of children) if (child) node.append(child);
        return node;
    }

    const number = new Intl.NumberFormat(lang);
    const fmt = (template, value) => template.replace('%d', String(value)).replace('%s', String(value));
    const fmt2 = (template, a, b) => template.replace('%s', a).replace('%s', b);
    const dateLong = new Intl.DateTimeFormat(lang, { dateStyle: 'medium' });
    const dateTime = new Intl.DateTimeFormat(lang, { dateStyle: 'medium', timeStyle: 'short' });
    const dayShort = new Intl.DateTimeFormat(lang, { day: 'numeric', month: 'short' });
    const fromUnix = (s) => new Date(s * 1000);
    const fromDay = (d) => new Date(`${d}T12:00:00`);

    function plural(prefix, n) {
        const rule = new Intl.PluralRules(lang).select(n);
        return fmt(t[prefix + (rule === 'one' ? 'One' : rule === 'few' ? 'Few' : 'Many')], number.format(n));
    }

    const safeUrl = (url) => (typeof url === 'string' && /^https?:\/\//i.test(url) ? url : null);

    // ---------- readouts ----------

    function readout(value, label) {
        return el('div', { class: 'readout' },
            el('span', { class: 'readout__value', text: number.format(value) }),
            el('span', { class: 'readout__label', text: label }));
    }

    function renderReadouts() {
        const githubTotal = github.reduce((sum, repo) => sum + repo.total, 0);
        $('readouts').append(
            readout(server.total, t.statsServer),
            readout(server.recent, fmt(t.statsRecent, server.days)),
            data.githubConfigured ? readout(githubTotal, t.statsGithub) : null,
        );
    }

    // ---------- daily chart ----------

    function niceMax(value) {
        if (value <= 4) return 4;
        const magnitude = 10 ** Math.floor(Math.log10(value));
        for (const step of [1, 1.5, 2, 3, 4, 5, 6, 8, 10]) {
            if (step * magnitude >= value) return step * magnitude;
        }
        return value;
    }

    function renderChart() {
        const days = server.daily;
        // Draw at the real width so labels keep their size on phones.
        const W = Math.min(768, Math.max(300, $('chart').clientWidth || 640));
        const H = 180;
        const pad = { top: 12, right: 8, bottom: 26, left: 36 };
        const innerW = W - pad.left - pad.right;
        const innerH = H - pad.top - pad.bottom;
        const max = niceMax(Math.max(...days.map((d) => d.count)));
        const slot = innerW / days.length;
        const barW = Math.max(2, slot * 0.7);

        const chart = svg('svg', { viewBox: `0 0 ${W} ${H}`, role: 'img', 'aria-label': t.statsDaily, class: 'chart__svg' });

        for (const value of [0, max / 2, max].filter(Number.isInteger)) {
            const y = pad.top + innerH - (value / max) * innerH;
            chart.append(svg('line', { x1: pad.left, x2: W - pad.right, y1: y, y2: y, class: 'chart__grid' }));
            const label = svg('text', { x: pad.left - 6, y: y + 4, class: 'chart__axis', 'text-anchor': 'end' });
            label.textContent = number.format(value);
            chart.append(label);
        }

        days.forEach((day, i) => {
            const h = day.count === 0 ? 0 : Math.max(2, (day.count / max) * innerH);
            const x = pad.left + i * slot + (slot - barW) / 2;
            const title = svg('title');
            title.textContent = `${dateLong.format(fromDay(day.date))}: ${plural('downloads', day.count)}`;
            // Full-height hit area so empty days still show their tooltip.
            chart.append(svg('g', { class: 'chart__day' },
                svg('rect', { x: pad.left + i * slot, y: pad.top, width: slot, height: innerH, class: 'chart__hit' }),
                svg('rect', { x, y: pad.top + innerH - h, width: barW, height: h, rx: 1.5, class: 'chart__bar' }),
                title));
        });

        for (const i of [0, Math.floor(days.length / 2), days.length - 1]) {
            const label = svg('text', {
                x: pad.left + i * slot + slot / 2,
                y: H - 8,
                class: 'chart__axis',
                'text-anchor': i === 0 ? 'start' : i === days.length - 1 ? 'end' : 'middle',
            });
            label.textContent = dayShort.format(fromDay(days[i].date));
            chart.append(label);
        }

        $('chart').append(chart);
        if (server.since) $('since').textContent = fmt(t.statsSince, dateLong.format(fromUnix(server.since)));
    }

    // ---------- per package ----------

    function renderPackages() {
        const table = $('by-package');
        table.append(el('thead', null, el('tr', null,
            el('th', { scope: 'col', text: t.statsPackage }),
            el('th', { scope: 'col', class: 'num', text: t.statsTotal }),
            el('th', { scope: 'col', class: 'num', text: fmt(t.statsRecent, server.days) }),
            el('th', { scope: 'col', class: 'num', text: t.statsFromPage }),
            el('th', { scope: 'col', class: 'num', text: t.statsDirect }),
            el('th', { scope: 'col', text: t.statsLast }),
        )));

        for (const p of server.packages) {
            const body = el('tbody');
            body.append(el('tr', { class: 'stats-table__package' },
                el('th', { scope: 'row', text: p.displayName }),
                el('td', { class: 'num', text: number.format(p.total) }),
                el('td', { class: 'num', text: number.format(p.recent) }),
                el('td', { class: 'num', text: number.format(p.web) }),
                el('td', { class: 'num', text: number.format(p.direct) }),
                el('td', { text: dateTime.format(fromUnix(p.last)) }),
            ));
            if (p.files.length > 1) {
                for (const f of p.files) {
                    body.append(el('tr', { class: 'stats-table__file' },
                        el('td', null, el('code', { text: f.file })),
                        el('td', { class: 'num', text: number.format(f.total) }),
                        el('td', { class: 'num', text: number.format(f.recent) }),
                        el('td'), el('td'),
                        el('td', { text: dateTime.format(fromUnix(f.last)) }),
                    ));
                }
            }
            table.append(body);
        }
    }

    // ---------- GitHub ----------

    function releaseRow(release) {
        const url = safeUrl(release.url);
        const tag = url ? el('a', { href: url, target: '_blank', rel: 'noopener noreferrer', text: release.tag }) : release.tag;
        const files = el('ul', { class: 'assets' });
        for (const asset of release.assets) {
            files.append(el('li', null, el('code', { text: asset.name }), el('span', { class: 'num', text: number.format(asset.count) })));
        }
        return el('tr', null,
            el('th', { scope: 'row' }, tag, release.prerelease ? el('span', { class: 'status', text: t.statsPrerelease }) : null),
            el('td', { text: release.published ? dateLong.format(fromUnix(release.published)) : '' }),
            el('td', null, release.assets.length ? files : null),
            el('td', { class: 'num', text: number.format(release.total) }),
        );
    }

    function renderRepo(repo) {
        const section = el('div', { class: 'repo' });
        const link = safeUrl(repo.url);
        section.append(el('h3', { class: 'repo__title' },
            link ? el('a', { href: link, target: '_blank', rel: 'noopener noreferrer', text: repo.repo }) : repo.repo,
            el('span', { class: 'repo__total', text: plural('downloads', repo.total) })));

        const note = repo.error
            ? fmt2(t.statsGithubError, repo.error, repo.fetched ? dateTime.format(fromUnix(repo.fetched)) : '—')
            : fmt(t.statsGithubChecked, dateTime.format(fromUnix(repo.checked)));
        section.append(el('p', { class: repo.error ? 'stats-note stats-note--warn' : 'stats-note', text: note }));

        if (repo.releases.length === 0) {
            if (!repo.error) section.append(el('p', { class: 'stats-note', text: t.statsGithubEmpty }));
            return section;
        }

        const body = el('tbody');
        const rows = repo.releases.map(releaseRow);
        rows.forEach((row, i) => { row.hidden = i >= RELEASES_SHOWN; body.append(row); });

        section.append(el('div', { class: 'table-scroll' }, el('table', { class: 'stats-table' },
            el('thead', null, el('tr', null,
                el('th', { scope: 'col', text: t.statsRelease }),
                el('th', { scope: 'col', text: t.statsPublished }),
                el('th', { scope: 'col', text: t.statsFiles }),
                el('th', { scope: 'col', class: 'num', text: t.statsTotal }),
            )),
            body)));

        if (rows.length > RELEASES_SHOWN) {
            const more = el('button', { type: 'button', class: 'button button--quiet', text: fmt(t.statsShowAll, rows.length) });
            more.addEventListener('click', () => {
                rows.forEach((row) => { row.hidden = false; });
                more.remove();
            });
            section.append(more);
        }
        return section;
    }

    function renderGithub() {
        const block = $('github');
        if (!data.githubConfigured) {
            block.append(el('p', { class: 'stats-note', text: t.statsGithubNone }));
            return;
        }
        for (const repo of github) block.append(renderRepo(repo));
    }

    // ---------- start ----------

    document.querySelectorAll('#bays .bay').forEach((bay, i) => bay.style.setProperty('--i', String(i)));
    $('bays').classList.add('is-booting');

    renderReadouts();
    if (server.total > 0) {
        $('server').hidden = false;
        renderChart();
        renderPackages();
    } else {
        $('server-empty').textContent = t.statsNone;
        $('server-empty').hidden = false;
    }
    renderGithub();
})();
