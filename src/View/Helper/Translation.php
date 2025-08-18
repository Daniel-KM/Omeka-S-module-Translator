<?php declare(strict_types=1);

namespace Translate\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
use Translate\Api\Representation\TranslationRepresentation;

class Translation extends AbstractHelper
{
    /**
     * Get the table representation.
     */
    public function __invoke($idOrSlug): ?TranslationRepresentation
    {
        if (!$idOrSlug) {
            return null;
        }

        try {
            return $this->getView()->api()
                ->read('tables', is_numeric($idOrSlug) ? ['id' => $idOrSlug] : ['slug' => $idOrSlug])
                ->getContent();
        } catch (\Exception $e) {
            return null;
        }
    }
}
