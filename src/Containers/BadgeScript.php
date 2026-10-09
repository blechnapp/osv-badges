<?php

namespace OSVBadges\Containers;

use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Templates\Twig;

class BadgeScript
{
    public function call(Twig $twig, ConfigRepository $config): string
    {
        $aktiv = $config->get('OSVBadges.sparenAktiv', 'true');
        if ($aktiv === 'false' || $aktiv === false || $aktiv === '0') {
            return '';
        }
        $grenze = (float)str_replace(',', '.', (string)$config->get('OSVBadges.sparenGrenze', '100'));
        if ($grenze <= 0) {
            $grenze = 100;
        }
        return $twig->render('OSVBadges::Containers.BadgeScript', [
            'einstellungJson' => json_encode(['grenze' => $grenze], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);
    }
}
