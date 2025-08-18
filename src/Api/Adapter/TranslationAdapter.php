<?php declare(strict_types=1);

namespace Translator\Api\Adapter;

use Common\Api\Adapter\CommonAdapterTrait;
use Common\Stdlib\PsrMessage;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Translator\Entity\Text;

class TranslationAdapter extends AbstractEntityAdapter implements EventSubscriber
{
    use CommonAdapterTrait;

    // TODO Manage sort and scalar for lang_source and string.

    protected $sortFields = [
        'id' => 'id',
        // 'lang_source' => 'langSource',
        'lang_target' => 'lang',
        'automatic' => 'automatic',
        'reviewed' => 'reviewed',
        // 'string' => 'string',
        'translation' => 'translation',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $scalarFields = [
        'id' => 'id',
        // 'lang_source' => 'langSource',
        'lang_target' => 'langTarget',
        'automatic' => 'automatic',
        'reviewed' => 'reviewed',
        // 'string' => 'string',
        'translation' => 'translation',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $queryFields = [
        // Lang source and string are now in another table.
        'string' => [
            // 'lang_source' => 'lang',
            'lang_target' => 'lang',
            // 'string' => 'string',
            'translation' => 'translation',
        ],
        'bool' => [
            'automatic' => 'automatic',
            'reviewed' => 'reviewed',
        ],
        'datetime_operator' => [
            'created' => 'created',
            'modified' => 'modified',
        ],
    ];

    protected static $texts = [];

    public function getResourceName()
    {
        return 'translations';
    }

    public function getRepresentationClass()
    {
        return \Translator\Api\Representation\TranslationRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Translator\Entity\Translation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $this->buildQueryFields($qb, $query);
        $result = $this->buildQueryFields($qb, $query, 'translate_text', [
            'string' => [
                'string' => 'string',
            ],
            'string_empty' => [
                'lang_source' => 'lang',
            ],
        ]);
        if ($result) {
            $qb
                ->innerJoin('omeka_root.text', 'translate_text');
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (!isset($data['o:lang_target'])
            || !strlen(trim((string) $data['o:lang_target']))
        ) {
            $errorStore->addError('o:lang_target', 'The translation requires a target language.'); // @translate
        }
        if (!isset($data['o:string'])
            || !strlen(trim((string) $data['o:string']))
        ) {
            $errorStore->addError('o:string', 'The translation requires a source string.'); // @translate
        }
        if (!isset($data['o:translation'])
            || !strlen(trim((string) $data['o:translation']))
        ) {
            $errorStore->addError('o:translation', 'The translation requires a translated string.'); // @translate
        }
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \Translator\Entity\Translation $entity */

        // The data are checked in validateRequest().

        // During creation or update, the entity manager is not yet flushed, so
        // there are multiple times the same source string and language. The
        // same Text should be used each time to make it unique. So it is cached
        // and persisited separately and immediately after creation.
        // Another solution is to store a Text with multiple translations, but
        // the logic is the inverse in the module.

        $data = $request->getContent();

        if ($this->shouldHydrate($request, 'o:string')
            || $this->shouldHydrate($request, 'o:lang_source')
        ) {
            $string = trim((string) ($data['o:string'] ?? ''));
            $lang = trim((string) ($data['o:lang_source'] ?? '')) ?: null;
            $text = null;
            if (Request::CREATE === $request->getOperation()) {
                $text = $this->getOrCreateText($string, $lang, true);
            } else {
                $existingText = $entity->getText();
                $existingLang = $existingText->getLang();
                $existingString = $existingText->getString();
                // Keep the existing text if string and lang are not updated.
                if ($string !== $existingString || $lang !== $existingLang) {
                    // If there is a new string or lang, check existing text.
                    $text = $this->getOrCreateText($string, $lang, false);
                    if ($text) {
                        // Remove existing text if not linked to another,
                        // translation.
                        // TODO Copy and keep existing translation.
                        if ($existingText->getTranslations()->count() <= 1) {
                            $this->getEntityManager()->remove($existingText);
                        }
                    } elseif ($existingText->getTranslations()->count() <= 1) {
                        // Keep existing text if not linked to another translation.
                        $text = $existingText->setString($string)->setLang($lang);
                    } else {
                        $this->getOrCreateText($string, $lang, true);
                    }
                }
            }
            $entity->setText($text);
        }
        if ($this->shouldHydrate($request, 'o:lang_target')) {
            $string = trim((string) ($data['o:lang_target'] ?? ''));
            $entity->setLang($string);
        }
        if ($this->shouldHydrate($request, 'o:translation')) {
            $string = trim((string) ($data['o:translation'] ?? ''));
            $entity->setTranslation($string);
        }
        if ($this->shouldHydrate($request, 'o:automatic')) {
            $entity->setAutomatic(!empty($data['o:automatic']));
        }
        if ($this->shouldHydrate($request, 'o:reviewed')) {
            $entity->setReviewed(!empty($data['o:reviewed']));
        }

        $this->updateTimestamps($request, $entity);
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        /** @var \Translator\Entity\Translation $entity */

        $text = $entity->getText();
        $langTarget = $entity->getLang();
        if ($text->getId() && !$this->isUnique($entity, ['text' => $text, 'lang' => $langTarget])) {
            $errorStore->addError('o:string', new PsrMessage(
                'The string "{string}", source language "{language}" and target language "{language_2}" to translate must be unique.', // @translate
                ['string' => $text->getString(), 'language' => $text->getLang(), 'language_2' => $langTarget]
            ));
        }
    }

    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        self::$texts = [];
    }

    protected function getOrCreateText(string $string, ?string $lang, bool $create): Text
    {
        $lang = $lang ?: '';

        $cacheKey = sha1('/' . $lang . '/' . $string . '/');

        if (isset(self::$texts[$cacheKey])) {
            return self::$texts[$cacheKey];
        }

        $text = $this->getEntityManager()->getRepository(\Translator\Entity\Text::class)->findOneBy([
            'lang' => $lang,
            'string' => $string,
        ]);

        if (!$text && $create) {
            $text = (new Text())->setString($string)->setLang($lang);
            $this->getEntityManager()->persist($text);
        }

        self::$texts[$cacheKey] = $text;

        return $text;
    }
}
