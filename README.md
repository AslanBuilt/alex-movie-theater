# Alex Movie Theatre — Website Rebuild

A rebuilt website for **Alex Movie Theatre** in Alexandria, Indiana. Two-screen independent cinema serving the local community with affordable tickets.

## Live Preview

**https://2ktay.github.io/alex-movie-theater/**

## Pages

| Page | URL |
|------|-----|
| Now Showing | [/](https://2ktay.github.io/alex-movie-theater/) |
| Senior Movie | [/senior-movie.html](https://2ktay.github.io/alex-movie-theater/senior-movie.html) |
| Concessions | [/concessions.html](https://2ktay.github.io/alex-movie-theater/concessions.html) |
| Events | [/events.html](https://2ktay.github.io/alex-movie-theater/events.html) |
| Private Screenings | [/private-screenings.html](https://2ktay.github.io/alex-movie-theater/private-screenings.html) |
| Location & Parking | [/location.html](https://2ktay.github.io/alex-movie-theater/location.html) |
| Contact | [/contact.html](https://2ktay.github.io/alex-movie-theater/contact.html) |

## Tech Stack

- PHP 8.0+
- Vanilla HTML / CSS / JavaScript
- Deployed via FTP to parityrfp.com
- No database required

## Design

- **Theme:** Boutique cinema — dark warm background, deep burgundy/crimson accents, warm cream text
- **Typography:** Playfair Display (headings) + Lato (body)
- **Inspired by:** Independent/boutique cinema aesthetics, not big chain templates
- **Built with:** Aslan Skills (`project-scaffolding`, `professional-frontend-design`, `seo-sao-optimization`, `ftp-deploy-parity`, `formspree-integration`, `htaccess-management`, `production-readiness-audit`)

## Project Structure

```
alex-movie-theater/
├── public/              # Web root — all pages and assets
│   ├── index.php
│   ├── senior-movie.php
│   ├── concessions.php
│   ├── events.php
│   ├── private-screenings.php
│   ├── location.php
│   ├── contact.php
│   ├── .htaccess
│   ├── robots.txt
│   ├── sitemap.xml
│   ├── llms.txt
│   └── assets/
│       ├── css/main.css
│       ├── js/main.js
│       └── images/          # Movie poster images
├── config/
│   └── config.php       # Site constants (URL, phone, form links)
├── includes/
│   └── helpers.php      # Utility functions (e, asset, url)
└── templates/
    ├── header.php
    └── footer.php
```

## Client Info

- **Theatre:** Alex Movie Theatre
- **Address:** 407 N. Harrison Street, Alexandria, IN 46001
- **Phone:** 765-620-9093
- **Tickets:** Square Online Store (being replaced with on-site Stripe checkout)
- **Contact Form:** Formspree (`xaqkjakn`)
