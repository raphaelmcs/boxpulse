import assert from 'node:assert/strict';
import {execFileSync, spawn} from 'node:child_process';
import {randomInt, randomUUID} from 'node:crypto';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import {createServer, request} from 'node:http';
import {tmpdir} from 'node:os';
import {join, relative} from 'node:path';
import {setTimeout as delay} from 'node:timers/promises';

async function waitFor(check, label) {
    for (let attempt = 0; attempt < 150; attempt++) {
        if (await check()) return;
        await delay(60);
    }
    throw new Error('Tempo excedido: ' + label);
}

async function openTab(address, sockets, errors, targetId) {
    const webSocketDebuggerUrl = targetId
        ? address.replace(/^http/, 'ws') + '/devtools/page/' + targetId
        : (await (await fetch(address + '/json/new?about:blank', {method: 'PUT'})).json()).webSocketDebuggerUrl;
    const socket = new WebSocket(webSocketDebuggerUrl);
    sockets.push(socket);
    const pending = new Map();
    const waiters = new Map();
    const mutations = [];
    let id = 0;
    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        const waiting = waiters.get(message.method);
        if (waiting) {
            waiters.delete(message.method);
            waiting();
        }
        if (message.method === 'Runtime.exceptionThrown') {
            errors.push(message.params.exceptionDetails.exception?.description ?? message.params.exceptionDetails.text);
        }
        if (message.method === 'Network.requestWillBeSent') {
            const {method, url} = message.params.request;
            if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) mutations.push(new URL(url).pathname);
        }
        const command = pending.get(message.id);
        if (!command) return;
        pending.delete(message.id);
        clearTimeout(command.timeout);
        if (message.error) command.reject(new Error(message.error.message));
        else command.resolve(message.result);
    });
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, {once: true});
        socket.addEventListener('error', reject, {once: true});
    });
    function send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const requestId = ++id;
            const timeout = setTimeout(() => {
                pending.delete(requestId);
                reject(new Error('Chrome não respondeu a ' + method));
            }, 15000);
            pending.set(requestId, {resolve, reject, timeout});
            socket.send(JSON.stringify({method, params, id: requestId}));
        });
    }
    for (const method of ['Page.enable', 'Runtime.enable', 'Network.enable']) await send(method);
    async function evaluate(fn, ...args) {
        const expression = '(' + fn.toString() + ')(...' + JSON.stringify(args) + ')';
        const result = await send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true, userGesture: true});
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
        return result.result.value;
    }
    async function visit(url, selector) {
        const loaded = new Promise(resolve => { waiters.set('Page.loadEventFired', resolve); });
        await send('Page.navigate', {url});
        await Promise.race([loaded, delay(10000, null, {ref: false})]);
        await waitFor(() => evaluate(selector => !!document.querySelector(selector), selector), new URL(url).pathname);
    }
    const resize = (width, height) => send('Emulation.setDeviceMetricsOverride', {
        width, height, screenWidth: width, screenHeight: height, deviceScaleFactor: 1, mobile: false,
    });
    return {send, evaluate, visit, resize, mutations};
}

async function openGuestTab(address, sockets, errors) {
    const {webSocketDebuggerUrl} = await (await fetch(address + '/json/version')).json();
    const browser = new WebSocket(webSocketDebuggerUrl);
    sockets.push(browser);
    await new Promise((resolve, reject) => {
        browser.addEventListener('open', resolve, {once: true});
        browser.addEventListener('error', reject, {once: true});
    });
    let id = 0;
    function send(method, params) {
        return new Promise((resolve, reject) => {
            const requestId = ++id;
            function receive(event) {
                const data = JSON.parse(event.data);
                if (data.id !== requestId) return;
                browser.removeEventListener('message', receive);
                if (data.error) reject(new Error(data.error.message));
                else resolve(data.result);
            }
            browser.addEventListener('message', receive);
            browser.send(JSON.stringify({id: requestId, method, params}));
        });
    }
    const {browserContextId} = await send('Target.createBrowserContext');
    const {targetId} = await send('Target.createTarget', {url: 'about:blank', browserContextId});
    return openTab(address, sockets, errors, targetId);
}

function browserMeasurements(selector) {
    const screen = document.querySelector(selector);
    const panel = screen.querySelector('.session-content');
    const area = screen.querySelector('[data-content-pages]').getBoundingClientRect();
    const lines = [...screen.querySelectorAll('.adaptive-instruction')];
    const score = screen.querySelector('.scoreboard');
    const timing = screen.querySelector('[data-session-timing]');
    const text = query => screen.querySelector(query)?.textContent;
    const heading = screen.querySelector('[data-block-title]');
    const styledLine = lines[0]?.querySelector('span');
    const loads = screen.querySelector('[data-suggested-loads]');
    const showLoads = getComputedStyle(loads).display !== 'none';
    const loadNotice = screen.querySelector('[data-load-capacity-warning]');
    const logo = screen.querySelector('[data-live-logo]');
    const canvas = screen.getBoundingClientRect();
    const fitsRect = (rect, parent) => rect.top >= parent.top - 1 && rect.left >= parent.left - 1
        && rect.right <= parent.right + 1 && rect.bottom <= parent.bottom + 1;
    return {
        excess: screen.dataset.contentExcess,
        ratio: timing.offsetWidth / (timing.offsetWidth + panel.offsetWidth),
        logicalWidth: screen.clientWidth,
        logicalHeight: screen.clientHeight,
        drawnAspect: canvas.width / canvas.height,
        font: parseFloat(panel.style.getPropertyValue('--adaptive-font')),
        grid: screen.dataset.contentColumns,
        boardHeight: screen.querySelector('.session-board').clientHeight,
        contentWidth: panel.clientWidth,
        contentHeight: screen.querySelector('[data-content-pages]').clientHeight,
        lines: lines.map(line => line.textContent),
        fullyVisible: lines.every(line => fitsRect(line.getBoundingClientRect(), area)),
        infoFits: fitsRect(heading.getBoundingClientRect(), panel.getBoundingClientRect()),
        panelColor: getComputedStyle(panel).backgroundColor,
        scoreFits: fitsRect(screen.querySelector('[data-timer-digits]').getBoundingClientRect(), score.getBoundingClientRect())
            && fitsRect(score.getBoundingClientRect(), timing.getBoundingClientRect()),
        timerFont: parseFloat(getComputedStyle(screen.querySelector('[data-timer-digits]')).fontSize),
        header: ['.tv-frame', '.tv-header', '.tv-logo', '.session-stages', '.session-stages li', '.tv-footer', '.tv-date'].map(query => {
            const element = screen.querySelector(query);
            const style = getComputedStyle(element);
            return [query, element.clientHeight, style.fontSize, style.lineHeight, style.gridTemplateRows, style.marginTop, style.marginBottom];
        }),
        heading: heading.textContent,
        headingColor: getComputedStyle(heading).color,
        weight: getComputedStyle(heading).fontWeight,
        lineColor: styledLine ? getComputedStyle(styledLine).color : null,
        logo: !!(logo?.complete && logo.naturalWidth > 0),
        accent: getComputedStyle(screen).getPropertyValue('--box-primary').trim().toLowerCase(),
        loads: showLoads ? [...loads.querySelectorAll('.suggested-load-card:not([hidden]) .suggested-load-amount')].map(item => item.textContent.trim()) : null,
        loadExcess: screen.dataset.loadExcess,
        loadWarning: !loadNotice.hidden,
        loadWarningFits: loadNotice.hidden || fitsRect(loadNotice.getBoundingClientRect(), timing.getBoundingClientRect()),
        loadDensity: timing.dataset.loadDensity,
        loadNames: showLoads ? [...loads.querySelectorAll('[data-load-name]')].map(item => item.textContent.trim()) : [],
        loadsFit: !showLoads || (timing.contains(loads) && !panel.contains(loads)
            && loads.getBoundingClientRect().top >= score.getBoundingClientRect().bottom
            && fitsRect(loads.getBoundingClientRect(), timing.getBoundingClientRect())
            && [...loads.querySelectorAll('.suggested-load-card:not([hidden])')].every(card =>
                card.scrollWidth <= card.clientWidth + 1 && fitsRect(card.getBoundingClientRect(), loads.getBoundingClientRect()))),
        flag: !screen.querySelector('[data-content-capacity-warning]').hidden,
        format: text('[data-timer-format]'),
        digits: text('[data-timer-digits]'),
        options: text('[data-timer-options]'),
        rounds: text('[data-round]'),
        scroll: document.scrollingElement.scrollHeight > innerHeight + 1 || document.scrollingElement.scrollWidth > innerWidth + 1,
    };
}

