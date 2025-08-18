<?php declare(strict_types=1);

namespace Translate\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;

/**
 * For index:
 * Most of the times, the process reads a string in a specific language to
 * translate it in another language, so a main index is used for that.
 * The index "string/lang_source/lang_target" is unique, but there may be a
 * duplicate issue on update (mysql is not sql), so a simple index is created
 * for now.
 *
 * Translates have no owner or linked data, like resource values. They should be simple and fast.
 *
 * For next version:
 * @todo Create two tables and a view to avoid to duplicate source string? Check size first then speed and simplicity. Will be required for big bases with multiple langs. Or a simple view to value id or site page block id ? or two views? Or limit to resources? And it is not recommended to translate long fields (ocr).
 * @todo Replace string by value id? The value id are not stable in omeka. The storage of the string avoids some duplication too (but some main fields like title and description are always unique).
 *
 * @todo Add a context (laminas text domain)?
 *
 * @todo Separate the language and the localisation in two fields? For now, follow the omeka way and deepl way.
 * @todo Length of the lang source should be shorter (3 characters, up to 8?), but it follows length used for Value. DeepL supports only 2 for source and some localization for target.
 * @todo Replace "automatic" or add a type or a name? (owner id, visitor id, "manual", "deepl", etc.)?
 * @todo Store a job id? No.
 *
 * @todo Manage the case where there are multiple translation for one string (The Queen => Die Königin, Ihre Majestät).
 * @todo The json-ld allows to store a canonical name with lang "@none", that may be stored as it or as null? Probably none.
 * @see https://www.w3.org/TR/json-ld/#language-indexing
 *
 * @todo Check if the indices and the aggregate index are the best ones.
 * The value of lang_source is almost always the same for common databases
 * and is already indexed in field lang of value.
 *
 * @Entity
 * @Table(
 *     indexes={
 *         @Index(
 *             name="idx_translate_langtarget_langsource",
 *             columns={
 *                 "lang_target",
 *                 "lang_source"
 *             }
 *         ),
 *         @Index(
 *             name="idx_translate_string_langtarget_langsource",
 *             columns={
 *                 "string",
 *                 "lang_target",
 *                 "lang_source"
 *             },
 *             options={
 *                 "lengths": {190}
 *             }
 *          )
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
     * While omeka value support 190 characters, only 8 is needed here, because
     * DeepL supports only very common languages for now. Localized code are not
     * supported too for now.
     * @see https://developers.deepl.com/docs/getting-started/supported-languages
     *
     * @var string
     *
     * @Column(
     *     length=8,
     *     nullable=false
     * )
     */
    protected $langSource;

    /**
     * Unlike source, DeepL already supports some localization codes.
     *
     * @var string
     *
     * @Column(
     *     length=8,
     *     nullable=false
     * )
     */
    protected $langTarget;

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
     *     name="`string`",
     *     type="text",
     *     nullable=false
     * )
     */
    protected $string;

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

    public function setLangSource(string $langSource): self
    {
        $this->langSource = $langSource;
        return $this;
    }

    public function getLangSource(): ?string
    {
        return $this->langSource;
    }

    public function setLangTarget(string $langTarget): self
    {
        $this->langTarget = $langTarget;
        return $this;
    }

    public function getLangTarget(): string
    {
        return $this->langTarget;
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

    public function setString(string $string): self
    {
        $this->string = $string;
        return $this;
    }

    public function getString(): string
    {
        return $this->string;
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
