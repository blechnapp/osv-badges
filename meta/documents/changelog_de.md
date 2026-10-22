# Changelog OSVBadges

Neueste Version oben. Jede Version ist ein Git-Tag; im Plugin-Set wird der Tag gewählt.

## 0.7.1 (22.10.2026)

- Lieferschild nach Lieferbarkeit eingefärbt: grün nur noch „Sofort versandfertig“ und eMail-Versand (Gutscheine), orange Vorbestellung wie bisher, blau „Lieferzeit auf Anfrage“, grau alle übrigen. Bisher war jede Lieferbarkeit außer Vorbestellung grün, auch „auf Anfrage“

## 0.7.0 (19.10.2026)

- Kacheln der OSV-Suche wie auf den Kategorieseiten: Lieferschild und am Rechner „Zum Artikel“ (statt des eigenen Knopfs der Suche). Gilt nur für Suchkacheln mit der Markierung osvk-voll (ab OSVSuche 0.10.0, liefert die Lieferdaten mit); ältere Suchkacheln bleiben unverändert. Kategorieseiten unverändert

## 0.6.5 (10.10.2026)

- Nach einem Filterklick springt die Seite an den Anfang der neuen Artikelliste (Sortierzeile oben im Bild, Filter links daneben) statt an die alte Stelle; Ceres allein springt ganz nach oben (Kay: an der alten Stelle landet man mitten in der neuen Liste)

## 0.6.4 (10.10.2026)

- Energielabel wieder oben links im Bild: 0.6.3 hatte den unteren Kachelteil zum Bezugspunkt gemacht, das Label rutschte über den Preis
- Karten einer Reihe gleich hoch (z. B. Paket mit Zeile „1 Stück“)
- Knopf „Zum Artikel“ unten in jeder Kachel am Rechner (ab 992 px; am Handy zeigt Ceres „In den Warenkorb“), Platz fest reserviert; in der Konfiguration abschaltbar, Text änderbar

## 0.6.3 (10.10.2026)

- Seite verschiebt sich beim Scrollen nicht mehr: Ceres baut Kacheln beim Sichtbarwerden neu auf und warf dabei die Lieferzeile raus, die Kachel wurde kurz 26 px kürzer und wieder länger (betraf auch 0.5.x). Platz für die Lieferzeile jetzt fest per CSS reserviert. Gemessen am Handy beim Durchscrollen: Verschiebung durch Kacheln 0,23 → 0
- Filter bleiben zugeklappt, auch nachdem Ceres sie beim Sichtbarwerden neu aufbaut (Zustand in data-Attributen statt Klassen)
- Rücksprung nach Filterklick stellt 8 Sekunden lang nach, falls die Seite sich noch verschiebt; hört auf, sobald der Besucher selbst scrollt

## 0.6.2 (10.10.2026)

- Doppelter Haken bei angehakten Filtern endgültig weg: Ceres setzt sein Symbol vor den Namen (.flex-grow-1::before), nicht vor das Feld

## 0.6.1 (09.10.2026)

- Nach einem Filterklick lädt Ceres die Seite neu und sprang bisher nach oben; die Seite steht jetzt wieder an derselben Stelle
- Angehakte Filter hell hinterlegt statt vollflächig blau, nur noch ein Haken (Ceres-Haken ausgeblendet)
- Suchfeld schon ab 8 Einträgen (z. B. Modell)

## 0.6.0 (09.10.2026)

- Filter auf Kategorieseiten als schlanke Liste: keine Kästen und eigenen Scrollbalken mehr, Trennlinien, Kästchen zum Anhaken
- Häufigste Einträge zuerst (nur Anzeige-Reihenfolge), je Filter 6 sichtbar, Rest über „Alle … anzeigen“
- Suchfeld ab 12 Einträgen (z. B. Motiv, Hersteller), ab 0.6.1 ab 8
- Neu in der Konfiguration: Bereich „Filter auf Kategorieseiten“ (an/aus, Anzahl sichtbar, Suchfeld ab)

## 0.5.2 (09.10.2026)

- Sterne korrekt gefüllt: Sternzeile wieder mit fester Breite (85px, wie im Feedback-Plugin). In 0.5.1 zeigte die Zeile durch display:flex nur einen gefüllten Stern
- Anzahl direkt rechts neben den Sternen statt darüber

## 0.5.1 (09.10.2026) – fehlerhaft, nicht verwenden

- Bewertungssterne auf den Kacheln: Durchschnitt und Anzahl (item.feedbackDecimal, item.feedbackCount) werden für die Kacheldaten mit angefordert; sie fehlten, weil im Shop eine ältere Feldliste die von Ceres überdeckt
- Sterne linksbündig unter der Lieferzeile, mit Anzahl

## 0.5.0 (09.10.2026)

- Kacheln als Karte (Preis vorn, UVP dahinter, Reihen bündig, leere Sterne aus), Lieferbarkeit mit Liefertermin

## 0.4.0 (09.10.2026)

- Gold-Etikett statt rotem Band

## 0.3.0 (09.10.2026)

- Band am unteren Bildrand statt Eckband, Energielabel bleibt frei

## 0.2.0 (09.10.2026)

- Schräges Eckband, Badge-Texte in der Konfiguration

## 0.1.0 (09.10.2026)

- Badge „Sie sparen“ auf Artikelkacheln (Kategorien und OSV-Suche)
