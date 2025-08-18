<?php declare(strict_types=1);

namespace Translator\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class TranslationRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \Translator\Entity\Translation
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
     * Here, this is a single representation of the translate itself.
     * This is not a value that is translated in multiple languages.
     * But in resource representation, they can be included as indicated, by language.
     * @see https://www.w3.org/TR/json-ld/#language-indexing
     *
     * @todo Another way to represent the value (check if flatifly is allowed in such a case in json-ld).
     * But in that case, it will be a TextRepresentation, but the aim is only to
     * get translation for now. The separation in two tables is more internal
     * and technical and is not useful in userland for now.
     *
     * @todo Make similar the Translation of Internationalisation and Translator.
     * @see \Internationalisation\Api\Representation\TranslatingRepresentation
     * @see \Translator\Api\Representation\TranslationRepresentation
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLd()
     */
    public function getJsonLd()
    {
        $modified = $this->modified();
        return [
            'o:id' => $this->id(),
            // TODO Set an array with the iso standard as key? Probably not.
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
            // TODO Another way to represent (and in case of multiple modes of language, add it in key). See above: TextRepresentation.
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
            // TODO Another way to represent Resource representation. And in case of multiple modes of language, add it in key.
            'label' => [
                'en' => $this->string()
                'de' => $this->translation(),
                'fr' => $this->translation(),
                '@none' => $this->string(),
            ],
            */
        ];
    }

    /**
     * The lang source may be null when there is no default language.
     * It is stored as an empty string, but the representation uses null.
     */
    public function langSource(): ?string
    {
        return $this->resource->getText()->getLang() ?: null;
    }

    public function langTarget(): string
    {
        return $this->resource->getLang();
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
        return $this->resource->getText()->getString();
    }

    public function translation(): string
    {
        return $this->resource->getTranslation();
    }

    public function created(): DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?DateTime
    {
        return $this->resource->getModified();
    }

    /**
     * For simplicity with generic code.
     */
    public function displayTitle(): string
    {
        return $this->string();
    }
}