function contrast(foreground, background) {
    const luminance = color => color.match(/\d+/g).map(Number).map(channel => channel / 255)
        .map(channel => channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4)
        .reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
    const [light, dark] = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return (light + .05) / (dark + .05);
}

function loadCardPalette(selector) {
    const panel = document.querySelector(selector);
    return [...panel.querySelectorAll('[data-load-row]')].flatMap(row => [...row.querySelectorAll('[data-load-level]:not([hidden])')]).map(card => {
        const label = card.querySelector('span');
        const value = card.querySelector('[data-load-value]');
        return {
            level: card.dataset.loadLevel,
            label: label.textContent,
            background: getComputedStyle(card).backgroundColor,
            labelColor: getComputedStyle(label).color,
            valueColor: getComputedStyle(value).color,
            labelSize: parseFloat(getComputedStyle(label).fontSize),
            valueSize: parseFloat(getComputedStyle(value).fontSize),
        };
    });
}

const palette = {
    rx: ['RX', 'rgb(48, 48, 57)', 'rgb(241, 245, 249)'],
    intermediate: ['INTER', 'rgb(42, 53, 38)', 'rgb(234, 197, 79)'],
    scale: ['SCALE', 'rgb(38, 53, 77)', 'rgb(130, 183, 255)'],
};
function assertPalette(cards) {
    assert.deepEqual([...new Set(cards.map(card => card.level))].sort(), Object.keys(palette).sort());
    for (const card of cards) {
        const [label, background, foreground] = palette[card.level];
        assert.deepEqual([card.label, card.background, card.labelColor, card.valueColor], [label, background, foreground, foreground]);
        assert.ok(contrast(card.valueColor, card.background) >= 4.5);
        assert.ok(card.valueSize >= card.labelSize * 1.5);
    }
}

async function screenshot(tab, name) {
    const directory = process.env.BOXPULSE_SCREENSHOT_DIR;
    if (!directory) return;
    await mkdir(directory, {recursive: true});
    await tab.send('Page.bringToFront');
    const {data} = await tab.send('Page.captureScreenshot', {format: 'png'});
    await writeFile(join(directory, name + '.png'), Buffer.from(data, 'base64'));
}

const docker = ['compose', 'exec', '-T', '-e', 'APP_ENV=local', '-e', 'DB_HOST=mysql', '-e', 'DB_DATABASE=testing',
    '-e', 'DB_URL=', '-e', 'SESSION_DRIVER=file', '-e', 'CACHE_STORE=file', 'laravel.test'];
const run = (...args) => execFileSync('docker', [...docker, ...args], {encoding: 'utf8'});
const database = run('php', 'artisan', 'config:show', 'database.connections.mysql.database', '--no-interaction').trim();
assert.match(database, /\btesting$/, 'A homologação exige o banco MySQL testing.');
assert.match(run('php', 'artisan', 'config:show', 'database.connections.mysql.host', '--no-interaction').trim(), /\bmysql$/);
const upstream = run('hostname', '-i').trim().split(/\s+/)[0];
const port = randomInt(20000, 60000);
const storage = await mkdtemp(join(process.cwd(), 'storage/framework/testing/tv-preview-'));
for (const directory of ['framework/sessions', 'framework/views', 'framework/cache/data', 'logs']) await mkdir(join(storage, directory), {recursive: true});
const proxy = createServer((incoming, outgoing) => {
    const forwarded = request({hostname: upstream, port, method: incoming.method, path: incoming.url, headers: incoming.headers}, response => {
        outgoing.writeHead(response.statusCode, response.headers);
        response.pipe(outgoing);
    });
    forwarded.on('error', () => { outgoing.writeHead(502).end(); });
    incoming.pipe(forwarded);
});
await new Promise((resolve, reject) => proxy.listen(0, '127.0.0.1').once('listening', resolve).once('error', reject));
const origin = 'http://127.0.0.1:' + proxy.address().port;
const serverDocker = [...docker.slice(0, -1), '--user', process.getuid() + ':' + process.getgid(), '-e',
    'LARAVEL_STORAGE_PATH=/var/www/html/' + relative(process.cwd(), storage), 'laravel.test'];
