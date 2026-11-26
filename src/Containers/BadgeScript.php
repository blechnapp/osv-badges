<?php

namespace OSVBadges\Containers;

use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Plenty\Modules\Webshop\ItemSearch\SearchPresets\VariationList;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;
use Plenty\Modules\Order\Coupon\Campaign\Contracts\CouponCampaignRepositoryContract;
use Plenty\Plugin\CachingRepository;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Templates\Twig;

class BadgeScript
{
    const CACHE_MINUTEN = 10;

    /**
     * Gutscheinkampagnen aus der Konfiguration lesen (nur lesend) und auf das kürzen, was die Seite braucht:
     * Code, Rabatt, Laufzeit, Artikel-IDs. Ob die Aktion gerade läuft, entscheidet das Skript im Browser
     * anhand von Start und Ende, damit auch zwischengespeicherte Seiten pünktlich umschalten.
     * Kampagnen ohne gemeinsamen Code (ein Code je Kunde) oder ohne Artikel werden übergangen.
     */
    private function gutscheine(string $ids, array &$diagnose): array
    {
        $liste = [];
        $zahlen = [];
        foreach (explode(',', $ids) as $teil) {
            $zahl = (int)trim($teil);
            if ($zahl > 0 && !in_array($zahl, $zahlen, true)) {
                $zahlen[] = $zahl;
            }
        }
        foreach ($zahlen as $id) {
            /** @var CachingRepository $cache */
            $cache = pluginApp(CachingRepository::class);
            $schluessel = 'OSVBadges_gutschein2_' . $id;
            $eintrag = $cache->get($schluessel);
            if (!is_array($eintrag)) {
                $eintrag = ['leer' => true];
                try {
                    /** @var AuthHelper $auth */
                    $auth = pluginApp(AuthHelper::class);
                    $k = $auth->processUnguarded(function () use ($id) {
                        /** @var CouponCampaignRepositoryContract $repo */
                        $repo = pluginApp(CouponCampaignRepositoryContract::class);
                        $kampagne = $repo->findById($id);
                        if (!$kampagne) {
                            return null;
                        }
                        // Codes und Bezüge sind Beziehungen, toArray() liefert sie nur, wenn sie geladen sind: einzeln abrufen
                        $daten = $kampagne->toArray();
                        try {
                            $codes = $kampagne->codes;
                            $daten['codes'] = is_object($codes) ? $codes->toArray() : (is_array($codes) ? $codes : []);
                        } catch (\Throwable $fehler) {
                            $daten['codesFehler'] = substr($fehler->getMessage(), 0, 120);
                        }
                        try {
                            $bezuege = $kampagne->references;
                            $daten['references'] = is_object($bezuege) ? $bezuege->toArray() : (is_array($bezuege) ? $bezuege : []);
                        } catch (\Throwable $fehler) {
                            $daten['referencesFehler'] = substr($fehler->getMessage(), 0, 120);
                        }
                        return $daten;
                    });
                    if (is_array($k)) {
                        $eintrag = $this->kampagne($k);
                    }
                } catch (\Throwable $fehler) {
                    $eintrag = ['leer' => true, 'fehler' => substr($fehler->getMessage(), 0, 200)];
                }
                $cache->put($schluessel, $eintrag, self::CACHE_MINUTEN);
            }
            if (empty($eintrag['leer'])) {
                $liste[] = $eintrag;
                $diagnose[] = ['id' => $id, 'ok' => true, 'von' => $eintrag['von'] ?? '', 'bis' => $eintrag['bis'] ?? ''];
            } else {
                $diagnose[] = ['id' => $id, 'ok' => false, 'grund' => $eintrag['grund'] ?? ($eintrag['fehler'] ?? 'nicht gefunden'), 'felder' => $eintrag['felder'] ?? ''];
            }
        }
        return $liste;
    }

