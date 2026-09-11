# Dimitrium.org

Open-source custom code behind [dimitrium.org](https://dimitrium.org): a
multilingual WordPress site about free software, music, self-hosted
infrastructure, table tennis, and community-owned tools.

The site's custom code is available under the
[GNU Affero General Public License v3.0 or later](LICENSE). Read
[LICENSES.md](LICENSES.md) for the precise scope, third-party notices, and
network-source obligations. Site content and media are covered separately in
[CONTENT-LICENSE.md](CONTENT-LICENSE.md).

## What is here

```text
wp-content/
├── mu-plugins/
│   └── dimitrium-language-home-redirects.php
└── plugins/
├── dimipedia/                 # Multilingual knowledge-base content type
├── dimipress-nightify/        # WordPress admin colour mode
├── dimitrium-now-playing/     # Music player, site interactions and WebP delivery
└── dimitrium-seo/             # Metadata, hreflang, sitemap and Schema.org graph
exports/                       # Sanitised public WordPress content/configuration
scripts/                       # Repeatable snapshot exporter
wp-content/themes/assembler-wpcom/ # Active GPL-licensed WordPress theme
wp-content/uploads/            # Public media, tracked with Git LFS
```

The repository deliberately excludes WordPress core, the raw database, secrets,
private production configuration, and third-party vendor plugins. It includes a
sanitised public export rather than a database dump.

Paid production-only assets are intentionally absent: the Minicomputer font
family and the menu, close, and social SVG icon files. See
[PROPRIETARY-ASSETS.md](PROPRIETARY-ASSETS.md) before using this snapshot in a
local installation.

## Production dependencies

The site currently uses WordPress with:

- the included Assembler theme;
- Polylang for Serbian and English;
- Custom Post Type UI, Darkify, Lightbox with PhotoSwipe, and Safe SVG;
- the custom plugins and MU-plugin contained in this repository.

Install WordPress and third-party dependencies from their official sources,
then place this repository's `wp-content` directory alongside that install.
Before using the snapshot, install Git LFS and run `git lfs pull`. Import
`exports/dimitrium-content.xml` through **Tools → Import → WordPress**, then
review `exports/site-settings.json` and apply only the documented public
settings that your local environment needs.

## Local development

Use a disposable local WordPress + MariaDB environment. Copy or symlink the
repository's custom plugin directories into `wp-content/plugins/`, and copy
the MU-plugin into `wp-content/mu-plugins/`. Install the listed dependencies,
activate the applicable plugins, and configure Polylang before importing any
non-production test content.

Never commit `wp-config.php`, `.env` files, database dumps containing private
data, credentials, or server-specific configuration. The included `.gitignore`
is a guardrail, not a substitute for reviewing every commit.

## Source availability

This repository is the public source location for the custom AGPL-covered code
running on Dimitrium.org. The production footer links here as `Source code /
Izvorni kod`. Forks and modified public deployments must preserve the
applicable AGPL obligations, including the network source offer.
