---
title: 'Projektförderung beantragen'
body_classes: 'title-center'
template: formular

form:
  name: projektantrag
  multiple: false
  refresh_pre_enable: false

  fields:

    - name: antragsteller
      label: 'Dein Name'
      type: text
      autocomplete: name
      validate:
        required: true

    - name: meldeadresse
      label: 'Deine Meldeadresse'
      type: text
      validate:
        required: true

    - name: email
      label: 'Deine E-Mail-Adresse'
      type: email
      validate:
        required: true

    - name: telefon
      label: 'Dein Telefonkontakt'
      type: tel
      autocomplete: tel
      validate:
        required: true

    - name: projekttitel
      label: 'Titel'
      type: text
      validate:
        required: true

    - name: startdatum
      label: 'Startdatum'
      type: date
      validate:
        required: true

    - name: veranstaltungsart
      label: 'Veranstaltungsart'
      type: text
      validate:
        required: true

    - name: ort
      label: 'Adresse / Ort'
      type: text
      validate:
        required: true

    - name: gesamtumsatz
      label: 'Gesamtumsatz in Euro (laut Kalkulation)'
      type: number
      validate:
        required: true
        max: 10000000
        step: 100

    - name: groesste_einzelausgabe
      label: 'Größte Einzelausgabe in Euro'
      type: number
      validate:
        required: true
        max: 100000
        step: 100

    - name: mitorganisatoren
      label: 'Mitorganisator:inn:en'
      type: text
      validate:
        required: true

    - name: teilnehmer_min
      label: 'Minimale Teilnehmerzahl'
      type: number
      validate:
        required: true

    - name: teilnehmer_max
      label: 'Maximale Teilnehmerzahl'
      type: number
      validate:
        required: true

    - name: zwecke
      label: 'Folgende [Vereinszwecke](https://dckn.de/der-verein/vereinssatzung/) werden gefördert:'
      type: checkboxes
      markdown: true
      use: values
      vertical: true
      wrapper_classes: zwecke-list
      options:
        'Kunst und Kultur': 'Kunst und Kultur'
        'Jugend- und Altenhilfe': 'Jugend- und Altenhilfe'
        'internationale Gesinnung, Toleranz auf allen Gebieten der Kultur und des Völkerverständigungsgedankens': 'internationale Gesinnung, Toleranz auf allen Gebieten der Kultur und des Völkerverständigungsgedankens'
        'Gleichberechtigung von Frauen und Männern': 'Gleichberechtigung von Frauen und Männern'
        'Sport': 'Sport'
        'bürgerschaftliches Engagement zugunsten gemeinnütziger Zwecke': 'bürgerschaftliches Engagement zugunsten gemeinnütziger Zwecke'

    - name: kurzbeschreibung
      label: 'Kurzbeschreibung (Ziel und grober Ablauf, max. 1000 Zeichen)'
      type: textarea
      validate:
        max: 1000

    - name: cokreation
      label: 'Förderung von Co-Kreation erfolgt auf folgende Weise (max. 1000 Zeichen)'
      type: textarea
      validate:
        max: 1000

    - name: kalkulation
      label: 'Deine Kalkulation ([ODS-Datei aus unseren Vorlagen](https://nextcloud.dckn.de/s/3JeBgMtZgFr87fq))'
      type: file
      markdown: true
      multiple: false
      accept:
        - .ods
        - application/vnd.oasis.opendocument.spreadsheet
      destination: user/data/projektantrag/kalkulation
      avoid_overwriting: true
      validate:
        required: true

    - name: bestaetigung
      label: 'Ich beachte die Rahmenbedingungen und versichere, dass alle Angaben vollständig und richtig sind.'
      type: checkbox
      validate:
        required: true

    - name: unterschrift
      label: 'Unterschrift'
      type: signature
      xss_check: false

  buttons:
    - type: submit
      value: Senden

  process:
    - email:
        from: "{{ form.value.email }}"
        to: vorstand@dckn.de
        subject: "Projektantrag „{{ form.value.projekttitel }}“ von {{ form.value.antragsteller }}"
        body: "{% include 'forms/data.html.twig' %}"
        attachments: kalkulation
    - message: 'Danke! Dein Projektantrag wurde gesendet.'
    - display: /der-verein/projektfoerderung-beantragen
---

# Projektförderung beantragen

Die Veranstaltung eines Projektes über Das Co-Kreative Netzwerk e.V. bringt einige Vorteile, aber auch einige Auflagen mit sich.

## Was wir bieten

- Möglichkeit (öffentliche) **Fördergelder** zu beantragen und erhalten. Diese werden üblicherweise nur an nachgewiesen gemeinnützige Organisationen abgegeben.
- **Vereinsversicherungspaket** des [Deutschen Ehrenamts](https://deutsches-ehrenamt.de/schutz-vereine/versicherung-fuer-vereine/)
- Nutzung der **IT-Infrastruktur** des Vereins
    - Internetseite in der Kategorie "Co-Kreative Projekte"
    - QR-Code zur Webseite des Projekts
    - Aufnahme der Veranstaltung in unseren Newsletter
    - Emailadresse (bei größeren Projekten)
    - online Speicherplatz (in Deutschland) für projektbezogene Daten (z.B. zum Fototausch)
- Reichhaltiger Wissensfundus und Erfahrungsschatz rund um die Veranstaltungsorganisation

## Was wir fordern

Als gemeinnütziger Verein haben wir einige zusätzliche Verpflichtungen, insbesondere bei Themen wie Steuern und Abrechnung.

- Erfüllung eines satzungsgemäßen Vereinszwecks und Praktizieren von Co-Kreation
- Anträge werden ausschließlich über das [Projektantragsformular](#projektantrag) (unten) entgegengenommen
- [Projektkalkulation mit positiver Bilanz und Abrechnung entsprechend unserer Vorlagen](https://nextcloud.dckn.de/s/3JeBgMtZgFr87fq)
    - Besondere Berücksichtigung allerlei Steuern und Abgaben (UStG, EStG, Künstlersozialkasse, …)
    - Lückenlose Dokumentation der Geldflüsse entsprechend der Anforderungen an eine steuerliche Einkommensüberschussrechnung (EÜR)
    - Beteiligung an den allgemeinen Vereinsausgaben mit einem gewissen Prozentsatz
- Nutzung der bereitgestellten Vorlagen für Verträge und Abrechnung
- Das Werbematerial enthält das DCKN Logo sichtbar.
- Auf der Veranstaltung wird DCKN sichtbar repräsentiert (z.B. durch aushängendes Plakat).
- _Alle Ausgaben_ des Vereins erfolgen ausschließlich _bargeldlos_ vom Konto des Vereins.
    - [Für Auslagenerstattungen wird das entsprechende Formular genutzt.](/faq/auslagenerstattung)

## <a id="projektantrag"></a>Projektantrag stellen