    private function kampagne(array $k): array
    {
        $codes = [];
        foreach (($k['codes'] ?? []) as $c) {
            if (empty($c['disable']) && empty($c['consumed']) && !empty($c['code'])) {
                $codes[] = (string)$c['code'];
            }
        }
        $artikel = [];
        foreach (($k['references'] ?? []) as $r) {
            if (($r['referenceType'] ?? '') === 'item' && (int)($r['value'] ?? 0) > 0) {
                $artikel[] = (int)$r['value'];
            }
        }
        $start = strtotime((string)($k['startsAt'] ?? '')) ?: 0;
        $ende = strtotime((string)($k['endsAt'] ?? '')) ?: 0;
        // Plenty speichert das Ende als Tag (Backend zeigt nur das Datum): 00:00 heißt „bis einschließlich dieses Tags“
        if ($ende && date('H:i:s', $ende) === '00:00:00') {
            $ende += 86399;
        }
        $grund = ($k['concept'] ?? 'single_code') !== 'single_code' ? 'ein Code je Kunde' : (count($codes) !== 1 ? count($codes) . ' Codes' : (!count($artikel) ? 'keine Artikel' : (!$ende ? 'kein Ende' : '')));
        if ($grund !== '') {
            return ['leer' => true, 'grund' => $grund . ' (Codes ' . count($k['codes'] ?? []) . ', Bezüge ' . count($k['references'] ?? []) . ($k['codesFehler'] ?? '') . ($k['referencesFehler'] ?? '') . ', von ' . ($k['startsAt'] ?? '') . ', bis ' . ($k['endsAt'] ?? '') . ')', 'felder' => implode(',', array_keys($k))];
        }
        $wert = (float)($k['value'] ?? 0);
        $rabatt = '';
        if (($k['discountType'] ?? '') === 'percent' && $wert > 0) {
            $rabatt = str_replace('.', ',', rtrim(rtrim(number_format($wert, 2, '.', ''), '0'), '.')) . ' %';
        } elseif (($k['discountType'] ?? '') === 'fixed' && $wert > 0) {
            $rabatt = number_format($wert, 2, ',', '.') . ' €';
        } elseif (($k['discountType'] ?? '') === 'item') {
            // „Kaufe 4, zahle 3“: größere Zahl = Stück, kleinere = bezahlte Stück
            $a = (int)($k['itemDiscountToBuy'] ?? 0);
            $b = (int)($k['itemDiscountToPay'] ?? 0);
            if ($a > 0 && $b > 0 && $a !== $b) {
                $rabatt = max($a, $b) . ' für ' . min($a, $b);
            }
        }
        // Etikett-Text: aus der Beschreibung der Kampagne (je Gutschein eigener Text), sonst passend zur Rabattart
        $text = trim(strip_tags((string)($k['description'] ?? '')));
        if ($text !== '' && strpos($text, '{code}') === false) {
            $text .= ' mit Gutschein {code}';
        }
        if ($text === '') {
            $art = (string)($k['discountType'] ?? '');
            if ($art === 'shipping') {
                $text = 'Versandkostenfrei mit Gutschein {code}';
            } elseif ($rabatt !== '' && $art === 'item') {
                $text = '{rabatt} mit Gutschein {code}';
            } elseif ($rabatt !== '') {
                $text = '{rabatt} Rabatt mit Gutschein {code}';
            } else {
                $text = 'Mit Gutschein {code}';
            }
        }
        return [
            't' => substr($text, 0, 120),
            'c' => $codes[0],
            'r' => $rabatt,
            'art' => (string)($k['discountType'] ?? ''),
            's' => $start * 1000,
            'e' => $ende * 1000,
            'ende' => date('d.m.Y', $ende) . (date('H:i', $ende) !== '00:00' && date('H:i', $ende) !== '23:59' ? ', ' . date('H:i', $ende) . ' Uhr' : ''),
            'i' => array_values(array_unique($artikel)),
            'von' => (string)($k['startsAt'] ?? ''),
            'bis' => (string)($k['endsAt'] ?? ''),
        ];
    }

    const AKTIONEN = 3;

