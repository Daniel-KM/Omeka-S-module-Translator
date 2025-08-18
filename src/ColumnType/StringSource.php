<?php declare(strict_types=1);

namespace Translator\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

/**
 * The name is StringSource because "String" is a reserved word in PHP.
 */
class StringSource implements ColumnTypeInterface
{
    public function getLabel() : string
    {
        return 'String'; // @translate
    }

    public function getResourceTypes() : array
    {
        return ['translates'];
    }

    public function getMaxColumns() : ?int
    {
        return 1;
    }

    public function renderDataForm(PhpRenderer $view, array $data) : string
    {
        return '';
    }

    public function getSortBy(array $data) : ?string
    {
        return 'string';
    }

    public function renderHeader(PhpRenderer $view, array $data) : string
    {
        return $this->getLabel();
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data) : ?string
    {
        return $resource->string();
    }
}
