# Public WordPress snapshot

`dimitrium-content.xml` is a sanitised WordPress WXR export of public
Dimitrium content. It includes published pages, posts, Dimipedia entries, news,
template parts, navigation, global styles, fonts, terms, post metadata, and
attachment records.

`site-settings.json` records the selected public configuration needed to
understand the install: permalink structure, active plugins, Polylang settings,
Assembler theme mods, and CPT UI definitions. Values with credential-like keys
are redacted.

Neither file is a database dump. User e-mail addresses, post passwords,
non-published posts, secrets, and runtime-only settings are intentionally
excluded. Public media files live in `wp-content/uploads/` and are tracked with
Git LFS.
