<?php declare(strict_types=1);

namespace Translate\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class TranslationAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'string' => 'string',
        'lang' => 'lang',
        'translation' => 'translation',
        'locale' => 'locale',
        'automatic' => 'automatic',
        'reviewed' => 'reviewed',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'string' => 'string',
        'lang' => 'lang',
        'translation' => 'translation',
        'automatic' => 'automatic',
        'reviewed' => 'reviewed',
    ];

    public function getResourceName()
    {
        return 'translations';
    }

    public function getRepresentationClass()
    {
        return \Translate\Api\Representation\TranslationRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Translate\Entity\Translation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['string']) && strlen((string) $query['string'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.string',
                $this->createNamedParameter($qb, $query['string']))
            );
        }

        if (isset($query['lang']) && strlen((string) $query['lang'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.lang',
                $this->createNamedParameter($qb, $query['lang']))
                );
        }

        if (isset($query['translation']) && strlen((string) $query['translation'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.translation',
                $this->createNamedParameter($qb, $query['translation']))
            );
        }

        if (isset($query['locale']) && strlen((string) $query['locale'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.locale',
                $this->createNamedParameter($qb, $query['locale']))
            );
        }

        if (isset($query['automatic'])
            && (is_numeric($query['automatic']) || is_bool($query['automatic']))
        ) {
            $qb->andWhere($expr->eq(
                'omeka_root.automatic',
                $this->createNamedParameter($qb, (bool) $query['automatic'])
            ));
        }

        if (isset($query['reviewed'])
            && (is_numeric($query['reviewed']) || is_bool($query['reviewed']))
        ) {
            $qb->andWhere($expr->eq(
                'omeka_root.reviewed',
                $this->createNamedParameter($qb, (bool) $query['reviewed'])
            ));
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (!isset($data['o:string'])
            || !strlen(trim((string) $data['o:string']))
        ) {
            $errorStore->addError('o:string', 'The translation requires a source string.'); // @translate
        }
        if (!isset($data['o:lang'])
            || !strlen(trim($data['o:lang']))
        ) {
            $errorStore->addError('o:lang', 'The translation requires a source language.'); // @translate
        }
        if (!isset($data['o:translation'])
            || !strlen(trim((string) $data['o:translation']))
        ) {
            $errorStore->addError('o:translation', 'The translation requires a translated string.'); // @translate
        }
        if (!isset($data['o:locale'])
            || !strlen(trim((string) $data['o:locale']))
        ) {
            $errorStore->addError('o:locale', 'The translation requires a language for translated string.'); // @translate
        }
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \Translate\Entity\Translation $entity */

        // The data are checked in validateRequest().

        $data = $request->getContent();

        if ($this->shouldHydrate($request, 'o:string')) {
            $string = trim((string) ($data['o:string'] ?? ''));
            $entity->setString($string);
        }
        if ($this->shouldHydrate($request, 'o:lang')) {
            $string = trim((string) ($data['o:lang'] ?? ''));
            $entity->setLang($string);
        }
        if ($this->shouldHydrate($request, 'o:translation')) {
            $string = trim((string) ($data['o:translation'] ?? ''));
            $entity->setTranslation($string);
        }
        if ($this->shouldHydrate($request, 'o:locale')) {
            $string = trim((string) ($data['o:locale'] ?? ''));
            $entity->setLocale($string);
        }
        if ($this->shouldHydrate($request, 'o:automatic')) {
            $entity->setAutomatic(!empty($data['o:automatic']));
        }
        if ($this->shouldHydrate($request, 'o:reviewed')) {
            $entity->setAutomatic(!empty($data['o:reviewed']));
        }
    }
}
