---
title: Home
body_classes: 'title-center title-h1h2'
process:
  twig: true

form:
  name: newsletter
  fields:
    - name: vorname
      label: Vorname
      type: text
      placeholder: Vorname
    - name: nachname
      label: Nachname
      type: text
      placeholder: Nachname
    - name: email
      label: E-Mail-Adresse
      type: email
      placeholder: deine@email.de
      validate:
        required: true
    - name: datenschutz
      label: 'Ich akzeptiere die Datenschutzerklärung'
      type: checkbox
      validate:
        required: true

  buttons:
    - type: submit
      value: Abonnieren
      classes: 'btn'

  process:
    - brevo:
        lists: [1]
        field_mappings:
          FIRSTNAME: vorname
          LASTNAME: nachname
    - message: 'Danke! Du bist jetzt für den Newsletter angemeldet.'
---

# Das co-kreative Netzwerk
## co-kreative Projekte fördern

Das co-kreative Netzwerk wurde 2023 gegründet mit dem Ziel co-kreative Projekte zu fördern. Ein co-kreatives Projekt erfordert das Zusammenwirken mehrerer Personen über einen gewissen Zeitraum zur Förderung oder Verwirklichung eines gemeinsamen Zweckes.

<div class="three-col">
<div><a href="/der-verein/vereinsmitglied-werden"><img src="/user/pages/01.home/mitglied-werden.jpeg" alt="Mitglied werden" /></a><a class="btn" href="/der-verein/vereinsmitglied-werden">Mitglied werden</a></div>
<div><a href="/co-kreative-projekte"><img src="/user/pages/01.home/co-kreative-projekte.jpg" alt="Co-kreative Projekte" /></a><a class="btn" href="/co-kreative-projekte">Co-kreative Projekte</a></div>
<div class="newsletter-tile"><h3>Newsletter</h3>{% include "forms/form.html.twig" %}</div>
</div>

### Neues aus unserem Blog

- [Vereins-IT: Voll digital](/blog/vereins-it-voll-digital) — 2025-04-19
- [Vereinsgründung: Vision – Menschen – Zeit](/blog/vereinsgruendung-vision-menschen-zeit) — 2025-04-10
