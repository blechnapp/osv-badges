# OSV Badges

Badges auf den Artikelkacheln, auf Kategorieseiten und in den Ergebnissen der OSV-Suche.

## Badge „Sie sparen“
- Erscheint auf jeder Kachel mit Streichpreis, also wenn der Shoppreis unter der Shop-UVP liegt
  (Ceres zeigt den Streichpreis nur dann).
- UVP unter der Grenze (Standard 100 €): „Sie sparen 30 %“, ab der Grenze: „Sie sparen 27,08 €“.
  Gleiche Regel wie das Badge auf der Artikelseite.
- Sind Shoppreis und UVP wieder gleich, zeigt Ceres keinen Streichpreis mehr und das Badge entfällt von selbst.

## Einrichtung
1. Container-Verknüpfung: **OSV Badges** → `Ceres::Script.AfterScriptsLoaded`.
2. Konfiguration „Badge „Sie sparen““: an/aus, Grenze in Euro und die beiden Texte.
   `{prozent}` und `{euro}` werden durch den Betrag ersetzt, z. B. „Du sparst {prozent}“.
   Passt ein Text nicht ins Band, endet er mit „…“.

## Hinweise
- Das Badge ist ein goldenes Etikett (Gold der Shop-Leiste, Schrift dunkelblau) am unteren Bildrand,
  links angesetzt. Es liegt auf dem Bild, verschiebt nichts und lässt die Ecke oben links für das Energielabel frei.
- Das Skript setzt die Badges nach dem Start der Ceres-App und sieht danach jede Sekunde nach,
  damit auch Kacheln nach Filtern, Blättern oder aus der OSV-Suche ihr Badge bekommen.
- Weitere Badges: im Skript eine Regel in `REGELN` anhängen.
- Kein MutationObserver, kein Vue.component, kein Zugriff auf den Ceres-Store.

## Versionen
- 0.4.0: Gold-Etikett statt rotem Band.
- 0.3.0: Band am unteren Bildrand statt Eckband (Energielabel oben links bleibt frei).
- 0.2.0: Schräges Eckband statt Kästchen; Texte in der Konfiguration.
- 0.1.0: Badge „Sie sparen“.
