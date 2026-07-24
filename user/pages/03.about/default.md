---
title: 'About'
body_classes: 'title-center'
---

# About this site

This page is part of the example content that ships with the local Grav
test setup. It demonstrates how to add a simple static page with
[Markdown](https://learn.getgrav.org/content/markdown).

## What is Grav?

Grav is a **fast**, **simple**, and **flexible** file-based CMS — no
database required. Pages live as folders of Markdown files under
`user/pages/`.

> [!TIP]
> Edit this file at `user/pages/03.about/default.md` and reload the
> browser to see your changes instantly.

## The local test setup

This instance runs inside a Docker container based on the
`linuxserver/grav` image, with the `user/pages` directory bind-mounted
from the host so content is version-controlled alongside the
`docker-compose.yml`.

| Component | Value |
| :--- | :--- |
| Image | `lscr.io/linuxserver/grav:latest` |
| Theme | Quark 2 (default) |
| Admin | Admin2 plugin (preinstalled) |
| Host port | 8080 |

## Links

- [Grav documentation](https://learn.getgrav.org)
- [Grav downloads](https://getgrav.org/downloads)
- [Quark 2 theme](https://github.com/getgrav/grav-theme-quark2)
