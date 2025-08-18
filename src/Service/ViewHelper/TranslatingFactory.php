<?php declare(strict_types=1);

namespace Translate\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Translate\View\Helper\Translating;

/**
 * Service factory for the Translating view helper.
 */
class TranslatingFactory implements FactoryInterface
{
    /**
     * Create and return the Translation view helper.
     *
     * @return Translating
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Translating(
            $services->get('Omeka\ApiManager')
        );
    }
}
