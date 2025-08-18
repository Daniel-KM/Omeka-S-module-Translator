<?php declare(strict_types=1);

namespace Translate\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Translate\Api\Representation\TranslateRepresentation;

class Translating extends AbstractHelper
{
    /**
     * Get the translate representation.
     */
    public function __invoke($idOrString, ?string $langSource = null, ?string $langTarget = null): ?TranslateRepresentation
    {
        $view = $this->getView();

        if (is_numeric($idOrString)) {
            try {
                return $view->api()->read('translates', ['id' => $idOrString])->getContent();
            } catch (\Exception $e) {
                return null;
            }
        }

        if (!$langTarget) {
            return null;
        }

        if (!$langSource) {
            $langSource = $view->setting('translate_lang_source_default');
            if (!$langSource) {
                return null;
            }
        }

        try {
            return $this->getView()->api()
                ->read('translates', [
                    'lang_source' => $langSource,
                    'lang_target' => $langTarget,
                    'string' => $idOrString,
                ])
                ->getContent();
        } catch (\Exception $e) {
            return null;
        }
    }
}
