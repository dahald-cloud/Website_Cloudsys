# Insights publishing — Phases 1–4

Local foundation only; nothing is deployed automatically.

## Database installation

Back up the database, then import `deployment/migrate-insights.sql` into the existing CloudSys database using phpMyAdmin. On a fresh installation, import `schema.sql` first, then this migration. No new environment values are required. Existing `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` are reused.

The migration is repeat-safe and does not overwrite existing category names, articles, or admin accounts. It creates `article_categories` and `articles`, and seeds NetSuite, AI & Automation, and Problems We Have Solved. One primary category per article; optional tags are not part of Phase 1.

## Access and routing

- `/admin/articles.php` uses the existing active-admin, session-version, and forced-password-change guards. No public registration or new role is introduced.
- `/insights/example-article` is routed internally to `article.php`. The controller validates the actual URL path, not a query-supplied slug. Direct `/article.php` requests return 404.
- Anonymous and authenticated public-route requests receive only rows with `status = published` and a non-null publication timestamp at or before the current UTC time. Draft, future-dated, and missing articles return the same 404.
- Public article responses use `no-store` to prevent cached pages surviving an unpublish. Draft previews and write endpoints require admin authentication.
- Slugs are unique lowercase ASCII words separated by single hyphens, maximum 160 characters. SQL uses bound parameters.
- Article text uses a restricted Markdown-style format: paragraphs, level-two/three headings, bold text, lists, and HTTP(S) links. Raw HTML is escaped. Public pages and private previews use the same server-side renderer. No arbitrary HTML, scripts, embedded media, or inline-image URLs are interpreted.

## Before deployment

Run PHP lint on `includes/articles.php`, `article.php`, and `admin/articles.php` using PHP 8.3. Test with a non-production database: anonymous admin access redirects to login; inactive/stale admins are rejected; drafts and future dates return 404; a published past-dated row renders with escaped content; unpublishing immediately produces 404; duplicate slugs are rejected. Re-import the migration to confirm existing rows remain intact.

## Phase 2 publishing

Open `/admin/articles.php` after signing in. Create/edit articles, assign a category and author display name, set summaries and SEO overrides, save drafts, preview the saved version, publish immediately, and unpublish. Publishing requires a summary and body; covers require alt text. Publication dates are set in UTC on first publish. Slugs become immutable after the first save to avoid breaking links. Editing a published article updates it publicly; unpublish first to make private revisions. No deletion action is included.

All writes use the existing admin authorization, CSRF tokens, prepared statements, transactions, and a row-version fingerprint checked under a database lock. Stale edits are rejected rather than overwriting another admin's work. Draft previews require active admin sessions and are noindex/no-store; no shareable preview token bypasses login.

### Image requirements

Enable PHP GD and Fileinfo. Configure `upload_max_filesize` at least `4M` and `post_max_size` at least `6M`; use a suitable PHP memory limit (128 MB or more). Only JPEG/PNG up to 4 MB and 6 megapixels are accepted. Files are decoded and re-encoded as JPEG (max 1920 pixels), discarding original filenames and metadata. Transparency becomes white.

The application creates `cloudsys-article-media` beside `public_html` with mode 0700 and stores random-named files as 0600. The PHP user must be allowed to write this location. Back up this directory with the database. No new environment values or schema migration beyond Phase 1 are required. The public directory must not contain a copy of private media.

`article-media.php?id=...` serves only normalized images referenced by a public article or requested by an authenticated admin with a changed password. Unpublishing protects the cover too. Removed/replaced covers are detached, not permanently deleted, to avoid races with readers; unreferenced files may be reviewed during a later backup-aware cleanup. Failed database saves remove only their newly generated image.

### Validation before deployment

Run `php -l` on all added/changed PHP files and `php deployment/test-insights.php`. For optional visibility integration tests use a dedicated test database, point `CLOUDSYS_CONFIG` at its private configuration, and set `CLOUDSYS_INSIGHTS_TEST_DB=1`; fixtures roll back. Never run these database tests against production.

Manually test: create a draft; save formatting and a cover; preview while signed in; confirm draft page and media are unavailable signed out; publish; verify body and image publicly; unpublish; confirm both return 404; try duplicate slugs, expired CSRF, oversized files, disguised SVG/PHP files, and two-tab edit conflicts. Failed saves must preserve text and explain reselecting uploads. Test all flows after session expiry and on narrow screens.

## Phase 3 public reading experience

Visit `/insights` directly. The listing shows nine published articles per page, newest first, with optional covers, summaries, dates, and text-labelled category badges. Search matches a literal phrase in title, summary, or body; `%`, `_`, and `!` are escaped, not treated as user-supplied wildcards. Search is limited to 100 Unicode characters. Search and category filters work together and persist in pagination links. No JavaScript is needed for browsing/searching.

