---
title: 'Vereinsmitglied werden – Kommunikation'
body_classes: 'title-center'
template: formular
visible: false

form:
  name: vereinsmitglied_werden_kommunikation
  multiple: false
  refresh_pre_enable: false

  fields:

    - name: vorname
      label: 'Vorname'
      type: text
      validate:
        required: true

    - name: nachname
      label: 'Nachname'
      type: text
      validate:
        required: true

    - name: email
      label: 'E-Mail-Adresse'
      type: email
      validate:
        required: true

    - name: datenschutz
      label: 'Hiermit akzeptiere ich die Datenschutzbestimmungen'
      type: checkbox
      validate:
        required: true

    - name: hcaptcha
      type: hcaptcha
      validate:
        required: true

  buttons:
    - type: submit
      value: Senden

  process:
    - brevo:
        lists: [2]
        field_mappings:
          FIRSTNAME: vorname
          LASTNAME: nachname
    - email:
        from: "{{ form.value.email }}"
        to: vorstand@dckn.de
        subject: "Mitgliedsantrag (Kommunikation) von {{ form.value.vorname }} {{ form.value.nachname }}"
        body: "{% include 'forms/data.html.twig' %}"
    - message: 'Danke! Dein Mitgliedsantrag wurde gesendet.'
    - display: /der-verein/vereinsmitglied-werden
---

# Vereinsmitglied werden – Kommunikation

Hiermit beantrage ich meine Aufnahme in den Verein "**Das Co-Kreative Netzwerk e.V.**".

**Mit meiner Mitgliedschaft erkenne ich die [aktuelle Fassung der Vereinssatzung](/der-verein/vereinssatzung) und die gültige Beitragsordnung an.**

Ich bin damit einverstanden, alle Informationen über Vereinsaktivitäten per E-Mail zu erhalten. Da unser Verein ist überregional tätig ist, läuft unsere Kommunikation per Email oder Chat und unsere Mitgliederversammlungen finden digital statt.

Das Co-Kreative Netzwerk darf mit meinen Daten arbeiten. Mit der Speicherung, Übermittlung und Verarbeitung meiner personenbezogenen Daten für Vereinszwecke gemäß den Bestimmungen des Bundesdatenschutzgesetzes (BDSG) und der Datenschutzgrundverordnung (DSGVO) bin ich einverstanden. Meine Daten werden nur so lange gespeichert, wie die gesetzlichen Bestimmungen dies erlauben. Ich habe jederzeit die Möglichkeit, vom Verein Auskunft über meine Daten zu erhalten. Meine Daten werden nach meinem Austritt aus dem Verein gelöscht. Für die Inanspruchnahme weiterer Betroffenenrechte erreiche ich als Ansprechpartner:in des Vereins den Vorstand unter: [vorstand@dckn.de](mailto:vorstand@dckn.de).

Der Verein strebt eine digitale Arbeitsweise an. Die Informationstechnologie (IT) ist heutzutage viel zu komplex, als dass der Verein alles selbst machen (und hosten) könnte, sodass digitale Dienstleistungen von verschiedenen Firmen genutzt werden. Dazu gehört auch die Verwaltung der Mitglieder und die Kommunikation mit den Mitgliedern. Der Verein nutzt dazu hauptsächlich die folgenden 3 Dienstleister, deren Datenschutzerklärungen ich zur Kenntnis genommen habe und stimme der Verarbeitung der relevanten Daten durch die Dienstleister zu.

- [Datenschutzerklärung](https://www.netcup.de/kontakt/datenschutzerklaerung.php) der [netcup GmbH](https://www.netcup.de/) für die Webseite und die Email-Abos.
- [Datenschutzerklärung](https://www.hetzner.com/de/legal/privacy-policy/) der [Hetzner GmbH](https://www.hetzner.com/de/legal/legal-notice/) für den NextCloud online Dateispeicher zur Ablage aller "Nicht-Webseite-Daten".
- [Datenschutzerklärung](https://gocardless.com/de-de/rechtliches/datenschutz/) der [goCardless Ltd](https://gocardless.com/de-de/rechtliches/) für die Abwicklung der Zahlungen rund um die Mitgliedsbeiträge.

**Ich versichere vollständige und richtige Angaben zu meiner Person zu machen.**
**Ich bin damit einverstanden, alle Informationen über Vereinsaktivitäten per E-Mail zu erhalten.**
