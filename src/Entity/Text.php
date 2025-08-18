<?php declare(strict_types=1);

namespace Translator\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Omeka\Entity\AbstractEntity;

/**
 * @todo The json-ld allows to store a canonical name with lang "@none", that may be stored as it or as null? Probably none.
 * @see https://www.w3.org/TR/json-ld/#language-indexing
 *
 * The value of lang is almost always the same for common databases and is
 * already indexed in field lang of value.
 *
 * The index "text/lang source" must be unique, but there may be a duplicate
 * issue on update (mysql is not sql), so a simple index is created.
 *
 * @Entity
 * @Table(
 *     name="translate_text",
 *     indexes={
 *         @Index(
 *             name="idx_text_string",
 *             columns={
 *                 "string",
 *                 "lang"
 *             },
 *             options={
 *                 "lengths": {190}
 *             }
 *         ),
 *         @Index(
 *             name="idx_text_lang",
 *             columns={
 *                 "lang"
 *             }
 *         )
 *     }
 * )
 */
class Text extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(
     *     type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * While omeka value support 190 characters, only 8 is needed here, because
     * DeepL supports only very common languages for now. Localized code are not
     * supported too for now.
     * @see https://developers.deepl.com/docs/getting-started/supported-languages
     *
     * A null means a text without language and implies an automatic detection.
     *
     * @var string
     *
     * @Column(
     *     length=8,
     *     nullable=true
     * )
     */
    protected $lang;

    /**
     * @var string
     *
     * @Column(
     *     name="`string`",
     *     type="text",
     *     nullable=false
     * )
     */
    protected $string;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     *
     * @OneToMany(
     *     targetEntity="Translator\Entity\Translation",
     *     mappedBy="text",
     *     orphanRemoval=true,
     *     cascade={"persist", "remove", "detach"}
     * )
     * @OrderBy(
     *     {
     *         "lang"="ASC"
     *     }
     * )
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setString(string $string): self
    {
        $this->string = $string;
        return $this;
    }

    public function getString(): string
    {
        return $this->string;
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection|\Doctrine\ORM\PersistentCollection
     */
    public function getTranslations()
    {
        return $this->translations;
    }
}
