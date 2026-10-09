import { spawn } from 'node:child_process';
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

// Use Chrome device emulation: window-size alone may clamp narrow viewports.
const [snapshot, profile, output] = process.argv.slice(2);
const browser = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'
], { stdio: 'ignore', windowsHide: true });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
const pending = new Map();
let sequence = 0;
function command(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++sequence;
        const timeout = setTimeout(() => { pending.delete(id); reject(new Error('CDP timeout: ' + method)); }, 10000);
        pending.set(id, { resolve, reject, timeout });
        socket.send(JSON.stringify({ id, method, params }));
    });
}
try {
    let port;
    for (let i = 0; i < 100; i++) {
        try { port = (await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
        catch { await pause(100); }
    }
    if (!port) throw new Error('Chrome did not start');
    const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    socket.addEventListener('message', event => {
        const data = JSON.parse(event.data);
        const item = pending.get(data.id);
        if (!item) return;
        pending.delete(data.id); clearTimeout(item.timeout);
        if (data.error) item.reject(new Error(data.error.message)); else item.resolve(data.result);
    });
    await command('Page.enable');
    for (const [name, width, height, mobile] of [['chat-desktop.png', 1440, 1000, false], ['chat-mobile.png', 390, 1000, true]]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile });
        await command('Page.navigate', { url: pathToFileURL(snapshot).href });
        await pause(700);
        const layout = await command('Runtime.evaluate', { expression: 'JSON.stringify({width:innerWidth,scroll:document.documentElement.scrollWidth,composer:document.querySelector(".chat-composer").getBoundingClientRect().width})', returnByValue: true });
        const metrics = JSON.parse(layout.result.value);
        if (metrics.width !== width || metrics.scroll > width) throw new Error('Chat overflows viewport: ' + JSON.stringify(metrics));
        const capture = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        await writeFile(path.join(output, name), Buffer.from(capture.data, 'base64'));
        process.stdout.write(`PASS ${width}px chat layout: no horizontal overflow\n`);
    }
    await command('Browser.close');
} finally {
    if (socket) socket.close();
    if (browser.exitCode === null) {
        await Promise.race([new Promise(resolve => browser.once('exit', resolve)), pause(2000)]);
        if (browser.exitCode === null) browser.kill();
    }
}
