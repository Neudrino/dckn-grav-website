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
      label: 'Ich akzeptiere die auf dieser Seite ausgeführten Bestimmungen des Vereins.'
      type: checkbox
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

Der Verein strebt eine digitale Arbeitsweise an. Da die Informationstechnologie (IT) heutzutage überaus komplex ist, nutzt der Verein digitale Dienstleistungen verschiedener Firmen, um ein modernes und komfortables digitales Angebot anbieten zu können. Dazu gehört auch die Verwaltung der Mitglieder und die Kommunikation mit den Mitgliedern. Der Verein nutzt dazu die folgenden Dienstleister, deren Datenschutzerklärungen ich zur Kenntnis genommen habe und stimme der Verarbeitung der relevanten Daten durch die Dienstleister zu.

- [Datenschutzerklärung](https://www.netcup.de/kontakt/datenschutzerklaerung.php) der [netcup GmbH](https://www.netcup.de/) für die öffentliche Webseite und für die Email-Abos.
- [Datenschutzerklärung](https://www.hetzner.com/de/legal/privacy-policy/) der [Hetzner GmbH](https://www.hetzner.com/de/legal/legal-notice/) für den NextCloud online Dateispeicher zur Ablage aller "Nicht-Webseite-Daten".
- [Datenschutzerklärung](https://gocardless.com/de-de/rechtliches/datenschutz/) der [goCardless Ltd](https://gocardless.com/de-de/rechtliches/) für die Abwicklung der Zahlungen rund um die Mitgliedsbeiträge.
- [Datenschutzerklärung](https://www.brevo.com/de/legal/privacypolicy/) der [Brevo GmbH](https://www.brevo.com) für die Email-Abos.

Da sich die digitale Landschaft fortlaufend wandelt, können sich die eingesetzten Dienste im Laufe der Zeit ändern. Über solche Änderungen wird der Verein die Mitglieder informieren. Sofern nicht binnen vier Wochen nach der Bekanntgabe ein aktiver Widerspruch erfolgt, gilt die Nutzung der geänderten Dienste als akzeptiert.

Das Co-Kreative Netzwerk darf mit meinen Daten arbeiten und dafür auch digitale Dienste Dritter nutzen. Mit der Speicherung, Übermittlung und Verarbeitung meiner personenbezogenen Daten für Vereinszwecke gemäß den Bestimmungen des Bundesdatenschutzgesetzes (BDSG) und der Datenschutzgrundverordnung (DSGVO) bin ich einverstanden. Meine Daten werden nur so lange gespeichert, wie die gesetzlichen Bestimmungen dies erlauben. Ich habe jederzeit die Möglichkeit, vom Verein Auskunft über meine Daten zu erhalten. Meine Daten werden nach meinem Austritt aus dem Verein gelöscht. Für die Inanspruchnahme weiterer Betroffenenrechte erreiche ich als Ansprechpartner:in des Vereins den Vorstand unter: [vorstand@dckn.de](mailto:vorstand@dckn.de).

**Ich versichere vollständige und richtige Angaben zu meiner Person zu machen.**

**Ich bin damit einverstanden, alle Informationen über Vereinsaktivitäten per E-Mail zu erhalten.**

**Ich akzeptiere die [Datenschutzerklärung des Vereins](/impressum#datenschutz).**
