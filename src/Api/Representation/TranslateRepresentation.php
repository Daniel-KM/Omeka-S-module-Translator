<?php declare(strict_types=1);

namespace Translate\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class TranslateRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \Translate\Entity\Translate
     */
    protected $resource;

    public function getControllerName()
    {
        return 'translate';
    }

    public function getJsonLdType()
    {
        return 'o:Translate';
    }

    /**
     * Here, this is a single representation of the translate itself.
     * This is not a value that is translated in multiple languages.
     * But in resource representation, they can be included as indicated, by language.
     * @see https://www.w3.org/TR/json-ld/#language-indexing
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLd()
     */
    public function getJsonLd()
    {
        $modified = $this->modified();
        return [
            'o:id' => $this->id(),
            // TODO Set an array with the standard as key? Probably not.
            'o:lang_source' => $this->langSource(),
            'o:lang_target' => $this->langTarget(),
            'o:automatic' => $this->automatic(),
            'o:reviewed' => $this->reviewed(),
            'o:string' => $this->string(),
            'o:translation' => $this->translation(),
            'o:created' => [
                '@value' => $this->getDateTime($this->created())->jsonSerialize(),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ],
            'o:modified' => $modified
                ? [
                    '@value' => $this->getDateTime($modified)->jsonSerialize(),
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                ] : null,
            /*
            // TODO Another way to represent the value (check if flatifly is allowed in such a case in json-ld).
            'o:string' => [
                '@value' => $this->string(),
                'o:lang' => $this->langSource(),
            ],
            'o:translation' => [
                '@value' => $this->translation(),
                'o:lang' => $this->langTarget(),
                'o:automatic' => $this->automatic(),
                'o:reviewed' => $this->reviewed(),
            ],
            */
            /*
            // TODO Another way to represent (and in case of multiple level of language, add it in key).
            'label' => [
                'en' => $this->string()
                'de' => $this->translation(),
                'fr' => $this->translation(),
                '@none' => $this->string(),
            ],
            */
        ];
    }

    public function langSource(): string
    {
        return $this->resource->getLangSource();
    }

    public function langTarget(): string
    {
        return $this->resource->getLangTarget();
    }

    public function automatic(): bool
    {
        return $this->resource->getAutomatic();
    }

    public function reviewed(): bool
    {
        return $this->resource->getReviewed();
    }

    public function string(): string
    {
        return $this->resource->getString();
    }

    public function translation(): string
    {
        return $this->resource->getTranslation();
    }

    /**
     * For simplicity with generic code.
     */
    public function displayTitle(): string
    {
        return $this->string();
    }
}
