<?php declare(strict_types=1);

namespace Translate\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Common\Stdlib\PsrMessage;

class TranslateAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'lang_source' => 'langSource',
        'lang_target' => 'langTarget',
        'automatic' => 'automatic',
        'reviewed' => 'reviewed',
        'string' => 'string',
        'translation' => 'translation',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'lang_source' => 'langSource',
        'lang_target' => 'langTarget',
        'automatic' => 'automatic',
        'reviewed' => 'reviewed',
        'string' => 'string',
        'translation' => 'translation',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $queryFields = [
        'string' => [
            'lang_source' => 'langSource',
            'lang_target' => 'langTarget',
            'string' => 'string',
            'translation' => 'translation',
        ],
        'bool' => [
            'automatic' => 'automatic',
            'reviewed' => 'reviewed',
        ],
        'datetime' => [
            'created' => ['eq', 'created'],
            'created_before' => ['lt', 'created'],
            'created_after' => ['gt', 'created'],
            'created_until' => ['lte', 'created'],
            'created_since' => ['gte', 'created'],
            'modified' => ['eq', 'modified'],
            'modified_before' => ['lt', 'modified'],
            'modified_after' => ['gt', 'modified'],
            'modified_until' => ['lte', 'modified'],
            'modified_since' => ['gte', 'modified'],
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
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (!isset($data['o:lang_source'])
            || !strlen(trim($data['o:lang_source']))
        ) {
            $errorStore->addError('o:lang_source', 'The translation requires a source language.'); // @translate
        }
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

        if ($this->shouldHydrate($request, 'o:lang_source')) {
            $string = trim((string) ($data['o:lang_source'] ?? ''));
            $entity->setLangSource($string);
        }
        if ($this->shouldHydrate($request, 'o:lang_target')) {
            $string = trim((string) ($data['o:lang_target'] ?? ''));
            $entity->setLangTarget($string);
        }
        if ($this->shouldHydrate($request, 'o:automatic')) {
            $entity->setAutomatic(!empty($data['o:automatic']));
        }
        if ($this->shouldHydrate($request, 'o:reviewed')) {
            $entity->setReviewed(!empty($data['o:reviewed']));
        }
        if ($this->shouldHydrate($request, 'o:string')) {
            $string = trim((string) ($data['o:string'] ?? ''));
            $entity->setString($string);
        }
        if ($this->shouldHydrate($request, 'o:translation')) {
            $string = trim((string) ($data['o:translation'] ?? ''));
            $entity->setTranslation($string);
        }

        $this->updateTimestamps($request, $entity);
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        /** @var \Translate\Entity\Translate $entity */

        $langSource = $entity->getLangSource();
        $langTarget = $entity->getLangTarget();
        $string = $entity->getString();
        if (!$this->isUnique($entity, ['langSource' => $langSource, 'langTarget' => $langTarget, 'string' => $string])) {
            $errorStore->addError('o:string', new PsrMessage(
                'The string "{string}", source language "{language}" and target language "{language_2}" to translate must be unique.', // @translate
                ['string' => $string, 'language' => $langSource, 'language_2' => $langTarget]
            ));
        }
    }
}
