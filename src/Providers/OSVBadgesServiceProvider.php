<?php

namespace OSVBadges\Providers;

use Plenty\Modules\Webshop\ItemSearch\Helpers\ResultFieldTemplate;
use Plenty\Plugin\ServiceProvider;

class OSVBadgesServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    /**
     * Bewertungen auf den Kacheln: Ceres verlangt item.feedbackDecimal und item.feedbackCount seit 5.0.62,
     * im Shop überdeckt aber eine ältere Feldliste (vermutlich ConversionCheckout) die von Ceres. requireFields
     * hängt die zwei Felder an die Kachel-Suche an, ohne die Feldliste selbst zu ersetzen (gleiche Klasse wie in Ceres).
     */
    public function boot()
    {
        /** @var ResultFieldTemplate $vorlagen */
        $vorlagen = pluginApp(ResultFieldTemplate::class);
        $vorlagen->requireFields(ResultFieldTemplate::TEMPLATE_LIST_ITEM, ['item.feedbackDecimal', 'item.feedbackCount']);
    }
}
