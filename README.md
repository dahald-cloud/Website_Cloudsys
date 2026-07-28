# CloudSys website

Static marketing site served by a small Node.js server with a protected contact endpoint.

## Local development

1. Copy `.env.example` to `.env`.
2. Run `npm install`.
3. Run `npm run dev`.
4. Open `http://localhost:3000`.

Localhost uses Cloudflare Turnstile's public always-pass test widget. The example
environment file contains its matching test secret. Never use those test values
in production.

## Production configuration

Set `NODE_ENV=production` and provide:

- `GMAIL_USER`: dedicated Gmail or Google Workspace sending mailbox
- `GMAIL_APP_PASSWORD`: app password for that mailbox
- `TURNSTILE_SECRET_KEY`: production Turnstile secret matching the site key in `form.js`
- `TRUST_PROXY=true`: only when a trusted reverse proxy overwrites
  `X-Forwarded-For`; otherwise leave it false
- `PORT`: optional, defaults to `3000`

The server refuses to start in production when required credentials are missing
or the Turnstile test secret is still configured.

If the previously committed Turnstile value was real, rotate it in the
Cloudflare dashboard before deployment.

## Verification

Run:

```sh
npm run check
npm test
npm audit --omit=dev
```

The tests cover security headers, private-file blocking, invalid contact
requests, and the malformed-URL regression that previously crashed the server.

## Deployment notes

- Terminate TLS at the hosting platform or reverse proxy.
- Configure the production hostname in the Turnstile widget.
- Preserve the security headers emitted by `server.js`.
- Use a process supervisor and collect stderr for contact-form failures.
- Replace the current qualitative case-study outcomes with approved,
  measurable customer results when those figures are available.
