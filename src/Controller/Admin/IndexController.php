<?php declare(strict_types=1);

namespace Translator\Controller\Admin;

use Common\Stdlib\PsrMessage;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Translator\Api\Representation\TranslationRepresentation;
use Translator\Form\TranslationForm;

/**
 * Adapted from Omeka controllers.
 */
class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function browseAction()
    {
        $this->browse()->setDefaults('translations');
        $response = $this->api()->search('translations', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        // Set the return query for batch actions. Note that we remove the page
        // from the query because there's no assurance that the page will return
        // results once changes are made.
        $returnQuery = $this->params()->fromQuery();
        unset($returnQuery['page']);

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], ['query' => $returnQuery], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], ['query' => $returnQuery], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $translations= $response->getContent();

        return new ViewModel([
            'translations' => $translations,
            'resources' => $translations,
            'formDeleteSelected' => $formDeleteSelected,
            'formDeleteAll' => $formDeleteAll,
            'returnQuery' => $returnQuery,
        ]);
    }

    public function showAction()
    {
        $translation = $this->getTranslationFromRoute();
        return new ViewModel([
            'translation' => $translation,
            'resource' => $translation,
        ]);
    }

    public function showDetailsAction()
    {
        $translation = $this->getTranslationFromRoute();

        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $view = new ViewModel([
            'translation' => $translation,
            'resource' => $translation,
            'linkTitle' => $linkTitle,
        ]);
        $view->setTerminal(true);
        return $view;
    }

    public function addAction()
    {
        /** @var \Translator\Form\TranslationForm $form */
        $form = $this->getForm(TranslationForm::class);
        $form
            ->setAttribute('action', $this->url()->fromRoute(null, [], true))
            ->setAttribute('enctype', 'multipart/form-data')
            ->setAttribute('id', 'add-translation');

        if ($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();
            $form->setData($post);
            if ($form->isValid()) {
                $data = $form->getData();
                $response = $this->api($form)->create('translations', $data);
                if ($response) {
                    /** @var \Translator\Api\Representation\TranslationRepresentation $translation */
                    $translation = $response->getContent();
                    $message = new PsrMessage(
                        'Translation successfully created. {link}Add another translation?{link_end}', // @translate
                        [
                            'link' => sprintf('<a href="%s">', htmlspecialchars($this->url()->fromRoute(null, [], true))),
                            'link_end' => '</a>',
                        ]
                    );
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                    return $this->redirect()->toUrl($translation->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'form' => $form,
        ]);
    }

    public function editAction()
    {
        $translation = $this->getTranslationFromRoute();
        $data = $translation->jsonSerialize();

        /** @var \Translator\Form\TranslationForm $form */
        $form = $this->getForm(TranslationForm::class);
        $form
            ->setAttribute('action', $this->url()->fromRoute(null, [], true))
            ->setAttribute('enctype', 'multipart/form-data')
            ->setAttribute('id', 'edit-translation')
            ->setData($data)
        ;

        if ($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();
            $form->setData($post);
            if ($form->isValid()) {
                $data = $form->getData();
                $response = $this->api($form)->update('translations', ['id' => $translation->id()], $data);
                if ($response) {
                    $translation = $response->getContent();
                    $this->messenger()->addSuccess('Translation successfully updated.'); // @translate
                    return $this->redirect()->toUrl($translation->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'form' => $form,
            'translation' => $translation,
            'resource' => $translation,
        ]);
    }

    public function deleteConfirmAction()
    {
        $translation = $this->getTranslationFromRoute();

        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $view = new ViewModel([
            'translation' => $translation,
            'resource' => $translation,
            'linkTitle' => $linkTitle,
            'resourceLabel' => 'translation', // @translate
            'partialPath' => 'translator/admin/index/show-details',
        ]);
        $view
            ->setTemplate('common/delete-confirm-details')
            ->setTerminal(true);
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $slug = $this->params('slug');
                $response = $this->api($form)->delete('translations', is_numeric($slug) ? ['id' => $slug] : ['slug' => $slug]);
                if ($response) {
                    $this->messenger()->addSuccess('Translation successfully deleted.'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/translation',
            ['action' => 'browse'],
            true
        );
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('admin/translation', ['action' => 'browse'], true);
        }

        $returnQuery = $this->params()->fromQuery();
        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one translation to batch delete.'); // @translate
            return $this->redirect()->toRoute('admin/translation', ['action' => 'browse'], ['query' => $returnQuery], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('translations', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Translations successfully deleted.'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute('admin/translation', ['action' => 'browse'], ['query' => $returnQuery], true);
    }

    public function batchDeleteAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('admin/translation', ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $job = $this->jobDispatcher()->dispatch(\Omeka\Job\BatchDelete::class, [
                'resource' => 'translations',
                'query' => $query,
            ]);
            $urlPlugin = $this->url();
            $message = new PsrMessage(
                'Deleting translations started in background (job {link_job}#{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
                [
                    'link_job' => sprintf(
                        '<a href="%s">',
                        htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                    ),
                    'job_id' => $job->getId(),
                    'link_end' => '</a>',
                    'link_log' => class_exists('Log\Module', false)
                        ? sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                        : sprintf('<a href="%1$s" target="_blank">', $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
                ]
            );
            $message->setEscapeHtml(false);
            $this->messenger()->addSuccess($message);
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute('admin/translation', ['action' => 'browse'], ['query' => $this->params()->fromQuery()], true);
    }

    /**
     * @throws \Omeka\Api\Exception\NotFoundException
     */
    protected function getTranslationFromRoute(): TranslationRepresentation
    {
        $id = $this->params('id');
        $response = $this->api()->read('translations', ['id' => $id]);
        return $response->getContent();
    }
}
