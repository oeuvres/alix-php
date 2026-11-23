# alix-php — minimal frontend skeleton

PHP 8.1+, FastRoute 1.3, native PHP templates. No CSS or JavaScript.

## Install

```sh
composer install
```

Edit `config.php`:

- `api`: base URL of the Alix servlet (before the index name)
- `mode`: `single` or `multi`
- `default`: base used by single mode
- `bases`: public base keys mapped to servlet index names, titles, and document ID regexes

To try the PHP development server:

```sh
php -S 127.0.0.1:8000 -t public public/index.php
```

Apache may use `public/` as its document root with the included `.htaccess`.
With nginx, use `try_files $uri /index.php?$query_string;` (configured for the `public/` document root).

## Routes

| Page | `single` | `multi` |
|---|---|---|
| Base directory | — | `/` |
| Base welcome | `/` | `/{base}` |
| Search | `/q?q=amour` | `/{base}/q?q=amour` |
| Document | `/{doc}` | `/{base}/{doc}` |

A configured `docPattern` constrains document identifiers. In `multi` mode,
this validation occurs after route matching because each base can use a different pattern.

## HTML fragments

Search pages request `/{index}/results.html?q=…` from Alix.
Document pages request `/{index}/{doc}.html` from Alix, using the servlet's
direct document ID fallback.

HTML responses are inserted directly in `q.php` and `doc.php`. This assumes
that the configured Alix servlet and its indexed HTML are trusted.
Relative document links in search results resolve to the short public paths
because the search URL is `/q` or `/{base}/q`.

## Files

- `public/index.php`: front controller and routes
- `src/Alix.php`: tiny HTTP client, independent of FastRoute
- `src/functions.php`: URL generation and native template rendering
- `templates/layout.php`: common HTML shell
- `templates/bases.php`: multi-base welcome
- `templates/base.php`: one-base welcome
- `templates/q.php`: search form and result fragment
- `templates/doc.php`: document fragment

The reference app depends on FastRoute; the `Alix` client does not.