const server = spawn('docker', [...serverDocker, 'php', 'artisan', 'serve', '--host=0.0.0.0', '--port=' + port, '--no-reload', '--no-interaction'], {stdio: ['ignore', 'pipe', 'pipe']});
let serverOutput = '';
for (const stream of [server.stdout, server.stderr]) stream.on('data', chunk => { serverOutput += chunk; });
const profile = await mkdtemp(join(tmpdir(), 'boxpulse-tv-browser-'));
const chrome = spawn(process.env.BOXPULSE_CHROME || 'google-chrome',
    ['--headless', '--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile],
    {stdio: 'ignore'});
const sockets = [], errors = [];
try {
    await waitFor(async () => {
        if (server.exitCode !== null) throw new Error(serverOutput);
        return serverOutput.includes('http://0.0.0.0:' + port) && (await fetch(origin + '/up')).ok;
    }, 'servidor do Sail');

    let address;
    await waitFor(async () => {
        const file = await readFile(join(profile, 'DevToolsActivePort'), 'utf8').catch(() => '');
        if (!file) return false;
        address = 'http://127.0.0.1:' + file.split('\n')[0];
        return true;
    }, 'Chrome');
    const editor = await openTab(address, sockets, errors);
    const tv = await openTab(address, sockets, errors);
    await editor.resize(1440, 1000);
    await editor.visit(origin + '/register', '#username');
    await editor.evaluate(async (username, password) => {
        for (const [key, value] of Object.entries({box_name: 'Box QA TV', head_coach_name: 'Coach QA', name: 'Admin QA', username,
            password, password_confirmation: password, primary_color: '#5266F4', secondary_color: '#C5E75F'})) {
            document.querySelector('[name="' + key + '"]').value = value;
        }
        const canvas = document.createElement('canvas');
        canvas.width = 60;
        canvas.height = 30;
        canvas.getContext('2d').fillRect(0, 0, 60, 30);
        const transfer = new DataTransfer();
        transfer.items.add(new File([await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))], 'logo-qa.png', {type: 'image/png'}));
        document.querySelector('input[name=logo]').files = transfer.files;
        document.querySelector('[name="modalities[]"]').checked = true;
        document.querySelector('form[data-registration-theme]').requestSubmit();
    }, 'tv-qa-' + randomUUID().slice(0, 8), 'TvQa9-' + randomUUID());
    await waitFor(() => editor.evaluate(() => location.pathname === '/workspace'), 'cadastro sintético');
    const schedule = origin + '/schedule?date=2026-12-10&modality=crossfit';
    await editor.visit(schedule, '.schedule-month');
    for (const [width, height] of [[1920, 1080], [1440, 900], [1366, 768], [768, 1024], [390, 844]]) {
        await editor.resize(width, height);
        const layout = await editor.evaluate(() => {
            const calendar = document.querySelector('.schedule-month').getBoundingClientRect();
            const main = document.querySelector('.schedule-main').getBoundingClientRect();
            return {
                sidebar: calendar.left >= main.right,
                stacked: calendar.top >= main.bottom,
                noOverflow: document.scrollingElement.scrollWidth <= innerWidth + 1,
                columns: getComputedStyle(document.querySelector('.week-grid')).gridTemplateColumns.split(' ').length,
                days: document.querySelectorAll('.week-day').length,
            };
        });
        assert.equal(layout.sidebar, width >= 1366, JSON.stringify({width, layout}));
        assert.equal(layout.stacked, width < 1366);
        assert.ok(layout.noOverflow);
        assert.equal(layout.days, 7);
        if (width >= 1366) assert.equal(layout.columns, 7);
    }
    await editor.resize(1440, 1000);
    const nextMonth = await editor.evaluate(() => document.querySelector('.schedule-month [aria-label="Próximo mês"]').href);
    await editor.visit(nextMonth, '.schedule-month');
    assert.match(await editor.evaluate(() => document.querySelector('#schedule-month-title').textContent), /janeiro 2027/);
    assert.equal(await editor.evaluate(() => document.querySelector('.week-day.is-selected').dataset.date), '2026-12-10');
    const dateLink = await editor.evaluate(() => document.querySelector('[data-month-date="2027-01-13"]').href);
    await editor.visit(dateLink, '.schedule-month');
    assert.equal(await editor.evaluate(() => document.querySelector('.week-day').dataset.date), '2027-01-11');
    assert.equal(await editor.evaluate(() => document.querySelector('#modality-filter').value), 'crossfit');
    assert.equal(await editor.evaluate(() => document.querySelectorAll('.schedule-month .month-in-week').length), 7);
    await editor.visit(schedule, '.schedule-month');
    const initial = await editor.evaluate(() => document.querySelector('.week-day.is-selected .add-day').href);
    assert.equal(new URL(initial).searchParams.get('structure'), 'full');
    assert.equal(new URL(initial).searchParams.get('date'), '2026-12-10');
    console.log('Chrome: calendário lateral em três desktops, layout empilhado no tablet e telefone, filtro e navegação por mês/data.');

    await editor.visit(initial, '[data-workout-form]');
    assert.equal(await editor.evaluate(() => document.querySelector('[data-presentation-check]').open), false);
    assert.equal(await editor.evaluate(() => document.querySelectorAll('[data-open-tv-preview]').length), 1);
    assert.equal(await editor.evaluate(() => !!document.querySelector('[data-capacity-resolution], [name^="presentation_options"]')), false);
    await editor.evaluate(() => document.querySelector('[data-open-tv-preview]').click());
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-presentation-check]').dataset.capacity === 'empty'), 'prévia vazia do novo treino');
    assert.match(await editor.evaluate(() => document.querySelector('[data-capacity-result]').textContent), /Preencha uma etapa/);
    await editor.evaluate(() => document.querySelector('[data-close-tv-preview]').click());
    assert.equal(await editor.evaluate(() => document.querySelector('[data-presentation-check]').open), false);
    await editor.send('Page.bringToFront');
    const before = editor.mutations.length;
    await editor.evaluate(() => {
        const original = window.fetch.bind(window);
        let resume;
        const ready = new Promise(resolve => { resume = resolve; });
        window.fetch = (url, options) => options?.method === 'POST' && new URL(url, location.href).pathname === '/workouts'
            ? ready.then(() => original(url, options)) : original(url, options);
        window.resumeTestSave = () => { window.fetch = original; resume(); };
        document.querySelector('[data-block-toggle="warmup"]').click();
        const title = document.querySelector('[data-stage="warmup"] [data-edit-text="title"]');
        title.value = 'Aquecimento ainda no formulário';
        title.dispatchEvent(new Event('input', {bubbles: true}));
        document.querySelector('[data-open-tv-preview]').click();
    });
    await waitFor(() => editor.evaluate(() => [...document.querySelector('[data-preview-block]').options].some(option => option.textContent.includes('ainda no formulário'))), 'prévia do rascunho local');
    await editor.evaluate(() => {
        const select = document.querySelector('[data-preview-block]');
        select.value = 'warmup';
        select.dispatchEvent(new Event('change'));
    });
    assert.equal(await editor.evaluate(() => document.querySelector('[data-preview-screen] [data-block-title]').textContent), 'Aquecimento ainda no formulário');
    assert.equal(editor.mutations.length, before, 'A prévia atualizou antes do autosave.');
    await editor.send('Input.dispatchKeyEvent', {type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27});
    await editor.send('Input.dispatchKeyEvent', {type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27});
    await waitFor(() => editor.evaluate(() => !document.querySelector('[data-presentation-check]').open), 'fechar modal com Escape');
    assert.equal(await editor.evaluate(() => document.activeElement.matches('[data-open-tv-preview]')), true);
    assert.equal(await editor.evaluate(() => document.querySelector('[data-stage="warmup"] [data-edit-text="title"]').value), 'Aquecimento ainda no formulário');
    await editor.resize(390, 844);
    await editor.evaluate(() => document.querySelector('[data-open-tv-preview]').click());
    assert.ok(await editor.evaluate(() => {
        const modal = document.querySelector('[data-presentation-check]').getBoundingClientRect();
        const viewport = document.querySelector('[data-preview-viewport]').getBoundingClientRect();
        return modal.left >= 0 && modal.right <= innerWidth && modal.bottom <= innerHeight
            && Math.abs(viewport.width / viewport.height - 16 / 9) < .01;
    }));
    await editor.resize(1440, 1000);
    await editor.send('Page.bringToFront');
    await editor.evaluate(() => document.querySelector('[data-preview-fullscreen]').click());
    await waitFor(() => editor.evaluate(() => document.fullscreenElement?.matches('[data-preview-region]')), 'tela cheia da prévia');
    await editor.evaluate(() => document.exitFullscreen());
    await editor.evaluate(() => { document.querySelector('[data-close-tv-preview]').click(); window.resumeTestSave(); });
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-save-feedback]').dataset.state === 'saved'), 'autosave do rascunho sintético');
    const editorUrl = await editor.evaluate(() => location.href);
    await editor.evaluate(() => {
        document.querySelector('[data-block-toggle="skill"]').click();
        const skill = document.querySelector('[data-stage="skill"]');
        const timer = skill.querySelector('[data-timer-type]');
        timer.value = 'emom';
        timer.dispatchEvent(new Event('change', {bubbles: true}));
        const duration = skill.querySelector('[name$="[duration_minutes]"]');
        duration.value = '2';
        duration.dispatchEvent(new Event('input', {bubbles: true}));
    });
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-save-feedback]').dataset.state === 'saved'), 'etapa opcional no rascunho');
    await editor.visit(editorUrl, '[data-workout-form]');
    assert.equal(await editor.evaluate(() => document.querySelector('[data-block-toggle="skill"]').checked), true);
    assert.equal(await editor.evaluate(() => document.querySelector('[data-stage="skill"] [name$="[duration_minutes]"]').value), '2');
    await editor.evaluate(() => document.querySelector('[data-open-tv-preview]').click());
    assert.deepEqual(await editor.evaluate(() => [...document.querySelector('[data-preview-block]').options].map(item => item.value)), ['warmup']);
    await editor.evaluate(() => {
        document.querySelector('[data-close-tv-preview]').click();
        const form = document.querySelector('[data-workout-form]');
        const edit = (type, title) => { form.querySelector(`[data-stage="${type}"] [data-edit-text="title"]`).value = title; };
        edit('warmup', '');
        edit('skill', 'Técnica QA');
        document.querySelector('[data-open-tv-preview]').click();
    });
    const currentStage = () => editor.evaluate(() => ({
        options: [...document.querySelector('[data-preview-block]').options].map(option => option.value),
        title: document.querySelector('[data-preview-screen] [data-block-title]').textContent,
    }));
    assert.deepEqual(await currentStage(), {options: ['skill'], title: 'Técnica QA'});
    await editor.evaluate(() => {
        document.querySelector('[data-close-tv-preview]').click();
        document.querySelector('[data-stage="workout"] [data-edit-text="title"]').value = 'WOD QA';
        document.querySelector('[data-open-tv-preview]').click();
        const select = document.querySelector('[data-preview-block]');
        select.value = 'workout';
        select.dispatchEvent(new Event('change'));
    });
    assert.deepEqual(await currentStage(), {options: ['skill', 'workout'], title: 'WOD QA'});
    await editor.evaluate(() => {
        document.querySelector('[data-close-tv-preview]').click();
        document.querySelector('[data-stage="warmup"] [data-edit-text="title"]').value = 'Aquecimento ainda no formulário';
        document.querySelector('[data-open-tv-preview]').click();
    });
    assert.deepEqual(await currentStage(), {options: ['warmup', 'skill', 'workout'], title: 'WOD QA'});
    await editor.visit(editorUrl, '[data-workout-form]');
    await editor.evaluate(() => {
        const previousConfirm = window.confirm;
        try {
            window.confirm = () => true;
            document.querySelector('[data-block-toggle="skill"]').click();
        } finally {
            window.confirm = previousConfirm;
        }
    });
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-save-feedback]').dataset.state === 'saved'), 'remoção da etapa');
    await editor.visit(editorUrl, '[data-workout-form]');
    assert.equal(await editor.evaluate(() => document.querySelector('[data-block-toggle="skill"]').checked), false);
    const returnUrl = await editor.evaluate(() => document.querySelector('[data-workout-schedule]').href);
    await editor.visit(returnUrl, '.schedule-month');
    assert.equal(await editor.evaluate(() => document.querySelector('.week-day.is-selected').dataset.date), '2026-12-10');
    assert.ok(await editor.evaluate(() => !!document.querySelector('[data-month-date="2026-12-10"] .month-workouts-dot')));
    await editor.visit(editorUrl, '[data-workout-form]');
    console.log('Chrome: abertura, fechamento e foco no modal, conteúdo anterior ao autosave, etapas opcionais, celular e tela cheia.');

    const guest = await openGuestTab(address, sockets, errors);
    const settings = origin + '/box/settings';
    await editor.send('Page.bringToFront');
    await editor.visit(settings, '#box-tv-settings-title');
    await waitFor(() => editor.evaluate(() => [...document.querySelector('[data-library-equipment]').options].some(item => item.value === 'Barbell')), 'catálogo de movimentos nas configurações');
    await editor.evaluate(() => {
        const equipment = document.querySelector('[data-library-equipment]');
        equipment.value = 'Barbell';
        equipment.dispatchEvent(new Event('change'));
        const search = document.querySelector('[data-library-search]');
        search.value = 'Power Clean';
        search.dispatchEvent(new Event('input'));
    });
    assert.deepEqual(await editor.evaluate(() => [...document.querySelectorAll('[data-library-list] h3')].map(title => title.textContent)), ['Power Clean']);
    await editor.evaluate(() => {
        document.querySelector('[data-library-list] .library-movement > button').click();
        const preset = document.querySelector('[data-library-edit]');
        preset.querySelector('[data-preset-field="name"]').value = 'Power Clean técnico';
        preset.querySelector('[data-preset-field="context"]').value = 'technique';
        preset.querySelector('[data-preset-level="rx.male"]').value = '22,5';
        preset.querySelector('[data-preset-level="rx.female"]').value = '15';
        preset.querySelector('[data-library-save]').click();
    });
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-library-list]').textContent.includes('22,5 / 15 kg')), 'preset decimal para movimento específico');
    await editor.evaluate(() => {
        document.querySelector('[data-movement-new]').click();
        document.querySelector('[data-movement-field="name"]').value = 'Clean Pausado QA';
        document.querySelector('[data-movement-field="aliases"]').value = 'PCQA';
        document.querySelector('[data-movement-save]').click();
    });
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-library-feedback]').textContent === 'Movimento salvo.'), 'cadastro de movimento da box');
    await editor.evaluate(async () => {
        const form = document.querySelector('.box-tv-settings form');
        const response = await fetch(form.action, {method: 'POST', body: new FormData(form)});
        if (!response.ok) throw new Error('Geração do link da TV falhou.');
    });
    await editor.visit(settings, '[data-box-tv-link]');
    const tvLink = await editor.evaluate(() => document.querySelector('[data-box-tv-link]').value);
    await editor.evaluate(() => document.querySelector('[data-copy-box-tv]').click());
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-box-tv-copy-feedback]').textContent === 'Link copiado.'), 'cópia do link da TV');
    await guest.resize(1920, 1080);
    await guest.send('Page.bringToFront');
    await guest.visit(tvLink, '[data-view="box-tv"]');
    const guestStatus = () => guest.evaluate(() => document.querySelector('[data-view="box-tv"]').dataset.tvStatus);
    const guestTimer = () => guest.evaluate(() => document.querySelector('[data-view="box-tv"]').dataset.timerState);
    await waitFor(() => guest.evaluate(() => document.querySelector('[data-live-logo]').naturalWidth > 0), 'identidade da TV do Box');
    assert.equal(await guestStatus(), 'waiting');
    assert.equal((await guest.send('Network.getCookies', {urls: [origin]})).cookies.length, 0);
    assert.equal(await guest.evaluate(() => !!document.querySelector('[data-command-url], [data-lease-url], a[href^="/sessions/"]')), false);
    assert.match(await guest.evaluate(() => document.querySelector('[data-live-clock]').textContent), /^\d{2}:\d{2}:\d{2}$/);

    await editor.visit(editorUrl, '[data-workout-form]');
    await editor.evaluate(() => {
        const warmup = document.querySelector('[data-stage="warmup"]');
        warmup.querySelector('[data-edit-text="description"]').value = 'Técnica\n20 POWER CLEANS\n5 FRONT SQUATS 70%\n10 DB SNATCH 22,5 / 15 KG';
        warmup.querySelector('[data-load-analyze]').click();
    });
    await waitFor(() => editor.evaluate(() => document.querySelectorAll('[data-stage="warmup"] .load-review-item').length === 3), 'análise com variação, percentual e prescrição explícita');
    assert.deepEqual(await editor.evaluate(() => [...document.querySelectorAll('[data-stage="warmup"] .load-review-item')].map(row => row.querySelector('input[maxlength="60"]').value)), ['22,5 / 15 kg', '', '22,5 / 15 kg']);
    await editor.evaluate(() => {
        const review = document.querySelector('[data-stage="warmup"] [data-load-review]');
        review.querySelector('input[type="checkbox"]').click();
        [...review.querySelectorAll('button')].find(button => button.textContent === 'Aplicar sugestões selecionadas').click();
    });
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-save-feedback]').dataset.state === 'saved'), 'autosave das cargas revisadas');
    console.log('Chrome: catálogo por equipamento e movimento, carga decimal, movimento da box, revisão e autosave de cargas.');
    const publicClass = await editor.evaluate(async () => {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const headers = {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token};
        const input = {
            title: 'Aula exclusiva da TV', scheduled_for: '2026-11-23',
            training_modality_id: document.querySelector('[name="training_modality_id"]').value, structure: 'full',
            editor_mode: 'publish', expected_edit_version: 0, editor_write_id: crypto.randomUUID(),
            blocks: [
                {type: 'warmup', title: 'Intervalos', description: 'Bike\nKettlebell Swing', timer_type: 'tabata',
                    timer_settings: {work_seconds: 4, rest_seconds: 4, rounds: 2},
                    load_entries: [{equipment: 'Kettlebell', movement: 'Swing', rx: '16', unit: 'kg'}]},
                {type: 'skill', title: 'Técnica sem cargas', description: 'Prática livre', timer_type: 'none'},
                {type: 'workout', title: 'Exercícios da aula', description: '10 Dumbbell Snatches', timer_type: 'for_time', duration_seconds: 120,
                    load_entries: [{equipment: 'Dumbbell', movement: 'Snatch', rx: '22 / 16 kg', unit: 'kg', implement: 'double'}],
                    text_style: {title: {font_weight: 'bold', text_color: '#facc15'}}},
            ],
        };
        const created = await fetch('/workouts', {method: 'POST', headers, body: JSON.stringify(input)});
        if (!created.ok) throw new Error('Falha ao publicar a aula sintética da TV.');
        const {update_url} = await created.json();
        const prepared = await fetch(update_url + '/sessions', {method: 'POST', body: new URLSearchParams({_token: token, preparation_seconds: '6'})});
        return prepared.url.replace(/\/control$/, '');
    });
    assert.match(publicClass, /\/sessions\/\d+$/);
    assert.equal(await guestStatus(), 'waiting');
    const coach = await openTab(address, sockets, errors);
    await coach.visit(publicClass + '/control', '[data-view="control"]');
    await guest.send('Page.bringToFront');
    const available = action => coach.evaluate(action => !!document.querySelector(`[data-session-command="${action}"]:not([hidden]):not([disabled])`), action);
    const coachCommand = async action => {
        await waitFor(() => available(action), 'controle do coach disponível para ' + action);
        await coach.evaluate(action => {
            window.confirm = () => true;
            document.querySelector(`[data-session-command="${action}"]`).click();
        }, action);
        await waitFor(() => coach.evaluate(() => document.querySelector('[data-session-feedback]').textContent === 'Comando confirmado.'), 'resposta do coach para ' + action);
    };
    assert.equal(await available('finish'), false);
    await coachCommand('start');
    await waitFor(async () => await guestStatus() === 'active' && await guestTimer() === 'preparing', 'preparação automática na TV do Box');
    const loaded = state => state.evaluate(() => !document.querySelector('[data-suggested-loads]').hidden);
    assert.equal(await loaded(guest), true);
    assert.equal(await loaded(coach), true);
    await coachCommand('pause');
    await waitFor(async () => await guestTimer() === 'paused', 'pausa na TV do Box');
    await guest.send('Network.emulateNetworkConditions', {offline: true, latency: 0, downloadThroughput: 0, uploadThroughput: 0});
    await coachCommand('resume');
    await waitFor(() => guest.evaluate(() => document.querySelector('[data-view="box-tv"]').dataset.online === 'false'), 'aviso de reconexão da TV do Box');
    await guest.send('Network.emulateNetworkConditions', {offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1});
    await waitFor(async () => await guestTimer() === 'running', 'retomada confirmada após reconexão da TV do Box');
    await waitFor(() => guest.evaluate(() => document.querySelector('[data-view="box-tv"]').dataset.phase === 'rest'), 'intervalo da aula na TV do Box');
    await waitFor(async () => await guestTimer() === 'completed', 'etapa concluída na TV do Box');
    await coachCommand('next');
    await waitFor(async () => !await loaded(guest), 'retirar cargas ao abrir etapa sem referências');
    assert.equal(await loaded(coach), false);
    await coachCommand('next');
    assert.equal(await available('next'), false);
    assert.equal(await available('finish'), false);
    await waitFor(() => guest.evaluate(() => document.querySelector('[data-content-pages]').textContent.includes('10 Dumbbell Snatches')), 'troca automática da tela ao próximo bloco');
    const tvPanel = await guest.evaluate(browserMeasurements, '[data-view="box-tv"]');
    assert.ok(Math.abs(tvPanel.ratio - .35) < .002 && !tvPanel.scroll);
    assert.ok(tvPanel.loads.some(load => load.includes('22 / 16 kg')));
    assert.match(await guest.evaluate(() => document.querySelector('[data-live-load-entries]').textContent), /Duplo · por unidade/);
    assert.equal(tvPanel.headingColor, 'rgb(250, 204, 21)');
    let previousFont = tvPanel.font;
    for (const [width, height] of [[2560, 1440], [3840, 2160]]) {
        await guest.resize(width, height);
        await waitFor(async () => (await guest.evaluate(browserMeasurements, '[data-view="box-tv"]')).font > previousFont, 'tipografia responsiva da TV do Box');
        const scaled = await guest.evaluate(browserMeasurements, '[data-view="box-tv"]');
        assert.equal(scaled.logicalWidth, width);
        assert.equal(scaled.logicalHeight, height);
        assert.ok(Math.abs(scaled.ratio - .35) < .002 && !scaled.scroll && scaled.fullyVisible);
        assert.deepEqual(scaled.lines, tvPanel.lines);
        previousFont = scaled.font;
    }
    await coachCommand('complete_block');
    assert.equal(await coach.evaluate(() => document.querySelector('[data-session-command="finish"]').textContent.trim()), 'Finalizar aula ✓');
    await coachCommand('finish');
    await coach.visit(publicClass + '/control', '[data-session-ended]');
    assert.equal(await coach.evaluate(() => !document.querySelector('[data-session-ended]').hidden && !!document.querySelector('[data-session-ended] a[href$="/coach"]')), true);
    assert.equal(await available('start'), false);
    await coach.send('Page.navigate', {url: 'about:blank'});
    await waitFor(async () => await guestStatus() === 'ended', 'aula encerrada na TV do Box');
    await waitFor(async () => await guestStatus() === 'waiting', 'retorno à espera da TV do Box');
    assert.equal(guest.mutations.length, 0);
    await editor.visit(settings, '[data-box-tv-link]');
    assert.equal(await editor.evaluate(() => document.querySelector('[data-tv-online]').textContent), 'TV conectada');
    await editor.evaluate(async () => {
        const form = document.querySelector('.box-tv-settings form');
        form.querySelector('[name="confirmation"]').checked = true;
        const response = await fetch(form.action, {method: 'POST', body: new FormData(form)});
        if (!response.ok) throw new Error('Rotação do link da TV falhou.');
    });
    await waitFor(async () => await guestStatus() === 'unavailable', 'link antigo revogado na TV do Box');
    await editor.visit(settings, '[data-box-tv-link]');
    const replacement = await editor.evaluate(() => document.querySelector('[data-box-tv-link]').value);
    assert.ok(replacement !== tvLink);
    await guest.visit(replacement, '[data-view="box-tv"]');
    await waitFor(() => guest.evaluate(() => document.querySelector('[data-live-logo]').naturalWidth > 0), 'novo link da TV do Box');
    assert.equal(await guestStatus(), 'waiting');
    await guest.send('Page.navigate', {url: 'about:blank'});
    await editor.visit(editorUrl, '[data-workout-form]');
    console.log('Chrome: TV do Box sem cookies, espera, preparação, pausa, retomada após perda de rede, intervalo, troca de etapa, encerramento e revogação.');

    const cases = [
        {count: 3, format: 'stopwatch', digits: '00:00', settings: {preparation_seconds: 0}},
        {count: 8, format: 'countdown', digits: '04:00', long: true},
        {count: 15, format: 'emom', digits: '01:00', rounds: 'Round 1 / 4', settings: {interval_seconds: 60}, multiple: true},
        {count: 25, format: 'amrap', digits: '04:00'},
        {count: 25, format: 'tabata', digits: '03:50', rounds: 'Round 1 / 8', settings: {work_seconds: 20, rest_seconds: 10, rounds: 8}},
        {count: 350, format: 'stopwatch', digits: '00:00', excess: true},
        {count: 3, format: 'stopwatch', digits: '00:00', loadCount: 8},
        {count: 3, format: 'stopwatch', digits: '00:00', loadCount: 12, loadOverflow: true},
    ];
    let shortFont, longFont, liveFixture, exerciseSpace;
    for (const scenario of cases) {
        for (const includeLoads of scenario.excess || scenario.loadCount || scenario.format === 'tabata' ? [true] : [false, true]) {
            const fixture = await editor.evaluate(async (scenario, includeLoads) => {
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const modality = document.querySelector('[name="training_modality_id"]').value;
                const lines = Array.from({length: scenario.count}, (_, index) => (index + 1) + ' · '
                    + (scenario.long ? 'Faça o movimento com calma, estabilize os ombros e mantenha controle do início ao fim da repetição.' : 'Execute o movimento com controle.'));
                const stage = {
                    type: 'workout', title: scenario.long ? 'Técnica de movimento com orientações completas para cada repetição' : 'Principal QA',
                    description: lines.join('\n'), timer_type: scenario.format,
                    timer_settings: scenario.settings ?? {},
                    text_style: {title: {font_weight: 'bold', text_color: '#facc15'}, description: {0: {font_weight: 'normal', text_color: scenario.long ? '#000000' : '#60a5fa'}}},
                };
                if (scenario.format !== 'tabata' && scenario.format !== 'stopwatch') stage.duration_seconds = 240;
                if (includeLoads) stage.load_entries = [{equipment: 'Dumbbell', movement: 'Dumbbell Snatch', rx: '22,5 / 15 kg', intermediate: '18 / 12 kg', scale: '15 / 10 kg', unit: 'kg'}];
                if (includeLoads && scenario.multiple) stage.load_entries.push(
                    {equipment: 'Barbell', movement: 'Deadlift', rx: '100 / 70', intermediate: '80 / 50', scale: '60 / 30', unit: 'kg'},
                    {equipment: 'Barbell', movement: 'Front Squat', rx: '70 / 50', intermediate: '50 / 30', scale: '30 / 20', unit: 'kg'},
                );
                if (scenario.loadCount) stage.load_entries = Array.from({length: scenario.loadCount}, (_, index) => ({
                    equipment: 'Barbell', movement: scenario.loadOverflow ? `Movimento ${index + 1} com longa orientação técnica para validar a capacidade do painel` : `Movimento ${index + 1}`,
                    rx: scenario.loadOverflow ? '100 / 70 kg como referência por série completa' : '100/70',
                    intermediate: scenario.loadOverflow ? '80 / 50 kg como referência por série completa' : '80/50',
                    scale: scenario.loadOverflow ? '60 / 30 kg como referência por série completa' : '60/30', unit: 'kg',
                }));
                if (includeLoads && scenario.format === 'countdown') stage.suggested_loads = {equipment: 'Barbell', rx: '20', unit: 'kg'};
                const stages = scenario.excess ? [{type: 'warmup', title: 'Mobilidade', description: '10 agachamentos', timer_type: 'none'}, stage] : [stage];
                const resp = await fetch('/workouts', {method: 'POST', headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
                    body: JSON.stringify({title: 'Treino QA TV', scheduled_for: '2026-11-23', training_modality_id: modality, structure: scenario.excess ? 'full' : 'main',
                        editor_mode: 'publish', expected_edit_version: 0, editor_write_id: crypto.randomUUID(), blocks: stages})});
                if (!resp.ok) return {status: resp.status, errors: (await resp.json()).errors};
                const saved = await resp.json();
                if (scenario.format === 'stopwatch' && !includeLoads) {
                    return {edit: saved.edit_url, presentation: saved.update_url + '/presentation', lines};
                }
                const prepared = await fetch(saved.update_url + '/sessions', {method: 'POST', body: new URLSearchParams({_token: token})});
                if (!prepared.url.endsWith('/control')) return {status: prepared.status};
                const session = prepared.url.replace(/\/control$/, '');
                if (scenario.excess) {
                    const client = crypto.randomUUID();
                    const headers = {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token};
                    const lease = await fetch(session + '/lease', {method: 'POST', headers, body: JSON.stringify({client_id: client})});
                    const {data} = await lease.json();
                    const move = await fetch(session + '/commands', {method: 'POST', headers, body: JSON.stringify({
                        client_id: client, command_id: crypto.randomUUID(), expected_version: data.version, action: 'next',
                    })});
                    if (!move.ok) return {status: move.status};
                }
                return {edit: saved.edit_url, control: prepared.url, session, lines};
            }, scenario, includeLoads);
            if (fixture.presentation) {
                await editor.visit(fixture.presentation, '[data-preparation-options]');
                const options = await editor.evaluate(() => {
                    const choice = document.querySelector('[data-preparation-choice]');
                    const custom = document.querySelector('[name="custom_preparation_seconds"]');
                    const auto = document.querySelector('[name="auto_advance"][type="checkbox"]');
                    const defaults = {value: choice.value, customDisabled: custom.disabled, auto: auto.checked};
                    choice.value = 'custom';
                    choice.dispatchEvent(new Event('change'));
                    return {...defaults, customEnabled: !custom.disabled && custom.required, choices: [...choice.options].map(option => option.value)};
                });
                assert.deepEqual(options, {value: '', customDisabled: true, auto: false, customEnabled: true, choices: ['', '0', '10', '20', '30', 'custom']});
                fixture.control = await editor.evaluate(async () => {
                    const custom = document.querySelector('[name="custom_preparation_seconds"]');
                    custom.value = '0';
                    const response = await fetch(custom.form.action, {method: 'POST', body: new FormData(custom.form)});
                    return response.url;
                });
                fixture.session = fixture.control.replace(/\/control$/, '');
                console.log('Chrome: opções de preparação, avanço desmarcado e envio de contagem personalizada.');
            }
            assert.match(fixture.control ?? '', /\/sessions\/\d+\/control$/, JSON.stringify(fixture.errors));
            if (scenario.count === 3 && !includeLoads) liveFixture = fixture;
            await editor.visit(fixture.edit, '[data-presentation-check]');
            await editor.evaluate(() => {
                document.querySelector('[data-open-tv-preview]').click();
                const block = document.querySelector('[data-preview-block]');
                block.value = 'workout';
                block.dispatchEvent(new Event('change'));
            });
            const preview = await editor.evaluate(browserMeasurements, '[data-preview-screen]');
            assert.equal(preview.logicalWidth, 1920);
            assert.equal(preview.logicalHeight, 1080);
            assert.ok(Math.abs(preview.drawnAspect - 16 / 9) < .01);
            assert.ok(Math.abs(preview.ratio - .35) < .002);
            console.log('Chrome: ' + scenario.format + ', ' + scenario.count + ' linhas, ' + (includeLoads ? 'com' : 'sem') + ' cargas.');
            for (const [width, height] of [[1920, 1080], [2560, 1440], [3840, 2160]]) {
                await tv.send('Page.bringToFront');
                await tv.resize(width, height);
                await tv.visit(fixture.session + '/tv', '[data-session-app]');
                await waitFor(() => tv.evaluate(() => !!document.querySelector('[data-session-app]').dataset.contentExcess), 'tipografia da TV').catch(async error => {
                    const screen = await tv.evaluate(() => ({visible: !document.hidden, stateUrl: document.querySelector('[data-session-app]').dataset.stateUrl,
                        sync: document.querySelector('[data-sync-status]').textContent}));
                    throw new Error(error.message + ': ' + JSON.stringify({screen, errors}));
                });
                const native = await tv.evaluate(browserMeasurements, '[data-session-app]');
                if (scenario.excess) {
                    assert.equal(native.excess, 'true');
                    assert.equal(native.flag, true);
                    assert.equal(native.scroll, false);
                    assert.deepEqual(native.lines, []);
                    assert.ok(Math.abs(native.ratio - .35) < .002);
                    assert.equal(preview.excess, 'true');
                    assert.equal(preview.flag, true);
                    assert.deepEqual(preview.lines, []);
                    assert.match(await editor.evaluate(() => document.querySelector('[data-capacity-result]').textContent), /Principal QA/);
                    assert.doesNotMatch(await editor.evaluate(() => document.querySelector('[data-capacity-result]').textContent), /Mobilidade/);
                    if (width === 1920) {
                        await editor.send('Page.bringToFront');
                        await editor.evaluate(() => document.querySelector('[data-capacity-edit]').click());
                        assert.equal(await editor.evaluate(() => document.querySelector('[data-presentation-check]').open), false);
                        assert.equal(await editor.evaluate(() => document.activeElement.matches('[data-stage="workout"] [data-edit-text="description"]')), true);
                        await editor.evaluate(() => document.querySelector('[data-open-tv-preview]').click());
                    }
                    continue;
                }
                assert.equal(native.scroll, false, JSON.stringify({scenario, width, native}));
                assert.equal(native.logicalWidth, width);
                assert.equal(native.logicalHeight, height);
                assert.ok(Math.abs(native.ratio - .35) < .002);
                assert.equal(native.excess, 'false', JSON.stringify({scenario, width, native}));
                assert.equal(preview.excess, 'false');
                if (width === 1920 && scenario.multiple) {
                    if (!includeLoads) exerciseSpace = [native.contentHeight, native.font];
                    else {
                        assert.deepEqual([native.contentHeight, native.font], exerciseSpace);
                        assert.deepEqual(native.loadNames, ['Dumbbell · Dumbbell Snatch', 'Barbell · Deadlift', 'Barbell · Front Squat']);
                        assertPalette(await editor.evaluate(loadCardPalette, '[data-preview-screen]'));
                        await screenshot(editor, 'previa-editor');
                        await coach.resize(1440, 1100);
                        await coach.send('Page.bringToFront');
                        await coach.visit(fixture.control, '[data-view="control"]');
                        await waitFor(() => coach.evaluate(() => !!document.querySelector('[data-view="control"]').dataset.loadExcess), 'cargas no painel do coach');
                        const coachLayout = await coach.evaluate(() => {
                            const left = document.querySelector('[data-session-timing]');
                            const timer = left.querySelector('.scoreboard');
                            const loads = left.querySelector('[data-suggested-loads]');
                            const panel = document.querySelector('.session-content');
                            return {ratio: left.offsetWidth / (left.offsetWidth + panel.offsetWidth),
                                fitted: timer.getBoundingClientRect().bottom <= loads.getBoundingClientRect().top
                                    && loads.getBoundingClientRect().bottom <= left.getBoundingClientRect().bottom,
                                rows: loads.querySelectorAll('[data-load-row]').length};
                        });
                        assert.ok(Math.abs(coachLayout.ratio - .35) < .002 && coachLayout.fitted);
                        assert.equal(coachLayout.rows, 3);
                        assertPalette(await coach.evaluate(loadCardPalette, '[data-view="control"]'));
                        await coach.evaluate(() => document.querySelector('.coach-primary-controls').scrollIntoView({block: 'start', behavior: 'instant'}));
                        await screenshot(coach, 'painel-coach');
                        await coach.resize(390, 844);
                        await waitFor(() => coach.evaluate(() => innerWidth === 390), 'largura móvel do painel do coach');
                        const mobile = await coach.evaluate(() => {
                            const board = document.querySelector('.session-board');
                            const left = board.querySelector('[data-session-timing]').getBoundingClientRect();
                            const panel = board.querySelector('.session-content').getBoundingClientRect();
                            return {stacked: left.bottom <= panel.top, width: innerWidth, boardWidth: board.clientWidth,
                                boardScroll: board.scrollWidth, pageScroll: document.scrollingElement.scrollWidth,
                                overflow: [...document.querySelectorAll('.coach-session *')].filter(node => node.getBoundingClientRect().right > innerWidth + 1)
                                    .slice(0, 6).map(node => node.className)};
                        });
                        assert.ok(mobile.stacked && mobile.boardScroll <= mobile.boardWidth + 1 && mobile.pageScroll <= mobile.width + 1, JSON.stringify(mobile));
                    }
                }
                if (native.loadExcess !== String(!!scenario.loadOverflow)) {
                    const measurements = await tv.evaluate(() => {
                        const timing = document.querySelector('[data-session-timing]');
                        const loads = timing.querySelector('[data-suggested-loads]');
                        const score = timing.querySelector('.scoreboard');
                        const notice = timing.querySelector('[data-load-capacity-warning]');
                        notice.hidden = true;
                        timing.dataset.loadExcess = 'false';
                        return ['normal', 'compact', 'columns'].map(mode => {
                            timing.dataset.loadDensity = mode;
                            return [mode, timing.clientHeight, getComputedStyle(timing).paddingTop, score.offsetHeight,
                                loads.offsetHeight, loads.clientWidth, loads.scrollWidth, getComputedStyle(loads).marginTop];
                        });
                    });
                    assert.equal(native.loadExcess, String(!!scenario.loadOverflow), JSON.stringify({scenario, width, measurements}));
                }
                assert.equal(preview.loadExcess, native.loadExcess);
                if (scenario.loadOverflow) {
                    assert.ok(native.loadWarning && preview.loadWarning && native.loadWarningFits && preview.loadWarningFits);
                    assert.equal(native.loads, null);
                    assert.deepEqual(native.lines, fixture.lines);
                    assert.match(await editor.evaluate(() => document.querySelector('[data-capacity-result]').textContent), /cargas sugeridas/);
                    if (width === 1920) {
                        await editor.evaluate(() => document.querySelector('[data-capacity-edit]').click());
                        assert.equal(await editor.evaluate(() => document.activeElement.matches('[data-stage="workout"] [data-load-analyze]')), true);
                        await editor.evaluate(() => document.querySelector('[data-open-tv-preview]').click());
                    }
                }
                if (scenario.loadCount && !scenario.loadOverflow) {
                    assert.notEqual(native.loadDensity, 'normal');
                    assert.equal(native.loadNames.length, scenario.loadCount);
                }
                if (scenario.multiple && includeLoads) {
                    assertPalette(await tv.evaluate(loadCardPalette, '[data-session-app]'));
                    const shiftedPalette = await tv.evaluate(() => {
                        document.body.style.setProperty('--box-accent', '#FA8258');
                        document.body.style.setProperty('--box-primary', '#F97316');
                        return getComputedStyle(document.querySelector('[data-load-level="intermediate"]')).backgroundColor;
                    });
                    assert.equal(shiftedPalette, palette.intermediate[1]);
                    await screenshot(tv, 'tv-' + width + 'x' + height);
                }
                const sharedKeys = ['heading', 'headingColor', 'weight', 'lineColor', 'accent', 'format', 'digits', 'options', 'rounds', 'loads', 'lines'];
                const referenceKeys = ['boardHeight', 'contentWidth', 'contentHeight', 'font', 'grid'];
                for (const key of width === 1920 ? [...sharedKeys, ...referenceKeys] : sharedKeys) {
                    assert.deepEqual(preview[key], native[key], JSON.stringify({key, width, includeLoads, scenario, previewHeader: preview.header, nativeHeader: native.header}));
                }
                assert.deepEqual(native.lines, fixture.lines);
                assert.ok(native.fullyVisible && preview.fullyVisible, 'Todo o texto deve ficar dentro da região medida.');
                assert.ok(native.scoreFits && preview.scoreFits && native.infoFits && preview.infoFits);
                assert.ok(native.loadsFit && preview.loadsFit && native.timerFont >= 130);
                assert.equal(native.digits, scenario.digits);
                assert.equal(native.rounds, scenario.rounds ?? '');
                assert.equal(native.weight, '700');
                assert.equal(native.headingColor, 'rgb(250, 204, 21)');
                if (!scenario.long) assert.equal(native.lineColor, 'rgb(96, 165, 250)');
                assert.ok(contrast(native.lineColor, native.panelColor) >= 4.5);
                assert.ok(native.logo && preview.logo);
                assert.equal(native.accent, '#5266f4');
                assert.equal(!!native.loads, includeLoads && !scenario.loadOverflow);
                if (width === 1920 && !includeLoads && scenario.count === 3) shortFont = native.font;
                if (width === 1920 && !includeLoads && scenario.count === 25) longFont = native.font;
            }
        }
    }
    assert.ok(shortFont >= 52 && shortFont > longFont && longFont >= 22);
    await editor.visit(liveFixture.edit, '[data-presentation-check]');
    const started = await editor.evaluate(async session => {
        const headers = {Accept: 'application/json', 'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content};
        const client_id = crypto.randomUUID();
        const lease = await fetch(session + '/lease', {method: 'POST', headers, body: JSON.stringify({client_id})});
        const {data} = await lease.json();
        const response = await fetch(session + '/commands', {method: 'POST', headers, body: JSON.stringify({client_id,
            command_id: crypto.randomUUID(), expected_version: data.version, action: 'start'})});
        const {data: snapshot} = await response.json();
        return {version: snapshot.version, state: snapshot.timers[0].state};
    }, liveFixture.session);
    assert.ok(['preparing', 'running'].includes(started.state));
    await tv.resize(1920, 1080);
    await tv.visit(liveFixture.session + '/tv', '[data-session-app]');
    await waitFor(() => tv.evaluate(() => !!document.querySelector('[data-session-app]').dataset.contentExcess), 'TV com sessão em andamento');
    const fontBefore = (await tv.evaluate(browserMeasurements, '[data-session-app]')).font;
    await tv.resize(2560, 1440);
    await waitFor(async () => (await tv.evaluate(browserMeasurements, '[data-session-app]')).font > fontBefore, 'recalcular sem recarregar a aula');
    const writes = editor.mutations.filter(url => url.startsWith('/sessions/')).length;
    await editor.evaluate(() => {
        document.querySelector('[data-enable-full-workout]').click();
        document.querySelector('[data-block-toggle="warmup"]').click();
        const warmup = document.querySelector('[data-stage="warmup"] [data-edit-text="title"]');
        warmup.value = 'Aquecimento no rascunho da aula ativa';
        warmup.dispatchEvent(new Event('input', {bubbles: true}));
        const input = document.querySelector('[data-stage="workout"] [data-edit-text="description"]');
        input.value = 'Alteração privada do coach';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        document.querySelector('[data-open-tv-preview]').click();
        const select = document.querySelector('[data-preview-block]');
        select.value = 'workout';
        select.dispatchEvent(new Event('change'));
    });
    await waitFor(() => editor.evaluate(() => document.querySelector('[data-preview-screen] [data-content-pages]').textContent.includes('Alteração privada')), 'prévia durante aula ativa');
    assert.equal(await editor.evaluate(() => document.querySelector('[data-preview-screen]').dataset.timerState), 'idle');
    assert.deepEqual(await editor.evaluate(() => [...document.querySelector('[data-preview-block]').options].map(item => item.value)), ['warmup', 'workout']);
    const preserved = await tv.evaluate(async () => {
        const screen = document.querySelector('[data-session-app]');
        const {data} = await (await fetch(screen.dataset.stateUrl)).json();
        return {version: data.version, state: data.timers[0].state, description: data.timers[0].description};
    });
    assert.equal(preserved.version, started.version);
    assert.ok(['preparing', 'running'].includes(preserved.state));
    assert.equal(preserved.description, liveFixture.lines.join('\n'));
    assert.equal(editor.mutations.filter(url => url.startsWith('/sessions/')).length, writes);

    console.log('Chrome: paridade da prévia em Full HD, TV adaptativa 35/65 em Full HD/QHD/4K, 3/8/15/25 linhas, estilos, cargas e aviso seletivo de excesso.');
    assert.deepEqual(errors, []);
} finally {
    for (const socket of sockets) socket.close();
    chrome.kill('SIGTERM');
    server.kill('SIGINT');
    proxy.closeAllConnections();
    proxy.close();
    await delay(200);
    await Promise.all([profile, storage].map(path => rm(path, {recursive: true, force: true})));
}
