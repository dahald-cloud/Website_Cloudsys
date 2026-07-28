const { after, before, test } = require('node:test');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const net = require('node:net');

const port = 31847;
let child;

before(async () => {
  child = spawn(process.execPath, ['server.js'], {
    cwd: __dirname,
    env: { ...process.env, PORT: String(port), NODE_ENV: 'development' },
    stdio: ['ignore', 'pipe', 'pipe']
  });
  await new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('Test server did not start')), 5000);
    child.stdout.on('data', data => {
      if (data.toString().includes('site running')) {
        clearTimeout(timer);
        resolve();
      }
    });
    child.once('exit', code => reject(new Error(`Test server exited with ${code}`)));
  });
});

after(() => {
  child?.kill();
});

test('serves the homepage with security headers', async () => {
  const response = await fetch(`http://127.0.0.1:${port}/`);
  assert.equal(response.status, 200);
  assert.match(response.headers.get('content-security-policy'), /frame-ancestors 'none'/);
  assert.equal(response.headers.get('x-content-type-options'), 'nosniff');
});

test('blocks private application files', async () => {
  const response = await fetch(`http://127.0.0.1:${port}/server.js`);
  assert.equal(response.status, 403);
});

test('rejects invalid contact submissions', async () => {
  const response = await fetch(`http://127.0.0.1:${port}/api/contact`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: '{}'
  });
  assert.equal(response.status, 400);
});

test('malformed URL returns 400 without crashing the server', async () => {
  const statusLine = await new Promise((resolve, reject) => {
    const socket = net.createConnection({ host: '127.0.0.1', port }, () => {
      socket.write('GET /% HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n');
    });
    let response = '';
    socket.on('data', chunk => response += chunk);
    socket.on('end', () => resolve(response.split('\r\n')[0]));
    socket.on('error', reject);
  });
  assert.match(statusLine, /400 Bad Request/);
  const response = await fetch(`http://127.0.0.1:${port}/`);
  assert.equal(response.status, 200);
});
