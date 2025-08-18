<?php declare(strict_types=1);

namespace Translate\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class TranslationRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \Translate\Entity\Translation
     */
    protected $resource;

    public function getControllerName()
    {
        return 'translation';
    }

    public function getJsonLdType()
    {
        return 'o:Translation';
    }

    /**
     * Here, this is a single representation of the translation.
     * This is not a value that is translated in multiple languages.
     * But in resource representation, they can be included as indicated, by language.
     * @see https://www.w3.org/2018/jsonld-cg-reports/json-ld/#language-indexing
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLd()
     */
    public function getJsonLd()
    {
        return [
            'o:id' => $this->id(),
            'o:string' => $this->string(),
            'o:lang' => $this->lang(),
            'o:translation' => $this->translation(),
            'o:locale' => $this->locale(),
            'o:automatic' => $this->automatic(),
            'o:reviewed' => $this->reviewed(),
            /*
            'o:string' => [
                '@value' => $this->string(),
                'o:lang' => $this->lang(),
            ],
            'o:translation' => [
                '@value' => $this->translation(),
                'o:lang' => $this->locale(),
                'o:automatic' => $this->automatic(),
                'o:reviewed' => $this->reviewed(),
            ],
            */
        ];
    }

    public function string(): string
    {
        return $this->resource->getString();
    }

    /**
     * For simplicity with generic code.
     */
    public function displayTitle(): string
    {
        return $this->string();
    }

    public function lang(): string
    {
        return $this->resource->getLang();
    }

    public function translation(): string
    {
        return $this->resource->getTranslation();
    }

    public function locale(): string
    {
        return $this->resource->getLocale();
    }

    public function automatic(): bool
    {
        return $this->resource->getAutomatic();
    }

    public function reviewed(): bool
    {
        return $this->resource->getReviewed();
    }
}
