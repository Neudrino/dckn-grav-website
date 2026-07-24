---
title: 'Editing content'
date: '2026-07-24 13:00'
---

# Editing content

There are two ways to edit Grav content:

1. **The Admin2 panel** — browse to `/admin`, log in with the account
   you created on first launch, and edit pages through the web UI.
2. **The filesystem** — edit the Markdown files directly under
   `user/pages/`. Changes appear on reload; no rebuild needed.

> [!TIP]
> The admin panel and the filesystem stay in sync: edits made in the
  admin are written back to the same Markdown files.

## Page frontmatter

Each page starts with a YAML frontmatter block between `---` lines:

```yaml
---
title: 'My Page'
date: '2026-07-24'
body_classes: 'title-center'
---
```

Only `title` is required. Everything else is optional metadata that
templates can use.

## Folder ordering

Folders are prefixed with a number (`01.home`, `02.typography`,
`03.about`) to set menu order. Prefix with `_` to hide from the menu
while keeping the page routable (useful for blog posts).

Back to the [Blog index](../..).
