---
title: 'Das co-kreative Netzwerk e.V.'
menu: 🏠
body_classes: 'title-center'
template: home
process:
  twig: true

verein_intro: 'Das co-kreative Netzwerk wurde 2023 gegründet mit dem Ziel co-kreative Projekte zu fördern. Ein co-kreatives Projekt erfordert das Zusammenwirken mehrerer Personen über einen gewissen Zeitraum zur Förderung oder Verwirklichung eines gemeinsamen Zweckes.'

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

### Neues aus unserem Blog

- [Vereins-IT: Voll digital](/blog/vereins-it-voll-digital) — 2025-04-19
- [Vereinsgründung: Vision – Menschen – Zeit](/blog/vereinsgruendung-vision-menschen-zeit) — 2025-04-10
