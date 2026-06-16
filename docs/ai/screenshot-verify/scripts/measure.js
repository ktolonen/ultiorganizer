#!/usr/bin/env node
// Usage: node measure.js <url> <width> <height> <selectors>
// selectors: comma-separated CSS selectors, e.g. ".page,.page_top,.games-table"
// Outputs JSON with viewport width, body scroll width, and per-element dimensions.

const { spawn } = require('child_process');
const { WebSocket } = require('ws');
const http = require('http');

const [,, url, width = '1400', height = '900', selectorsArg = '.page'] = process.argv;

if (!url) {
    console.error('Usage: node measure.js <url> [width] [height] [selectors]');
    process.exit(1);
}

const selectors = selectorsArg.split(',').map(s => s.trim()).filter(Boolean);
const PORT = 9230 + Math.floor(Math.random() * 100);

const proc = spawn('chromium', [
    '--headless=new', '--no-sandbox', '--disable-gpu',
    `--remote-debugging-port=${PORT}`,
    `--window-size=${width},${height}`,
    url,
], { stdio: 'ignore' });

const cleanup = () => { try { proc.kill('SIGKILL'); } catch (_) {} };
process.on('exit', cleanup);
process.on('SIGINT', () => { cleanup(); process.exit(1); });

const wait = ms => new Promise(r => setTimeout(r, ms));

async function getTargets(retries = 15) {
    for (let i = 0; i < retries; i++) {
        try {
            const data = await new Promise((res, rej) => {
                http.get(`http://localhost:${PORT}/json`, r => {
                    let d = '';
                    r.on('data', c => d += c);
                    r.on('end', () => res(JSON.parse(d)));
                }).on('error', rej);
            });
            const pages = data.filter(t => t.type === 'page' && t.webSocketDebuggerUrl);
            if (pages.length) return pages;
        } catch (_) {}
        await wait(300);
    }
    throw new Error('Could not connect to Chromium DevTools');
}

function sendCommand(ws, id, method, params = {}) {
    ws.send(JSON.stringify({ id, method, params }));
}

async function run() {
    await wait(1500);
    const targets = await getTargets();
    const ws = new WebSocket(targets[0].webSocketDebuggerUrl);

    await new Promise((res, rej) => { ws.on('open', res); ws.on('error', rej); });

    let nextId = 1;
    const pending = new Map();

    ws.on('message', raw => {
        const msg = JSON.parse(raw);
        if (msg.id && pending.has(msg.id)) {
            pending.get(msg.id)(msg);
            pending.delete(msg.id);
        }
    });

    const call = (method, params) => new Promise(res => {
        const id = nextId++;
        pending.set(id, res);
        ws.send(JSON.stringify({ id, method, params }));
    });

    // Enable Page events and wait for load
    await call('Page.enable');

    await new Promise(res => {
        const handler = raw => {
            const msg = JSON.parse(raw);
            if (msg.method === 'Page.loadEventFired') {
                ws.off('message', handler);
                res();
            }
        };
        ws.on('message', handler);
        // Failsafe: resolve after 8s even if event never fires
        setTimeout(res, 8000);
    });

    // Small extra wait for any JS that runs after load
    await wait(500);

    const expression = `
        JSON.stringify({
            viewport: window.innerWidth,
            bodyScrollWidth: document.body.scrollWidth,
            url: location.href,
            elements: Object.fromEntries(
                ${JSON.stringify(selectors)}.map(sel => {
                    const el = document.querySelector(sel);
                    if (!el) return [sel, null];
                    const r = el.getBoundingClientRect();
                    return [sel, {
                        width: Math.round(r.width),
                        height: Math.round(r.height),
                        scrollWidth: el.scrollWidth,
                        computedMinWidth: getComputedStyle(el).minWidth,
                    }];
                })
            )
        })
    `;

    const result = await call('Runtime.evaluate', { expression });
    const value = result?.result?.result?.value;
    if (value) {
        console.log(JSON.stringify(JSON.parse(value), null, 2));
    } else {
        console.error('Unexpected CDP response:', JSON.stringify(result));
        process.exit(1);
    }

    ws.close();
    cleanup();
}

run().catch(e => {
    console.error('Error:', e.message);
    cleanup();
    process.exit(1);
});
