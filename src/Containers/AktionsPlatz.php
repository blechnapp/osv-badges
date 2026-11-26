<?php

namespace OSVBadges\Containers;

use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Http\Request;

/**
 * Hält im Seitenkopf den Platz der Aktionskachel (Stelle 1) im Artikelraster frei, bevor die Seite gezeichnet wird.
 * Die Kachel selbst setzt das Skript erst nach dem Start von Ceres ein; ohne freigehaltenen Platz schöbe sie die
 * Artikelkarten weg (CLS am Rechner bis 0,08 gemessen, 26.11.2026). Das Skript setzt danach osvak-drin an <html>.
 */
class AktionsPlatz
{
    const AKTIONEN = 3;

    private function tag(string $wert): string
    {
        $wert = trim($wert);
        if ($wert === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert)) {
            return $wert;
        }
        $zeit = strtotime($wert);
        return $zeit ? date('Y-m-d', $zeit) : '';
    }

    public function call(ConfigRepository $config): string
    {
        /** @var Request $request */
        $request = pluginApp(Request::class);
        if ((int)$request->get('page', 1) > 1) {
            return '';
        }
        $heute = date('Y-m-d');
        $selektoren = [];
        for ($n = 1; $n <= self::AKTIONEN; $n++) {
            $p = 'OSVBadges.aktion' . $n . '.';
            $an = $config->get($p . 'aktiv', 'false');
            if ($an === 'false' || $an === false || $an === '0' || $an === '' || $an === null) {
                continue;
            }
            if (max(1, (int)$config->get($p . 'stelle', '1')) !== 1) {
                continue;
            }
            $von = $this->tag((string)$config->get($p . 'von', ''));
            $bis = $this->tag((string)$config->get($p . 'bis', ''));
            if (($von !== '' && $heute < $von) || ($bis !== '' && $heute > $bis)) {
                continue;
            }
            foreach (explode(',', (string)$config->get($p . 'kategorien', '')) as $teil) {
                $id = (int)trim($teil);
                if ($id > 0) {
                    $selektoren[] = 'html:not(.osvak-drin) body.category-' . $id . ' .widget-item-grid .product-list::before';
                }
            }
        }
        if (!count($selektoren)) {
            return '';
        }
        $liste = implode(', ', array_unique($selektoren));
        // Tablet: allein in der Reihe, deshalb feste Höhe (gleich .osvak-li); ab 992 px streckt die Reihe den Platz auf Kartenhöhe
        return '<style>@media (min-width: 768px) { ' . $liste . ' { content: ""; display: block; flex: 0 0 100%; max-width: 100%; height: 430px; } }'
            . ' @media (min-width: 992px) { ' . $liste . ' { flex-basis: 50%; max-width: 50%; height: auto; } }</style>';
    }
}
