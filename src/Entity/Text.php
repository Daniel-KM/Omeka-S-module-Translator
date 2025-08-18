<?php declare(strict_types=1);

namespace Translate\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Omeka\Entity\AbstractEntity;

/**
 * @todo The json-ld allows to store a canonical name with lang "@none", that may be stored as it or as null? Probably none.
 * @see https://www.w3.org/TR/json-ld/#language-indexing
 *
 * The value of lang is almost always the same for common databases and is
 * already indexed in field lang of value.
 *
 * @Entity
 * @Table(
 *     uniqueConstraints={
 *         @UniqueConstraint(
 *             name="uniq_text_string_lang",
 *             columns={
 *                 "string",
 *                 "lang"
 *            }
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
     * An empty string means undetermined and implies an automatic detection.
     *
     * @var string
     *
     * @Column(
     *     length=8,
     *     nullable=false
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
     *     targetEntity="Translate\Entity\Translate",
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
    protected $translates;

    public function __construct()
    {
        $this->translates = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setLang(string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    public function getLang(): string
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
    public function getTranslates()
    {
        return $this->translates;
    }
}
