<?php declare(strict_types=1);

namespace Translator\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Translator\View\Helper\Translation;

/**
 * Service factory for the Translation view helper.
 */
class TranslationFactory implements FactoryInterface
{
    /**
     * Create and return the Translation view helper.
     *
     * @return Translation
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Translation(
            $services->get('Omeka\ApiManager')
        );
    }
}
