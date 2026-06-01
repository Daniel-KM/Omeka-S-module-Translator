<?php declare(strict_types=1);

namespace Translator\Job;

use Omeka\Job\AbstractJob;
use Translator\Module;

/**
 * Translate a set of resources saved during a single request.
 *
 * Receives a flat list of "resourceName:id" tokens via the "refs" argument.
 * Calls the module translation logic per resource, then batch-creates the
 * translations at the end.
 */
class TranslateResources extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $settings = $services->get('Omeka\Settings');

        $deeplApiKey = $settings->get('translator_deepl_api_key');
        if (!$deeplApiKey) {
            return;
        }
        $propertiesToInclude = $settings->get('translator_properties_include', []);
        if (!$propertiesToInclude) {
            return;
        }

        $refs = $this->getArg('refs') ?: '';
        $refs = array_filter(array_unique(preg_split('/\s+/', (string) $refs)));
        if (!$refs) {
            return;
        }

        /** @var Module $module Laminas ModuleManager returns the real
         * module instance, unlike Omeka\ModuleManager which returns a metadata
         * wrapper. */
        $module = $services
            ->get('ModuleManager')
            ->getModule('Translator');
        if (!$module instanceof Module) {
            return;
        }

        $results = [];
        foreach ($refs as $ref) {
            if ($this->shouldStop()) {
                break;
            }
            [$resourceName, $id] = array_pad(explode(':', $ref, 2), 2, null);
            if (!$resourceName || !$id) {
                continue;
            }
            try {
                $resource = $api->read($resourceName, (int) $id)->getContent();
            } catch (\Throwable $e) {
                continue;
            }

            $texts = $module->filterValuesToTranslate($resource);
            if (!$texts) {
                continue;
            }

            foreach ($texts as $langAndTexts) {
                $chunk = $langAndTexts['texts'];
                $langSource = $langAndTexts['source'];
                $langTarget = $langAndTexts['target'];
                $chunk = $module->filterExistingTranslations($chunk, $langSource, $langTarget);
                if (!$chunk) {
                    continue;
                }
                $chunk = array_values($chunk);
                $translations = $module->translateDeepL($chunk, $langSource, $langTarget);
                foreach ($translations as $key => $translation) {
                    $results[] = [
                        'o:string' => $chunk[$key],
                        'o:lang_source' => $langSource,
                        'o:lang_target' => $langTarget,
                        'o:translation' => $translation->text,
                        'o:automatic' => true,
                    ];
                }
            }
        }

        if (!$results) {
            return;
        }

        $api->batchCreate('translations', $results, [], ['continueOnError' => true]);
        $logger->info(
            'Translator: stored {count} translations for {resources} resources.', // @translate
            ['count' => count($results), 'resources' => count($refs)]
        );
    }
}
