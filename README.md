# justopen.app

**Self-hosted deep link generator** — paste a social URL, get a short link on your own domain that opens the native mobile app when possible and falls back to the web everywhere else.

This project is a **free, open-source alternative** to paid SaaS tools that charge for branded short links and app deep linking. You run it on your server; there is no subscription and no vendor lock-in.

You can also use [public, shared version of this software](https://justopen.app).

## What it does

1. **Home page** — You paste a supported URL (YouTube, Instagram, X, Facebook, LinkedIn). The app parses it and returns a **short link** on your domain (e.g. `https://yourdomain.com/yt/dQw4w9WgXcQ`).
2. **Short link visit** — Behavior depends on the device:
   - **Mobile (iOS / Android)** — A small landing page tries to open the **native app** via platform deep links (`youtube://`, `intent://`, etc.), with a fallback button to the canonical web URL.
   - **Desktop** — Immediate **HTTP redirect** to the original web URL (watch page, post, profile, etc.).

Nothing is persisted: **links are not stored in a database or on disk**. Short paths are derived from the original URL using rules in `services.php`, and resolved again on each request. Stateless by design.

## Requirements

| Requirement | Notes |
|-------------|--------|
| **PHP** | 7.4+ recommended (typed code, closures in config) |
| **Web server** | Apache with `mod_rewrite` (see `.htaccess`), or equivalent rewrite to `index.php` |
| **HTTPS** | Strongly recommended so mobile apps and browsers trust your links |

**No configuration file.** Upload the project files to your domain’s `document_root` and it should work. Ensure URL rewriting sends non-file requests to `index.php` (included `.htaccess` does this on Apache).

Optional: edit `translations.php` for copy, or extend `services.php` to add providers — still no env vars or database setup.

## Supported services

| Service | Short prefix | Supported link types |
|---------|--------------|----------------------|
| **YouTube** | `/yt/` | Videos (`watch`, `youtu.be`, Shorts, live, embed), channels (`@handle`) |
| **Instagram** | `/ig/` | Posts, Reels, IGTV (`/p/`, `/reel/`, `/tv/`), profiles |
| **X** (Twitter) | `/x/` | Status / tweet URLs |
| **Facebook** | `/fb/` | Reels, Watch videos (`facebook.com` and `fb.watch`), profile posts, profiles (`facebook.com/<handle>`, `fb.me/<handle>`) |
| **LinkedIn** | `/li/` | Feed activity updates (`urn:li:activity:…`) |

Hostnames and parsing rules live in [`services.php`](services.php). Pull requests that add providers or fix edge-case URLs are welcome.

## Project layout

```
index.php          # Front controller
engine.php         # Routing, parsing, templates, security headers
services.php       # Provider definitions (parse + deep link templates)
translations.php   # UI strings (PL / EN)
templates/         # HTML templates (server-side only; blocked from direct HTTP)
.htaccess          # Rewrite + protect templates/
```

## Author

**Jakub ‘unknow’ Mrugalski** — [mrugalski.pl](https://mrugalski.pl)

## Hosting tip

Probably the **cheapest place to host** this stack is a small VPS — for example **[Mikrus VPS](https://mikr.us)** (Polish micro-hosting, PHP + Apache friendly). Any shared hosting with PHP and rewrite support works too.

## Contributing

Bug fixes, new platforms, and documentation improvements are appreciated. **Send a pull request** on GitHub if you have something to share.

Suggested contributions:

- Additional services or URL patterns in `services.php`
- Translation tweaks in `translations.php`
- Template / accessibility improvements under `templates/`

## License

This project is released under the **[MIT License](LICENSE)**. You are free to use, modify, and distribute it with minimal restrictions; see `LICENSE` for the full text.
