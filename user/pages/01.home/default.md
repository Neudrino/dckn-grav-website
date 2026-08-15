---
title: 🏠
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
    - name: datenschutz_link
      label: false
      type: display
      markdown: true
      content: "Hier findest du unsere [Datenschutzerklärung](/impressum#datenschutz)."
    - name: datenschutz
      label: 'Ich akzeptiere die Datenschutzerklärung.'
      type: checkbox
      validate:
        required: true
    - name: hcaptcha
      type: hcaptcha
      validate:
        required: true

  buttons:
    - type: submit
      value: Abonnieren
      classes: 'btn'

  process:
    - brevo:
        lists: [3]
        field_mappings:
          FIRSTNAME: vorname
          LASTNAME: nachname
    - message: 'Danke! Du bist jetzt für den Newsletter angemeldet.'
---

# Das co-kreative Netzwerk e.V.

===
