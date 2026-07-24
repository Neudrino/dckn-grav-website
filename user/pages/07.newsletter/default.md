---
title: 'Newsletter'
body_classes: 'title-center'
template: form

form:
  name: newsletter
  fields:
    - name: vorname
      label: Vorname
      type: text
    - name: nachname
      label: Nachname
      type: text
    - name: email
      label: E-Mail-Adresse
      type: email
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

  process:
    - brevo:
        lists: [1]
        field_mappings:
          FIRSTNAME: vorname
          LASTNAME: nachname
    - message: 'Danke! Du bist jetzt für den Newsletter angemeldet.'
---

# Newsletter

Abonniere den DCKN Newsletter, um über co-kreative Projekte und Veranstaltungen informiert zu bleiben.
