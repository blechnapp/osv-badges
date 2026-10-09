<?php

namespace OSVBadges\Containers;

use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Templates\Twig;

class BadgeScript
{
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
        if (!$sparen && !$kachel && !$liefer && !$filter) {
            return '';
        }
        $grenze = (float)str_replace(',', '.', (string)$config->get('OSVBadges.sparenGrenze', '100'));
        if ($grenze <= 0) {
            $grenze = 100;
        }
        $textProzent = trim((string)$config->get('OSVBadges.sparenTextProzent', ''));
        $textEuro = trim((string)$config->get('OSVBadges.sparenTextEuro', ''));
        $textVor = trim((string)$config->get('OSVBadges.lieferTextVor', ''));
        return $twig->render('OSVBadges::Containers.BadgeScript', [
            'kachel' => $kachel,
            'filter' => $filter,
            'einstellungJson' => json_encode([
                'sparen' => $sparen,
                'liefer' => $liefer,
                'filter' => $filter,
                'filterSichtbar' => max(1, (int)$config->get('OSVBadges.filterSichtbar', '6')),
                'filterSucheAb' => max(1, (int)$config->get('OSVBadges.filterSucheAb', '8')),
                'grenze' => $grenze,
                'textProzent' => $textProzent !== '' ? $textProzent : 'Sie sparen {prozent}',
                'textEuro' => $textEuro !== '' ? $textEuro : 'Sie sparen {euro}',
                'textVor' => $textVor !== '' ? $textVor : 'Lieferbar ab {datum}',
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);
    }
}
