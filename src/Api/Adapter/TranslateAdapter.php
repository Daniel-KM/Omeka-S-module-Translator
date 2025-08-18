<?php declare(strict_types=1);

namespace Translate\Api\Adapter;

use Common\Api\Adapter\CommonAdapterTrait;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Translator\Entity\Text;

class TranslateAdapter extends AbstractEntityAdapter
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

    public function getResourceName()
    {
        return 'translates';
    }

    public function getRepresentationClass()
    {
        return \Translate\Api\Representation\TranslateRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Translate\Entity\Translate::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $this->buildQueryFields($qb, $query);
        $result = $this->buildQueryFields($qb, $query, 'omeka_text', [
            'string' => [
                'lang_source' => 'lang',
                'string' => 'string',
            ],
        ]);
        if ($result) {
            $qb
                ->innerJoin('omeka_root.text', 'omeka_text', 'WITH', $qb->expr()->eq('omeka_root.text', 'omeka_text.id'));
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
        /** @var \Translate\Entity\Translate $entity */

        // The data are checked in validateRequest().

        $data = $request->getContent();

        if ($this->shouldHydrate($request, 'o:string')
            || $this->shouldHydrate($request, 'o:lang_source')
        ) {
            $string = trim((string) ($data['o:string'] ?? ''));
            $lang = trim((string) ($data['o:lang_source'] ?? ''));
            if (Request::CREATE === $request->getOperation()) {
                $text = $this->getText($string, $lang)
                    ?: (new Text())->setString($string)->setLang($lang);
            } else {
                $existingText = $entity->getText();
                $existingLang = $existingText->getLang();
                $existingString = $existingText->getString();
                // Keep the existing text if string and lang are not updated.
                if ($string !== $existingString || $lang !== $existingLang) {
                    // If there is a new string or lang, check existing text.
                    $text = $this->getText($string, $lang);
                    if ($text) {
                        // Remove existing text if not linked to another,
                        // translation.
                        // TODO Copy and keep existing translation.
                        if ($existingText->getTranslates()->count() <= 1) {
                            $this->getEntityManager()->remove($existingText);
                        }
                    } else {
                        // Keep existing text if not linked to another translation.
                        if ($existingText->getTranslates()->count() > 1) {
                            $text = (new Text())->setString($string)->setLang($lang);
                        } else {
                            $text = $existingText->setString($string)->setLang($lang);
                        }
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
        /** @var \Translate\Entity\Translate $entity */

        $text = $entity->getText();
        $langTarget = $entity->getLang();
        if ($text->getId() && !$this->isUnique($entity, ['text' => $text, 'lang' => $langTarget])) {
            $errorStore->addError('o:string', new PsrMessage(
                'The string "{string}", source language "{language}" and target language "{language_2}" to translate must be unique.', // @translate
                ['string' => $text->getString(), 'language' => $text->getLang(), 'language_2' => $langTarget]
            ));
        }
    }

    protected function getText(string $string, ?string $lang): ?Text
    {
        return $this->getEntityManager()->getRepository(\Translate\Entity\Text::class)->findOneBy([
            'lang' => $lang,
            'string' => $string,
        ]);
    }
}
