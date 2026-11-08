# Ubuntu fonts — static instances

Bundled for PDF rendering by the `form-pdf` plugin (`Grav\Plugin\FormPdf\PdfRenderer::phpPdf`).

| File | PostScript name | Source |
|---|---|---|
| `Ubuntu-Regular.ttf` | `Ubuntu-Regular` | Ubuntu[wdth,wght].ttf @ wdth=100, wght=400 |
| `Ubuntu-Bold.ttf` | `Ubuntu-Bold` | Ubuntu[wdth,wght].ttf @ wdth=100, wght=700 |
| `Ubuntu-Italic.ttf` | `Ubuntu-Italic` | Ubuntu-Italic[wdth,wght].ttf @ wdth=100, wght=400 |
| `Ubuntu-BoldItalic.ttf` | `Ubuntu-BoldItalic` | Ubuntu-Italic[wdth,wght].ttf @ wdth=100, wght=700 |

Provenance: Ubuntu Font Family, fontversion 56951 (Debian/Ubuntu package
`fonts-ubuntu 0.869+git20240321`, upstream https://design.ubuntu.com/font/),
instantiated from the variable build with »

```
uvx --from fonttools fonttools varLib.instancer --static --update-name-table \
    -o Ubuntu-Regular.ttf 'Ubuntu[wdth,wght].ttf' wdth=100 wght=400
```

PostScript names of the italic instances were normalized back to
`Ubuntu-Italic` / `Ubuntu-BoldItalic` afterwards, because fontTools'
`--update-name-table` emitted `UbuntuItalic-*` names that did not fit the
"Ubuntu" family.

Licence: Ubuntu Font Licence 1.0 (see `LICENCE.txt`). Bundling
static instances of the variable build is a non-substantially-changed
derivative; the family name stays "Ubuntu".
