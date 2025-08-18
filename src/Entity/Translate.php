<?php declare(strict_types=1);

namespace Translator\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;

/**
 * For index:
 * Most of the times, the process reads a string in a specific language to
 * translate it in another language, so a main index is used for that.
 * The index "text/lang source/lang target" is generally unique, but there may
 * be a duplicate issue on update (mysql is not sql), so a simple index is
 * created.
 * Furthermore, it allows to manage the case where there are multiple
 * translations for one string (The Queen => Die Königin, Ihre Majestät).
 *
 * Translates have no owner or linked data, like resource values. They should be
 * simple and fast, but not repetitive.
 *
 * @todo Replace string by value id? The value id are not stable in omeka. The storage of the string avoids some duplication too (but some main fields like title and description are generally unique).
 *
 * @todo Add a context (laminas text domain)?
 *
 * @todo Separate the language and the localisation in two fields? For now, follow the omeka way and deepl way.
 * @todo Length of the lang source should be shorter (3 characters, up to 8?), but it follows length used for Value. DeepL supports only 2 for source and some localization for target.
 * @todo Replace "automatic" or add a type or a name? (owner id, visitor id, "manual", "deepl", etc.)?
 * @todo Store a job id? No.
 *
 * @todo Check if the indices and the aggregate index are the best ones (see Text too).
 *
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(
 *             name="idx_translate_lang",
 *             columns={
 *                 "lang"
 *             }
 *         )
 *     }
 * )
 */
class Translate extends AbstractEntity
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
     * @ManyToOne(
     *     targetEntity="Translator\Entity\Text",
     *     inversedBy="translates",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $text;

    /**
     * Unlike source, DeepL already supports some localization codes for target.
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
     * @Column(
     *     type="boolean",
     *     nullable=false,
     *     options={
     *         "default": false
     *     }
     * )
     */
    protected $automatic = false;

    /**
     * @var bool
     *
     * @Column(
     *     type="boolean",
     *     nullable=false,
     *     options={
     *         "default": false
     *     }
     * )
     */
    protected $reviewed = false;

    /**
     * @var \DateTime
     *
     * @Column(
     *     type="datetime"
     * )
     */
    protected $created;

    /**
     * @var \DateTime|null
     *
     * @Column(
     *      type="datetime",
     *      nullable=true
     * )
     */
    protected $modified;

    /**
     * @var string
     *
     * @Column(
     *     type="text",
     *     nullable=false
     * )
     */
    protected $translation;

    public function getId()
    {
        return $this->id;
    }

    public function setText(Text $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getText(): Text
    {
        return $this->text;
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

    public function setAutomatic(bool $automatic): self
    {
        $this->automatic = $automatic;
        return $this;
    }

    public function getAutomatic(): bool
    {
        return $this->automatic;
    }

    public function setReviewed(bool $reviewed): self
    {
        $this->reviewed = $reviewed;
        return $this;
    }

    public function getReviewed(): bool
    {
        return $this->reviewed;
    }

    public function setTranslation(string $translation): self
    {
        $this->translation = $translation;
        return $this;
    }

    public function getTranslation(): string
    {
        return $this->translation;
    }

    public function setCreated(DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function setModified(?DateTime $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): ?DateTime
    {
        return $this->modified;
    }
}