    /** Kalendertag aus dem Datumsfeld der Konfiguration als JJJJ-MM-TT (deutsche Zeit); leer = ohne Grenze */
    private function tag(string $wert): string
    {
        $wert = trim($wert);
        if ($wert === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert)) {
            return $wert;
        }
        // Kein new erlaubt (Plenty-Build): Zeitstempel über strtotime, Kalendertag in der Zeitzone des Systems
        $zeit = strtotime($wert);
        return $zeit ? date('Y-m-d', $zeit) : '';
    }

    /** Artikelnummer (oder Varianten-ID) in die Varianten-ID übersetzen, 10 Minuten zwischengespeichert */
    private function variante(string $eingabe, array &$diag): int
    {
        $eingabe = trim($eingabe);
        if ($eingabe === '') {
            return 0;
        }
        if (preg_match('/^\d+$/', $eingabe)) {
            return (int)$eingabe;
        }
        /** @var CachingRepository $cache */
        $cache = pluginApp(CachingRepository::class);
        $schluessel = 'OSVBadges_nr_' . md5($eingabe);
        $id = $cache->get($schluessel);
        if (is_numeric($id) && (int)$id > 0) {
            return (int)$id;
        }
        $id = 0;
        try {
            /** @var AuthHelper $auth */
            $auth = pluginApp(AuthHelper::class);
            $id = (int)$auth->processUnguarded(function () use ($eingabe) {
                /** @var VariationSearchRepositoryContract $repo */
                $repo = pluginApp(VariationSearchRepositoryContract::class);
                $repo->setFilters(['numberExact' => $eingabe]);
                $treffer = $repo->search();
                foreach ($treffer->getResult() as $v) {
                    $v = is_array($v) ? $v : (is_object($v) ? $v->toArray() : []);
                    if ((string)($v['number'] ?? '') === $eingabe) {
                        return (int)($v['id'] ?? 0);
                    }
                }
                return 0;
            });
        } catch (\Throwable $fehler) {
            $diag['nrFehler'] = substr($fehler->getMessage(), 0, 160);
        }
        if ($id > 0) {
            $cache->put($schluessel, $id, self::CACHE_MINUTEN);
        }
        return $id;
    }

    /**
     * Aktionen aus der Konfiguration: Kategorien, Zeitraum, Texte und die Daten der Variante (Bild, Name, Preis, Link, Liefertermin).
     * Ob eine Aktion gerade läuft und ob die Seite passt, entscheidet das Skript im Browser (Seitencache).
     */
    private function aktionen(ConfigRepository $config, array &$diagnose): array
    {
        $liste = [];
        for ($n = 1; $n <= self::AKTIONEN; $n++) {
            $p = 'OSVBadges.aktion' . $n . '.';
            if (!$this->an($config, 'aktion' . $n . '.aktiv', 'false')) {
                continue;
            }
            $diag = ['nr' => $n];
            $kategorien = [];
            foreach (explode(',', (string)$config->get($p . 'kategorien', '')) as $teil) {
                $zahl = (int)trim($teil);
                if ($zahl > 0) {
                    $kategorien[] = $zahl;
                }
            }
            $vid = $this->variante((string)$config->get($p . 'artikel', ''), $diag);
            $diag['vid'] = $vid;
            if (!$vid || !count($kategorien)) {
                $diag['grund'] = !$vid ? 'Artikel nicht gefunden' : 'keine Kategorie';
                $diagnose[] = $diag;
                continue;
            }
            /** @var CachingRepository $cache */
            $cache = pluginApp(CachingRepository::class);
            $schluessel = 'OSVBadges_aktion_' . $vid;
            $a = $cache->get($schluessel);
            if (!is_array($a)) {
                $a = [];
                try {
                    /** @var ItemSearchService $suche */
                    $suche = pluginApp(ItemSearchService::class);
                    $ergebnis = $suche->getResult(VariationList::getSearchFactory(['variationIds' => [$vid], 'itemsPerPage' => 1]));
                    $d = $ergebnis['documents'][0]['data'] ?? [];
                    if (count($d)) {
                        $bild = $d['images']['variation'][0] ?? ($d['images']['all'][0] ?? []);
                        $frei = (string)($d['variation']['releasedAt'] ?? '');
                        $a = [
                            'n' => (string)($d['texts']['name1'] ?? ''),
                            'b' => (string)($bild['url'] ?? ($bild['urlMiddle'] ?? '')),
                            'alt' => (string)($bild['alternate'] ?? ''),
                            'u' => '/' . ($d['texts']['urlPath'] ?? '') . '_' . ($d['item']['id'] ?? 0) . '_' . $vid . '/',
                            'p' => (string)($d['prices']['default']['price']['formatted'] ?? ''),
                            'd' => preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $frei, $m) ? $m[3] . '.' . $m[2] . '.' . $m[1] : '',
                        ];
                    }
                } catch (\Throwable $fehler) {
                    $diag['suchFehler'] = substr($fehler->getMessage(), 0, 160);
                }
                if (count($a)) {
                    $cache->put($schluessel, $a, self::CACHE_MINUTEN);
                }
            }
            if (!count($a)) {
                $diag['grund'] = 'keine Artikeldaten (Variante im Shop aktiv und sichtbar?)';
                $diagnose[] = $diag;
                continue;
            }
            $liste[] = $a + [
                'k' => $kategorien,
                's' => $this->tag((string)$config->get($p . 'von', '')),
                'e' => $this->tag((string)$config->get($p . 'bis', '')),
                'pos' => max(1, (int)$config->get($p . 'stelle', '1')),
                'ecke' => $this->feld($config, $p, 'ecke'),
                'marke' => $this->feld($config, $p, 'kennzeile'),
                'titel' => $this->feld($config, $p, 'titel'),
                'unter' => $this->feld($config, $p, 'unterzeile'),
                'satz' => $this->feld($config, $p, 'satz'),
                'liefer' => $this->feld($config, $p, 'lieferzeile'),
                'knopf' => $this->feld($config, $p, 'knopf') !== '' ? $this->feld($config, $p, 'knopf') : 'Zum Artikel',
                'bt' => $this->feld($config, $p, 'bandTitel'),
                'bz' => $this->feld($config, $p, 'bandZeile'),
            ];
            $diag['ok'] = true;
            $diagnose[] = $diag;
        }
        return $liste;
    }

    private function feld(ConfigRepository $config, string $p, string $feld): string
    {
        return trim((string)$config->get($p . $feld, ''));
    }

    private function an(ConfigRepository $config, string $schluessel, string $standard = 'true'): bool
    {
        $wert = $config->get('OSVBadges.' . $schluessel, $standard);
        return !($wert === 'false' || $wert === false || $wert === '0');
    }

    public function call(Twig $twig, ConfigRepository $config): string
    {
        $sparen = $this->an($config, 'sparenAktiv');
        $kachel = $this->an($config, 'kachelAktiv');
        $liefer = $this->an($config, 'lieferAktiv');
        $filter = $this->an($config, 'filterAktiv');
        $diagnose = [];
        $gutscheine = $this->gutscheine((string)$config->get('OSVBadges.gutscheinIds', ''), $diagnose);
        $aktionDiagnose = [];
        $aktionen = $this->aktionen($config, $aktionDiagnose);
        if (!$sparen && !$kachel && !$liefer && !$filter && !count($gutscheine) && !count($aktionen)) {
            return '';
        }
        $grenze = (float)str_replace(',', '.', (string)$config->get('OSVBadges.sparenGrenze', '100'));
        if ($grenze <= 0) {
            $grenze = 100;
        }
        $textProzent = trim((string)$config->get('OSVBadges.sparenTextProzent', ''));
        $textEuro = trim((string)$config->get('OSVBadges.sparenTextEuro', ''));
        $textVor = trim((string)$config->get('OSVBadges.lieferTextVor', ''));
        $zeileGutschein = trim((string)$config->get('OSVBadges.gutscheinZeile', ''));
        return $twig->render('OSVBadges::Containers.KachelAnzeige', [
            'kachel' => $kachel,
            'aktionen' => count($aktionen) > 0,
            'filter' => $filter,
            'einstellungJson' => json_encode([
                'sparen' => $sparen,
                'liefer' => $liefer,
                'filter' => $filter,
                'zum' => $kachel && $this->an($config, 'zumAktiv'),
                'textZum' => trim((string)$config->get('OSVBadges.zumText', '')) !== '' ? trim((string)$config->get('OSVBadges.zumText', '')) : 'Zum Artikel',
                'filterSichtbar' => max(1, (int)$config->get('OSVBadges.filterSichtbar', '6')),
                'filterSucheAb' => max(1, (int)$config->get('OSVBadges.filterSucheAb', '8')),
                'grenze' => $grenze,
                'textProzent' => $textProzent !== '' ? $textProzent : 'Sie sparen {prozent}',
                'textEuro' => $textEuro !== '' ? $textEuro : 'Sie sparen {euro}',
                'textVor' => $textVor !== '' ? $textVor : 'Lieferbar ab {datum}',
                'gutscheine' => $gutscheine,
                'gutscheinDiagnose' => $diagnose,
                'aktionen' => $aktionen,
                'aktionDiagnose' => $aktionDiagnose,
                'zeileGutschein' => $zeileGutschein !== '' ? $zeileGutschein : 'Gilt bis {ende}, nicht mit anderen Gutscheinen kombinierbar.',
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);
    }
}
