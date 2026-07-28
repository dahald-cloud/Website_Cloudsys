require('dotenv').config();
const http = require('http');
const fs = require('fs');
const path = require('path');
const nodemailer = require('nodemailer');
const https = require('https');

const port = process.env.PORT || 3000;
const root = __dirname;
const senders = new Map();
const isProduction = process.env.NODE_ENV === 'production';
const trustProxy = process.env.TRUST_PROXY === 'true';
const securityHeaders = {
  'X-Frame-Options': 'DENY',
  'X-Content-Type-Options': 'nosniff',
  'Referrer-Policy': 'strict-origin-when-cross-origin',
  'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
  'Content-Security-Policy': "default-src 'self'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; script-src 'self' https://challenges.cloudflare.com; connect-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
};
const blockedPath = /(?:^|\/)(?:\.|node_modules|server\.js|package(?:-lock)?\.json|work|outputs)(?:\/|$)/i;
const contentTypes = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.png': 'image/png', '.jpeg': 'image/jpeg', '.jpg': 'image/jpeg', '.avif': 'image/avif', '.ico': 'image/x-icon', '.svg': 'image/svg+xml', '.xml': 'application/xml', '.txt': 'text/plain; charset=utf-8' };
setInterval(() => { const cutoff = Date.now() - 120_000; for (const [ip, ts] of senders) if (ts < cutoff) senders.delete(ip); }, 120_000);

const transporter = nodemailer.createTransport({
  service: 'gmail',
  auth: { user: process.env.GMAIL_USER, pass: process.env.GMAIL_APP_PASSWORD }
});

function send(res, code, body, type = 'application/json') {
  if (res.headersSent) return;
  res.writeHead(code, { 'Content-Type': type, 'Cache-Control': 'no-store', ...securityHeaders });
  res.end(body);
}

function safe(value = '') { return String(value).replace(/[\r\n]/g, ' ').trim(); }

function verifyTurnstile(token, ip) {
  return new Promise((resolve, reject) => {
    const body = `secret=${encodeURIComponent(process.env.TURNSTILE_SECRET_KEY)}&response=${encodeURIComponent(token)}&remoteip=${encodeURIComponent(ip)}`;
    const req = https.request({ hostname: 'challenges.cloudflare.com', path: '/turnstile/v0/siteverify', method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(body) } }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        if (res.statusCode !== 200) return reject(new Error(`Turnstile returned ${res.statusCode}`));
        try { resolve(JSON.parse(data)); } catch { reject(new Error('Invalid Turnstile response')); }
      });
    });
    req.setTimeout(5000, () => req.destroy(new Error('Turnstile request timed out')));
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

function getClientIp(req) {
  if (trustProxy) {
    const forwarded = String(req.headers['x-forwarded-for'] || '').split(',')[0].trim();
    if (forwarded) return forwarded;
  }
  return req.socket.remoteAddress || 'unknown';
}

const server = http.createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/api/contact') {
    let body = '';
    let overflow = false;
    req.on('data', chunk => { body += chunk; if (body.length > 10_000 && !overflow) { overflow = true; send(res, 413, JSON.stringify({ error: 'Request too large.' })); req.destroy(); } });
    req.on('end', async () => {
      if (overflow) return;
      try {
        const { name, email, company, message, website, 'cf-turnstile-response': turnstileToken } = JSON.parse(body);
        if (website) return send(res, 200, JSON.stringify({ ok: true }));
        const ip = getClientIp(req);
        if (senders.get(ip) > Date.now() - 60_000) return send(res, 429, JSON.stringify({ error: 'Please wait before trying again.' }));
        if (!safe(name) || !/^\S+@\S+\.\S+$/.test(safe(email))) return send(res, 400, JSON.stringify({ error: 'Please provide a name and valid email.' }));
        if (safe(name).length > 100 || safe(email).length > 254 || safe(company).length > 150 || safe(message).length > 3000) return send(res, 400, JSON.stringify({ error: 'One or more fields are too long.' }));
        if (!turnstileToken) return send(res, 400, JSON.stringify({ error: 'Please complete the CAPTCHA.' }));
        const turnstileResult = await verifyTurnstile(turnstileToken, ip);
        if (!turnstileResult.success) return send(res, 400, JSON.stringify({ error: 'CAPTCHA verification failed. Please try again.' }));
        if (!process.env.GMAIL_USER || !process.env.GMAIL_APP_PASSWORD) throw new Error('Gmail is not configured.');
        senders.set(ip, Date.now());
        await transporter.sendMail({
          from: `CloudSys Website <${process.env.GMAIL_USER}>`,
          to: 'support@cloudsysllc.com',
          replyTo: safe(email),
          subject: `New NetSuite assessment request from ${safe(name)}`,
          text: `Name: ${safe(name)}\nWork email: ${safe(email)}\nCompany: ${safe(company) || 'Not provided'}\nChallenge: ${safe(message) || 'Not provided'}\n\nSubmitted through cloudsysllc.com.`
        });
        send(res, 200, JSON.stringify({ ok: true }));
      } catch (error) {
        console.error('Contact form error:', error.message);
        send(res, 500, JSON.stringify({ error: 'Unable to send message.' }));
      }
    });
    return;
  }


  if (req.method !== 'GET' && req.method !== 'HEAD') return send(res, 405, 'Method not allowed', 'text/plain');
  let pathname;
  try {
    pathname = req.url === '/' ? '/index.html' : decodeURIComponent(req.url.split('?')[0]);
  } catch {
    return send(res, 400, 'Bad request', 'text/plain');
  }
  if (blockedPath.test(pathname)) return send(res, 403, 'Forbidden', 'text/plain');
  const file = path.resolve(root, `.${pathname}`);
  if (file !== root && !file.startsWith(`${root}${path.sep}`)) return send(res, 403, 'Forbidden', 'text/plain');
  const ext = path.extname(file);
  const stream = fs.createReadStream(file);
  stream.on('error', () => send(res, 404, 'Not found', 'text/plain'));
  stream.on('open', () => {
    const cacheControl = ext === '.html' ? 'no-cache' : 'public, max-age=86400';
    res.writeHead(200, { 'Content-Type': contentTypes[ext] || 'application/octet-stream', 'Cache-Control': cacheControl, ...securityHeaders });
    if (req.method === 'HEAD') { res.end(); stream.destroy(); } else stream.pipe(res);
  });
});

server.requestTimeout = 10_000;
server.headersTimeout = 12_000;

const turnstileTestSecret = '1x0000000000000000000000000000000AA';
if (isProduction && (!process.env.GMAIL_USER || !process.env.GMAIL_APP_PASSWORD || !process.env.TURNSTILE_SECRET_KEY || process.env.TURNSTILE_SECRET_KEY === turnstileTestSecret)) {
  console.error('Missing required production email or Turnstile configuration.');
  process.exit(1);
}

server.listen(port, () => console.log(`CloudSys site running at http://localhost:${port}`));

module.exports = server;
