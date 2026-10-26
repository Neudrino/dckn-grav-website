---
title: 'Auslagenerstattung'
body_classes: 'title-center'
template: formular

form:
  name: auslagenerstattung
  multiple: false
  refresh_pre_enable: false

  fields:

    - name: leistungsdatum
      label: 'Leistungsdatum (Tag der letzten Leistung, falls mehrtägig)'
      type: date
      validate:
        required: true

    - name: projektname
      label: 'Projektname'
      type: text
      validate:
        required: true

    - name: antragsteller
      label: 'Dein Name (Antragsteller:in)'
      type: text
      validate:
        required: true

    - name: email
      label: 'Deine Email-Adresse'
      type: email
      validate:
        required: true

    - name: begruendung
      label: 'Ich beantrage die Erstattung der folgenden Auslagen, die mir im Rahmen der o.g. Veranstaltung des Co-Kreativen Netzwerks e.V. angefallen sind. Diese Auslagen sind mit aus folgendem Grund angefallen. (In der Kürze liegt die Würze!)'
      type: textarea
      validate:
        required: true

    - name: betrag
      label: 'Insgesamt beantrage ich die Erstattung eines Betrages in Höhe von (Euro, brutto)'
      type: number
      step: 0.01
      min: 0
      validate:
        required: true

    - name: anzahl_belege
      label: 'Anzahl der Belege'
      type: number
      min: 1
      validate:
        required: true

    - name: belege
      label: 'Belege hochladen (Mindestens 1 Beleg ist erforderlich! Maximale Dateigröße 1 MB pro Datei! Akzeptierte Dateiformate: PDF, TIFF, PNG, JPG.)'
      type: file
      multiple: true
      accept:
        - application/pdf
        - image/png
        - image/jpeg
        - image/tiff
      filesize: 1
      destination: user/data/auslagenerstattung/belege
      avoid_overwriting: true

    - name: kontoinhaber
      label: 'Kontoinhaber:in'
      type: text
      validate:
        required: true

    - name: iban
      label: 'IBAN'
      type: text
      validate:
        required: true

    - name: bestaetigung
      label: 'Ich versichere, dass alle Angaben vollständig und richtig sind.'
      type: checkbox
      validate:
        required: true

    - name: unterschrift
      label: 'Unterschrift'
      type: signature
      xss_check: false

    - name: captcha
      type: captcha
      provider: cap
      mode: invisible
    - name: honeypot
      type: honeypot

  buttons:
    - type: submit
      value: Senden

  process:
    - email:
        reply_to: "{{ form.value.email }}, vorstand@dckn.de"
        subject: "[DCKN] Auslagenerstattungsantrag von {{ form.value.antragsteller|raw }} für {{ form.value.projektname|raw }}"
        body:
          - body: "{% include 'forms/auslagenerstattung-email.html.twig' %}"
            content_type: text/html
          - body: "{% include 'forms/auslagenerstattung-email.txt.twig' %}"
            content_type: text/plain
        attachments: belege
    - message: 'Danke! Dein Auslagenerstattungsantrag wurde gesendet.'
    - display: /faq/auslagenerstattung
---

# Auslagenerstattung
