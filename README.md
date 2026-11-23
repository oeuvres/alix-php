# alix-php — minimal frontend skeleton

PHP 8.1+, FastRoute 1.3, native PHP templates. No CSS or JavaScript.

## Install

```sh
composer install
```

Edit `config.php`:

- `api`: Alix servlet base URL, before the corpus name
- `mode`: `single` or `multi` (explicit; not derived from the number of corpora)
- `default`: corpus name for single mode
- `cacheFile`: writable file outside the web root for the servlet's `/` JSON
- `cacheTtl`: number of seconds before attempting a refresh (default 300)

No local list of bases or document regexes. Alix's root JSON provides names,
labels, counts, and optional `docprefix`. `docprefix` is a whitespace-separated
list of **literal prefixes**, not glob or regex patterns. `docprefix` is optional.

The root registry is fetched on the first request, then cached to disk.
Concurrent refreshes are serialized with `flock`, and an old cache is retained
when a refresh fails. Search results and documents are always fetched live.
If newly published metadata is needed immediately, delete `var/cache/bases.json`
(and keep the directory writable).

To try the PHP development server:

```sh
php -S 127.0.0.1:8000 -t public public/index.php
```

Apache may use `public/` as its document root with `.htaccess`.
With nginx, use `try_files $uri /index.php?$query_string;` for the `public/` root.

## Routes

| Page | `single` | `multi` |
|---|---|---|
| Corpus directory | — | `/` |
| Corpus welcome | `/` | `/{base}` |
| Search | `/q?q=amour` | `/{base}/q?q=amour` |
| Document matching `docprefix` | `/{doc}` | `/{base}/{doc}` |
| Other document | `/doc/{doc}` | `/{base}/doc/{doc}` |

If no `docprefix` exists, only the `/doc/{doc}` route is accepted for documents.
The servlet performs the actual document existence check. Static routes like
`/q` are reserved for the application and take priority over document paths.

## HTML fragments

Search uses `/{base}/results.html?q=...`, while document pages use
`/{base}/{doc}.html`. The PHP frontend does not duplicate the servlet's JSON
field inventory; it only reads the root registry needed for navigation.

The servlet emits relative links to documents inside `results.html`. The PHP
client adjusts those anchors to the appropriate public document paths, preserving
query strings and fragments. Relative `?from=...` pagination URLs are untouched.
This rewriting is intended specifically for Alix's generated results markup.

HTML fragments are rendered unescaped; configure only a trusted Alix servlet.

## Files

- `public/index.php`: routing and page controller
- `src/Alix.php`: HTTP client plus file-backed corpus registry cache
- `src/functions.php`: URL helpers, fragment link rewriting, native templates
- `templates/`: common layout and welcome, search, document, error pages

FastRoute is used by the reference application, not by the `Alix` client class.
