# Bot-Erkennung: `bot_regexp` (Page Insights)

**Zweck:** Diese Datei dokumentiert die Bot-Filterliste des Grav-Plugins
`page-insights` für **dckn.de**. Sie ist die einzige Stelle, an der das Plugin
entscheidet, ob ein Request ein Bot ist – und zwar ausschließlich anhand des
User-Agent-Strings.

## Wie die Erkennung funktioniert

| Punkt | Verhalten |
|---|---|
| Prüfung | `preg_match('/' . implode('|', bot_regexp) . '/i', $_SERVER['HTTP_USER_AGENT'])` |
| Zeitpunkt | **einmalig beim INSERT**; Änderungen wirken nie rückwirkend |
| Delimiter | `/` – deshalb müssen Schrägstriche in Mustern als `\/` geschrieben werden |
| Groß/Klein | case-insensitiv (`i`) |
| Alternativen | Liste wird mit `|` verkettet → **Gruppieren in einem Eintrag ändert das Verhalten nicht** |

Was das Plugin **nicht** kann: Erkennung über IP, Rate, Burst, Route, HTTP-Code,
Land oder Verhalten. Solche Regeln laufen ausschließlich als SQL-Nachlauf
außerhalb des Plugins.

## Aktuelle Liste

```yaml
bot_regexp:
    - 'bot'
    - '^$'
    - '^\s*$'
    - 'crawler'
    - 'facebookexternalhit'
    - 'spider'
    - 'yabrowser\/20\.'
    - 'python'
    - 'search'
    - 'curl'
    - 'java\/'
    - 'go-http-client'
    - 'wordpress\/'
    - '^Mozilla\/5\.0$'
    - 'mozlila'
    - 'fetch'
    - '\(gort\)'
    - '^mozilla\/5\.0 \(compatible\)$'
    - 'Motor'
    - 'yeti'
    - 'mozilla\/5\.0 applewebkit\/537\.36 \(khtml, like gecko\) chrome\/123'
    - 'baidu'
    - 'find'
    - 'mozilla\/5\.0[0-9]'
    - 'webs'
    - 'lovense'
    - 'meta-webindexer'
    - 'slurp'
    - 'ia_archiver'
    - 'yandex'
    - 'archive.org_bot'
    - 'petalbot'
    - 'chrome\/131\.0\.0\.0|chrome\/81\.0\.4044\.|chrome\/151\.0\.0\.0|chrome\/[0-9]+\.0 safari|chrome\/49\.0\.2623|chrome\/130\.0\.0\.0|chrome\/125\.0\.0\.0|chrome\/104\.0\.0\.0|chrome\/62\.0\.3202\.94|chrome\/124\.0\.0\.0|chrome\/146\.0\.0\.0|chrome\/135\.0\.0\.0|chrome\/129\.0\.0\.0|chrome\/120 safari|chrome\/73\.0\.3683\.75|chrome\/80\.0\.3987\.13|chrome\/118\.0\.0\.0|chrome\/128\.0\.0\.0|chrome\/90\.0\.4430\.85|chrome\/116\.0\.0\.0|chrome\/126\.0\.0\.0|chrome\/119\.0\.0\.0|chrome\/99\.0\.4844\.51|chrome\/84\.0\.4128\.0'
    - 'networkingextension\/|ddg_win|Electron|com.duckduckgo|whatsapp\/|com\.apple\.|mobilesafari\/|emclient\/|skypeuripreview|telegram\/|adobeuxtech|dalvik\/|HeadlessChrome|iphone os 13_|iphone os 17_0'
    - 'safari\/537\.3$|version\/16\.0 safari|webkit\/536\.5|applewebkit\/537\.36$|applewebkit\/537\.36 \(khtml, like gecko\) (safari|opera|edge|firefox)\/|applewebkit\/537\.4[2-9]'
    - 'firefox\/155\.0|firefox\/126\.0|firefox\/133\.0|firefox\/74\.0'
    - 'stripepluginscanner|internetdata\.io|cms-checker|waslos|hylki|stretchoid|hedgehogscan|authorized-exposure-audit|redirectchecker|domainintelligence|GoogleOther|defaultsexposed|zoomeye|shodan|domainharvester|owcareerspageverifier|promote-system-domain-health|domainbox-domain-snapshot|forestengine|clst-pab|idg\/eu|Google-Extended|meta-externalagent|meta-externalfetcher|anthropic-ai|cohere-ai|Webzio-Extended|omgili'
    - 'edg\/149\.0\.0\.0|crios\/87|opera mini\/7\.6'
```

## Herkunft / Quellen

- Plugin-Standardliste (268 Einträge, `user/plugins/page-insights/page-insights.yaml`)
- eigene Auswertung von 7.004 Requests (29.10.–08.11.2026)
- <https://github.com/ai-robots-txt/ai.robots.txt> für neue KI-Crawler
