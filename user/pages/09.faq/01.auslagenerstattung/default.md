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
      validate:
        required: true

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
      type: text
      validate:
        required: true

    - name: math_captcha
      label: '17 + 4 ='
      type: text
      validate:
        required: true
        pattern: '21'

  buttons:
    - type: submit
      value: Senden

  process:
    - email:
        from: "{{ form.value.email }}"
        to: vorstand@dckn.de
        subject: "Auslagenerstattungsantrag von {{ form.value.antragsteller }}"
        body: "{% include 'forms/data.html.twig' %}"
        attachments: belege
    - message: 'Danke! Dein Auslagenerstattungsantrag wurde gesendet.'
    - display: /faq/auslagenerstattung
---

# Auslagenerstattung

_Aus unklaren Gründen kommt in manchen Browsern beim ersten Klick auf Senden ein Fehler. Probiere es dann einfach direkt noch einmal. An der grünen Meldung und dem Zurücksetzen der Formulare erkennst du das erfolgreiche Absenden._