Only published, non-future articles appear in listing queries, category counts, related articles, or detail pages. Unknown categories return 404; malformed filters return 400; database failures return a styled 503. Empty categories/searches show an empty state. Out-of-range pages redirect to the final available page. No example articles are seeded or published automatically.

`insights.css` extends the existing Manrope/DM Mono/Newsreader palette with three/two/one-column cards, wrapping filter controls, narrow-screen stacked search, keyboard focus outlines, and a readable article column. NetSuite badges are pale cyan/dark teal; AI badges dark teal/white; Problems We've Solved badges grey/ink. Article pages include a topic link, author/date, formatted text, contact link, and up to three other published articles in the same category. No related section is shown if there are none.

All public article/list responses are no-store. Query variants are noindex/follow with a canonical base listing URL. `/insights/` and `/insights.php` redirect to `/insights` on Apache. Phase 4 integrates these routes into navigation and sitemap/schema handling below.

No new environment variables, dependencies, or database migrations are introduced in Phase 3. Upload `insights.php`, `insights.css`, `includes/insights-view.php`, the updated `includes/articles.php`, `article.php`, and `.htaccess` when deployment is authorized. Keep deployment helpers and private config outside the public upload.

Before hosting, use a dedicated MySQL test database for the optional tests and verify search, combined filters, multi-page results, draft/future/unpublished exclusion, protected images, cookie controls, and layouts at mobile/tablet/desktop widths. PHP lint and pure renderer/input tests do not replace database or browser integration testing.

## Phase 4 integration

All 11 static public pages and the public Insights templates share these navigation labels: NetSuite Solutions, AI & Automation, Client Stories, Managed Support, Insights, About Us. Development remains available through the footer. No admin/login link is exposed. The new `navigation.css` loads last, keeps labels readable, uses a 1280px hamburger breakpoint, and gives the menu its own scrolling area on short screens. `form.js` uses the same breakpoint, preserves Escape/outside-click/link-close behavior, and keeps keyboard Tab focus within the open menu controls.

Article pages now emit escaped BlogPosting and BreadcrumbList JSON-LD, using the real headline, summary, author, publication/update timestamps (UTC), canonical URL, and optional existing cover. The default CloudSys author is an Organization; other display names are represented as Person authors. Admins should use personal names for those bylines. Title/description/url Open Graph and Twitter metadata match the article SEO overrides. No stock social image or invented author profile is added.

The existing `/sitemap.xml` public URL becomes a dynamic sitemap index through Apache rewriting. It references `/sitemap-pages.xml` (the static pages plus Insights) and `/sitemap-articles-N.xml` pages of up to 1,000 published articles each. The local `sitemap.xml` file remains the static source manifest read by PHP—keep it in the upload. Article queries exclude draft, undated, and future articles and use no-store responses; publishing/unpublishing changes the generated sitemap without editing XML. Requests validate the path, never a user-supplied SQL fragment. Database errors return 503/retry-after rather than presenting an empty successful index. Private/admin/search-result URLs are not included. `robots.txt` retains the same sitemap URL and now also disallows deployment helpers; this is not an access-control mechanism.

Phase 4 files: all public HTML pages, `navigation.css`, `form.js`, `includes/insights-view.php`, `includes/article-seo.php`, `includes/sitemap.php`, `article.php`, `sitemap.php`, `sitemap.xml`, `.htaccess`, `robots.txt`, and the deployment tests/notes. No new environment keys, migrations, npm packages, or Node hosting process are required.

### Release checks still required

- Run `php deployment/test-insights.php` with a dedicated MySQL test database for real published/draft/future/sitemap visibility assertions. The local pure tests do not connect to MySQL by default.
- On an Apache/PHP staging host, verify `/sitemap.xml` is an XML index (not just the static source file), `/sitemap-pages.xml` is a urlset, and article sitemap pages add/remove a test article when it is published/unpublished. Verify nonexistent sitemap pages and direct `/sitemap.php` return 404; invalid methods return 405.
- Check menu opening/closing and keyboard use at 320, 768, 1280, and 1440px, at 200% zoom, and with long titles. Verify cookie choices and contact links on the new public pages. Browser visual checks have not been run in Phase 4.
- Confirm an anonymous request cannot access admin previews or draft images. Verify real uploads, author bylines, contact email delivery, and chatbot settings separately; no external emails or AI calls were made for this phase.
- After deployment is authorized, inspect a published article with Google's Rich Results Test and submit/refresh `/sitemap.xml` in Search Console. Neither schema nor a sitemap guarantees indexing or rich results.

References: [Google Article structured data](https://developers.google.com/search/docs/appearance/structured-data/article), [Google sitemap guidance](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).
