<?php declare(strict_types=1);

namespace Translator\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
// use Translator\Api\Representation\TranslationRepresentation;

class Translation extends AbstractHelper
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
     * Get the translation representation.
     *
     * @param array $options
     * - as_representation (bool): false (default)
     *
     * @return \Translator\Api\Representation\TranslationRepresentation|string|null
     */
    public function __invoke(
        $idOrString,
        ?string $langSource = null,
        ?string $langTarget = null,
        array $options = []
    ) {
        $asRepresentation = !empty($options['as_representation']);

        if (is_numeric($idOrString)) {
            try {
                return $asRepresentation
                    ? $this->api->read('translations', ['id' => $idOrString])->getContent()
                    : $this->api->read('translations', ['id' => $idOrString], ['returnScalar' => 'translation'])->getContent();
            } catch (\Exception $e) {
                return null;
            }
        }

        if (!$langTarget) {
            return null;
        }

        // "read" cannot be used with a sub-entity in doctrine, or get it first.
        // For lang source, "read" uses empty string, but "search" requires it
        // to be wrapped with single quotes.
        $data = [
            'string' => $idOrString,
            'lang_source' => $langSource ?: "''",
            'lang_target' => $langTarget,
            'limit' => 1,
        ];

        try {
            $result = $asRepresentation
                ? $this->api->search('translations', $data)->getContent()
                : $this->api->search('translations', $data, ['returnScalar' => 'translation'])->getContent();
        } catch (\Exception $e) {
            return null;
        }

        return reset($result);
    }
}
