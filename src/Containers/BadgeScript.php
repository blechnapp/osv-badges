<?php

namespace OSVBadges\Containers;

use Plenty\Modules\Authorization\Services\AuthHelper;
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
        }
        return [
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

    private function an(ConfigRepository $config, string $schluessel): bool
    {
        $wert = $config->get('OSVBadges.' . $schluessel, 'true');
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
        if (!$sparen && !$kachel && !$liefer && !$filter && !count($gutscheine)) {
            return '';
        }
        $grenze = (float)str_replace(',', '.', (string)$config->get('OSVBadges.sparenGrenze', '100'));
        if ($grenze <= 0) {
            $grenze = 100;
        }
        $textProzent = trim((string)$config->get('OSVBadges.sparenTextProzent', ''));
        $textEuro = trim((string)$config->get('OSVBadges.sparenTextEuro', ''));
        $textVor = trim((string)$config->get('OSVBadges.lieferTextVor', ''));
        $textGutschein = trim((string)$config->get('OSVBadges.gutscheinText', ''));
        $textVersand = trim((string)$config->get('OSVBadges.gutscheinTextVersand', ''));
        $zeileGutschein = trim((string)$config->get('OSVBadges.gutscheinZeile', ''));
        return $twig->render('OSVBadges::Containers.BadgeScript', [
            'kachel' => $kachel,
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
                'textGutschein' => $textGutschein !== '' ? $textGutschein : 'Mit Code {code} {rabatt} sparen',
                'textVersand' => $textVersand !== '' ? $textVersand : 'Mit Code {code} versandkostenfrei',
                'zeileGutschein' => $zeileGutschein !== '' ? $zeileGutschein : 'Gutschein {code} gilt bis {ende}. Nicht mit anderen Gutscheinen kombinierbar.',
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);
    }
}
