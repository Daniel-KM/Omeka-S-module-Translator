<?php declare(strict_types=1);

namespace Translate\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * For index: most of the time, the request uses string, lang and locale.
 * The index "string/lang/locale" is unique, but there may be a duplicate issue
 * on update (mysql is not sql), so a simple index is created.
 *
 * Translations have no owner, like resource values. They should be simple and fast.
 *
 * @todo Separate the language and the localisation in two fields? For now, follow the omeka way.
 * @todo Length of the locale should be shorter (3 characters, up to 8?), but it follows length used for Value.
 * @todo Replace isAuto by a type or a name? (owner id, visitor id, "manual", "deepl", etc.)?
 * @todo Store a job id?
 * @todo Store a modification date?
 * @todo The json-ld allows to store a canonical name with lang "@none", that may be stored as it or as null?
 *
 * @Entity
 * @Table(
 *      indexes={
 *         @Index(
 *             columns={
 *                 "string",
 *                 "lang",
 *                 "locale"
 *             },
 *             options={
 *                 "lengths": {190}
 *             }
*          ),
 *         @Index(
 *             columns={
 *                 "lang"
 *             }
 *         ),
 *         @Index(
 *             columns={
 *                 "locale"
 *             }
 *         )
 *     }
 * )
 */
class Translation extends AbstractEntity
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
     * @var string
     *
     * @Column(
     *     length=190,
     *     nullable=false
     * )
     */
    protected $lang;

    /**
     * @var string
     *
     * @Column(
     *     type="text",
     *     nullable=false
     * )
     */
    protected $string;

    /**
     * @var string
     *
     * @Column(
     *     length=8,
     *     nullable=false
     * )
     */
    protected $locale;

    /**
     * @var string
     *
     * @Column(
     *     type="text",
     *     nullable=false
     * )
     */
    protected $translation;

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

    public function getId()
    {
        return $this->id;
    }

    public function setLang(string $lang): self
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

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
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
}
