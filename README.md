# E&R Real Estate and Construction Limited

E&R REAL ESTATE AND CONSTRUCTION LIMITED is a construction website that allows clients to interact with the contractor and view the services provided by the company.

Public marketing website for E&R Real Estate and Construction Limited.

## Local preview

Run the site through a web server; opening a `.php` file with `file://` displays source rather than executing it. With XAMPP, start Apache and MySQL in the XAMPP Control Panel, then visit:

`http://localhost/E%26R%20REAL%20ESTATE%20AND%20CONSTRUCTION%20LIMITED/quote.php`

The contact and quote forms also require the configured MySQL database. Website submissions are stored for administrator review. The legacy mail handlers are not public entry points. The academy form sends course enquiries to the company mailbox; it does not create an enrolment record.

## Before production

- Configure a transactional email provider and verified sender domain (including SPF, DKIM, and DMARC) before enabling email notifications; the enquiry forms currently persist submissions in the database and show that status to visitors.
- Public contact and quote submissions use server-side length/type checks, CSRF tokens, honeypot fields, and per-IP rate limits. Hashed rate-limit records expire after 24 hours. Keep monitoring abuse and add CAPTCHA only if the rate limiting proves insufficient.
- The legacy `mail/` handlers are denied direct web access; keep them private unless a form explicitly needs them.
- Keep the client dashboard unavailable until server-side authentication, role authorization, secure sessions, audit logging, and a protected document store are implemented. The administrator workspace uses database-backed login, password verification, and an administrator-role check; add an audit trail before production.
- Keep source photos and video optimized for web delivery, and publish responsive image derivatives. Pages loading the shared settings script set below-the-fold images to native lazy loading and use asynchronous image decoding; keep the first carousel image eager.
- Administrator image uploads resize oversized images to a 1,920-pixel maximum dimension and WebP-compress them in the browser where supported; the server still validates the uploaded file type and size.
- Use project records for public portfolio entries. Add genuine project photos, the actual location, and a factual summary of confirmed scope and outcomes; omit unknown details rather than filling them with sample copy.
- Confirm property availability, Academy schedules and fees, credentials, and delivery commitments with the company before publishing them as current facts.
- Keep business contact details and other site-wide settings in one admin-managed source instead of copying values across individual pages.
- Manage editorial articles, publication dates, categories, and moderation through the authenticated Editorial CMS. Search now operates on real published articles; comment counts, fake categories, and pagination remain removed.
- Keep database credentials in the `ER_DB_HOST`, `ER_DB_USER`, `ER_DB_PASSWORD`, and `ER_DB_NAME` environment variables on production servers. Local XAMPP defaults remain available for development.
- Set `ER_PUBLIC_BASE_URL` to the canonical HTTPS origin before publishing. `sitemap.php` uses it to emit public pages and only published project/article records; it intentionally returns HTTP 503 until the public origin is configured. `/robots.txt` excludes admin, configuration, migration, mail, and quote-upload paths from crawlers, and advertises the sitemap when the canonical origin is configured.
- The privacy information page documents the current form data flow. Have the company approve the final legal notice and define data-retention and access/deletion procedures before production.
- The root `.htaccess` disables directory listings and enables browser caching/compression when the corresponding Apache modules are enabled. Configuration and migration folders are web-inaccessible, and quote attachments are only available to authenticated administrators through the Enquiries workspace. Keep Apache overrides enabled or implement equivalent virtual-host rules.
- Keyboard focus indicators and a reduced-motion stylesheet are provided. Recheck contrast, image alternatives, and screen-reader behavior when adding or replacing page content.
- Before production changes, run `powershell -ExecutionPolicy Bypass -File scripts/backup-database.ps1`. The script writes a timestamped SQL export to the current user's `E&R Database Backups` folder, outside the web root. Store an additional encrypted copy off the web server and periodically verify a restore in a separate database; configure and test that external destination with the hosting provider's credentials.
- Apply `database/migrations/001_content_review_and_site_settings.sql` once before using the content review and site settings administration screens. Existing projects and properties start unpublished and must be reviewed before publication.
- Apply `database/migrations/002_editorial_cms.sql` once before creating or publishing articles in the Editorial CMS.
- Apply `database/migrations/003_enquiry_ownership_and_follow_up.sql` once to enable lead assignment and scheduled follow-up dates.
- Open `/admin/dashboard.php` after signing in, then use Content review, Editorial CMS, Enquiries, and Site settings for the corresponding workflows. The legacy `blog.html` and `single.html` paths redirect to the database-backed Insights experience.
- The site records new quote and contact submissions in the database and exposes status tracking to authenticated administrators. Use the administration dashboard to review content and follow up on leads.

There is no JavaScript build step. The public pages currently load Bootstrap, jQuery, and Font Awesome from CDNs; pin and upgrade those dependencies before a long-lived production deployment.
