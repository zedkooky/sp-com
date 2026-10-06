# siddharthaparmar.com

Founder site for Siddhartha Parmar. Static HTML plus one small PHP handler for
the contact forms. No build step, no framework, no database.

```
index.html            home page: splash, themes, ventures, essays, contact form
aurum.html            Aurum Resources page (copper, mines, investor procurement)
media-kit.html        bio, facts and contact details for press
writing/              one page per essay (plus index), generated from the essay text
facts.html            canonical facts and biography (citation target for AI search)
timeline.html         career chronology
assets/crest.png      the crest (splash, hero, nav logo, favicon)
og.png                1200x630 link-preview image
sitemap.xml           pages for search engines
robots.txt
contact.php           contact form handler (the only moving part)
config.example.php    copy to config.php on the server and fill in
.htaccess             Apache / LiteSpeed rules (HTTPS, headers, caching)
server/               optional nginx config and Node handler
api/contact.js        Node version of the form handler (only if no PHP)
```

---

## What the site does

- **Random theme per visit.** Orbital (A), Cosmos (B) or Ground Control (C).
  Never the same theme twice in a row. Add `?theme=a`, `b` or `c` to the URL to
  pin one. Footer links switch themes. Themes are `:root[data-theme="..."]`
  blocks in `index.html` and `aurum.html`, and the nebula colours are the
  `PAL` object in the WebGL script.
- **Fonts.** Syne (headings) and Inter (body) in every theme, with a different
  mono font per theme.
- **Splash.** Crest beside "SP", a counter, then the screen splits open and
  the crest flies onto the hero. As you scroll it shrinks into the nav logo.
  Shown once per browser session. Skipped with reduced motion.
- **Aurum Resources** (`aurum.html`). Copper cathode and concentrate trading,
  mine sale and acquisition mandates, investor procurement. Own enquiry form.

---

## Deploy (FTP)

Upload these to the web root (`public_html` or equivalent) so that
`/aurum.html` and `/og.png` resolve:

```
index.html  aurum.html  media-kit.html  facts.html  timeline.html  og.png  robots.txt  sitemap.xml
contact.php  config.example.php  .htaccess  assets/
```

Do not upload `.git`, `README.md`, `server/` or `api/` unless you use nginx or
the Node handler. Make sure `.htaccess` goes across (dotfiles are often hidden
in FTP clients).

Then, on the server only:

```bash
cp config.example.php config.php
```

Edit `config.php`:

- `CONTACT_TO`: the inbox that receives enquiries.
- `CONTACT_FROM`: a sender address on your own domain.
- `RESEND_API_KEY`: optional but recommended. Without it the form falls back
  to PHP `mail()`, which often lands in spam. Get a key at resend.com, verify
  the domain with the DNS records it gives you, and paste the key in.

`config.php` is in `.gitignore`. Never commit it, and never put the key in the
repository.

Test the form before announcing the site: submit it from `/` and from
`/aurum.html` and confirm both emails arrive. Aurum emails are tagged
"Aurum: ..." in the subject. If the PHP handler is not reachable the page
opens the visitor's email app instead, so nothing is lost.

### DNS and SSL

Point `A` records for `@` and `www` at the server IP. The `.htaccess` forces
HTTPS and strips `www`, so install an SSL certificate first (cPanel: Security >
SSL/TLS Status > Run AutoSSL; self-managed: `certbot`).

### Other servers

- **nginx:** use `server/nginx.conf.example`. Adjust the PHP-FPM socket to your
  PHP version. Run certbot before enabling the 443 blocks.
- **No PHP:** use `server/contact-server.js` and `api/contact.js`, and set
  `FORM_ENDPOINT` to `/api/contact` near the bottom of `index.html` and in
  `aurum.html`.

---

## Still to add

- **Favicon set.** The crest PNG is used as the icon. Add `favicon.ico` and
  `apple-touch-icon.png` for older browsers and iOS.
- **Crest as SVG.** `assets/crest.png` works everywhere but an SVG will be
  sharper when large.
- **Aurum facts.** The page makes no claims about past deals, tonnages or
  licences. Add real credentials, case studies and registrations when ready.
- **Aurum on LinkedIn, Google Business Profile and mining directories.** Links
  from these help the Aurum page rank for mining and investor searches.
- **Property.** Properties for lease, sale and sell-on-behalf mandates are
  mentioned in the Now section and the timeline only. A dedicated page with
  listings would help leads.
- **Media kit images.** Headshots and logos are sent on request. Add a zip and
  link it from `media-kit.html` if you want self-serve downloads.

---

## Editing

Everything visual is in the HTML files.

- **Ventures** are the `.card` articles in `#ventures` in `index.html`.
- **Now section.** Carries a date ("updated ..."), also shown on the hero
  status board. Change both when you update it.
- **Essays** live in the `ESSAYS` array near the bottom of `index.html`. Each
  object has `kicker`, `read`, `title`, `dek` and `body`. A plain string in
  `body` is a paragraph; `{stand:"..."}` is a pull quote.
- **Enquiry types.** The tabs and hints are the `DOORS` object in each page's
  script. Matching labels for the email subject are in `contact.php`
  (`$labels`) and `api/contact.js` (`LABELS`).
- **Sitemap.** Update `lastmod` in `sitemap.xml` when you change a page, and
  add new pages to it.

## SEO checklist

- Each page has a unique title, description, canonical URL, Open Graph and
  Twitter tags, and JSON-LD structured data.
- Submit `https://siddharthaparmar.com/sitemap.xml` in Google Search Console
  and Bing Webmaster Tools after the first deploy.
- Rich results can be checked at search.google.com/test/rich-results.
- Keep the visible page text and the structured data in step. If you change
  the Aurum FAQ, change the FAQ schema in the page head too.

## Security notes

- `config.php` holds the mail key. `.htaccess` blocks direct access to it.
- The forms have a hidden honeypot field and a per-IP limit of five sends an
  hour, so no captcha is needed at this traffic level.
- Header injection through the name and email fields is blocked in
  `contact.php`.
