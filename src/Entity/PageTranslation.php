<?php declare(strict_types=1);

namespace Translator\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\SitePage;

/**
 * State of the translation of a copied site page.
 *
 * A multilingual site group is built by copying the pages of a site into the
 * other sites of the group, so a copied page has the same blocks as the
 * original one. The blocks of the copies are translated in place, so the module
 * needs to know what was already done: a copied block that was translated does
 * not contain the original text anymore, so it cannot be compared to it.
 *
 * So a hash of the translatable content of the original page is stored here for
 * each copy, by title and by block position. A block is translated again only
 * when its hash changed, that is when the original text was updated. It avoids
 * to overwrite the corrections made by a user in the copy too.
 *
 * @Entity
 * @Table(
 *     name="translate_page",
 *     uniqueConstraints={
 *         @UniqueConstraint(
 *             name="idx_translate_page",
 *             columns={
 *                 "page_id"
 *             }
 *         )
 *     },
 *     indexes={
 *         @Index(
 *             name="idx_translate_page_source",
 *             columns={
 *                 "source_page_id"
 *             }
 *         )
 *     }
 * )
 */
class PageTranslation extends AbstractEntity
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
     * The copied page, that contains the translations.
     *
     * @var \Omeka\Entity\SitePage
     *
     * @OneToOne(
     *     targetEntity="\Omeka\Entity\SitePage"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $page;

    /**
     * The original page, kept to follow the origin of the translations.
     *
     * @var \Omeka\Entity\SitePage|null
     *
     * @ManyToOne(
     *     targetEntity="\Omeka\Entity\SitePage"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $sourcePage;

    /**
     * @var string
     *
     * @Column(
     *     length=8,
     *     nullable=false
     * )
     */
    protected $lang;

    /**
     * Hashes of the translated parts of the original page, by key: "title" for
     * the title of the page and "block:{position}" for each block.
     *
     * @var array
     *
     * @Column(
     *     type="json",
     *     nullable=false
     * )
     */
    protected $hashes = [];

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

    public function getId()
    {
        return $this->id;
    }

    public function setPage(SitePage $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function getPage(): SitePage
    {
        return $this->page;
    }

    public function setSourcePage(?SitePage $sourcePage): self
    {
        $this->sourcePage = $sourcePage;
        return $this;
    }

    public function getSourcePage(): ?SitePage
    {
        return $this->sourcePage;
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

    public function setHashes(array $hashes): self
    {
        $this->hashes = $hashes;
        return $this;
    }

    public function getHashes(): array
    {
        return $this->hashes ?: [];
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
