<?php declare(strict_types=1);

namespace Translator\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
// use Translator\Api\Representation\TranslateRepresentation;

class Translating extends AbstractHelper
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    /**
     * Get the translate representation.
     *
     * @param array $options
     * - as_representation (bool): false (default)
     *
     * @return \Translator\Api\Representation\TranslateRepresentation|string|null
     */
    public function __invoke(
        $idOrString,
        ?string $langSource = null,
        ?string $langTarget = null,
        array $options = []
    ) {
        $view = $this->getView();

        $asRepresentation = !empty($options['as_representation']);

        if (is_numeric($idOrString)) {
            try {
                return $asRepresentation
                    ? $this->api->read('translates', ['id' => $idOrString])->getContent()
                    : $this->api->read('translates', ['id' => $idOrString], ['returnScalar' => 'translation'])->getContent();
            } catch (\Exception $e) {
                return null;
            }
        }

        if (!$langTarget) {
            return null;
        }

        if (!$langSource) {
            $langSource = $view->setting('translator_lang_source_default');
            if (!$langSource) {
                return null;
            }
        }

        $data = [
            'langSource' => $langSource,
            'langTarget' => $langTarget,
            'string' => $idOrString,
        ];

        try {
            return $asRepresentation
                ? $this->api->read('translates', $data)->getContent()
                : $this->api->read('translates', $data, ['returnScalar' => 'translation'])->getContent();
        } catch (\Exception $e) {
            return null;
        }
    }
}
