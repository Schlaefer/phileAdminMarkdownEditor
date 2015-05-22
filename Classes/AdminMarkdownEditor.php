<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor;

use Phile\Plugin\Siezi\PhileAdmin\Lib\AdminController;
use Phile\Plugin\Siezi\PhileAdmin\Lib\Helper\StringHelper;
use Phile\Plugin\Siezi\PhileAdminMarkdownEditor\Lib\DraftRepository;
use Symfony\Component\HttpFoundation\Request;

class AdminMarkdownEditor extends AdminController
{

    protected function getRoutes($controllers)
    {
        $controllers->match('add', [$this, 'add']);
        $controllers->match('edit/{type}/{pageId}', [$this, 'edit'])
          ->assert('pageId', '.*');
        $controllers->post('trash', [$this, 'trash'])
          ->bind('pages/trash');

        return $controllers;
    }

    /**
     * list all pages
     */
    public function index()
    {
        $getFolder = function ($type) {
            $repository = $this->app['siezi.phileAmdinMarkdownEditor.contentRepositoryFactory']($type);
            $options = ['pages_order' => 'meta.date:desc page.filePath:asc'];
            $pages = $repository->findAll($options);
            return $pages;
        };
        $drafts = $getFolder('draft');
        $pages = $getFolder('content');

        $trashForm = $this->getTrashForm()->createView();
        $vars = compact('pages', 'drafts', 'trashForm');

        return $this->render('pages/index.twig', $vars);
    }

    /**
     * add a new page
     */
    public function add()
    {
        $select = $this->getFolderSelect();
        $form = $this->app['form.factory']->createBuilder('form')
          ->add('title', 'text', [
            'label' => $this->trans('siezi.phileAdminPages.label.title'),
          ])
          ->add('place', 'choice', [
            'label' => $this->trans('siezi.phileAdminPages.label.place'),
            'choices' => $select
          ])
          ->getForm();

        $form->handleRequest($this->app['request']);
        if ($form->isValid()) {
            $data = $form->getData();
            $title = $data['title'];
            $type = ($data['place'] === 'draft') ? 'draft' : 'content';
            $folder = ($type === 'draft') ? '/' : $data['place'];
            $content = '<!--
Title: ' . $title . '
Author:
Date: ' . date('Y-m-d') . '
-->

#' . $title . '#';
            $repo = $this->app['siezi.phileAmdinMarkdownEditor.contentRepositoryFactory']($type);
            try {
                $title = StringHelper::slug($title);
                $page = $repo->create($title, $folder, $content);
                return $this->redirect('pages/edit/' . $type . '/' . $page->getPageId());
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $this->flash("Error while creating page: $message", 'error');
            }
        }

        $data = [
          'form' => $form->createView(),
          'contentExt' => $this->app['plugin']->getConfig('contentExt')
        ];

        return $this->render('pages/add.twig', $data);
    }

    /**
     * edit an existing page
     *
     * @param $pageId
     * @return mixed
     */
    public function edit($type, $pageId)
    {
        $repository = $this->app['siezi.phileAmdinMarkdownEditor.contentRepositoryFactory']($type);
        try {
            $page = $repository->findByPath($pageId);
        } catch (\Exception $e) {
            $this->flash($e->getMessage(), 'error');
            return $this->redirect('pages/');
        }

        if (empty($page)) {
            throw new \RuntimeException("File $pageId not found.");
        }

        $data = [
          'content' => $page->getRawData(),
          'name' => $page->getBasename(false)
        ];

        $currentPlace = ($type === 'draft') ? 'draft' : $page->getContentFolderRelativeFolder();

        $form = $this->app['form.factory']
          ->createBuilder('form', $data, ['csrf_protection' => false])
          ->add('name', 'text', [
              'label' => $this->trans('siezi.phileAdminPages.label.title')
          ])
          ->add('place', 'choice', [
              'choices' => $this->getFolderSelect(),
              'label' => $this->trans('siezi.phileAdminPages.label.place'),
              'data' => $currentPlace
          ])
          ->add('content', 'textarea', [
            'label' => $this->trans('siezi.phileAdminPages.label.content')
          ])
          ->getForm();

        $form->handleRequest($this->app['request']);
        if ($form->isValid()) {
            $data = $form->getData();
            $successMsg = function() {
                $this->flash(
                  $this->trans('siezi.phileAdminPages.message.saved.success'),
                  'success'
                );
            };

            //= save page
            try {
                $page->setRawData($data['content']);
                $page->save();
            } catch (\Exception $e) {
                $errors = true;
                $this->flash(
                  $this->trans('siezi.phileAdminPages.message.saved.failure'),
                  'error'
                );
            }

            // root pageId '' is null when received from from here
            $data['place'] = $data['place'] === null ? '' : $data['place'];
            //= rename & move
            if ($currentPlace !== $data['place'] || $page->getPageId() !== $data['name']) {
                try {
                    $targetPageId = StringHelper::slug($data['name']);
                    if ($data['place'] === 'draft') {
                        $targetType = 'draft';
                    } else {
                        $targetType = 'content';
                        $targetPageId = $data['place'] . '/' . $targetPageId ;
                    }
                    $target = $this->app['siezi.phileAmdinMarkdownEditor.contentRepositoryFactory']($targetType);

                    $target->add($page, $targetPageId);
                    $repository->delete($page);

                    $successMsg();
                    return $this->redirect("pages/edit/{$targetType}/{$targetPageId}");
                } catch (\Exception $e) {
                    $errors = true;
                    $this->flash(
                      $this->trans('siezi.phileAdminPages.message.saved.success'),
                      'success'
                    );
                    $this->flash(
                      $this->trans('siezi.phileAdminPages.message.moved.failure'),
                      'error'
                    );
                }
            }

            if (!$errors) {
                $successMsg();
            }
        }

        $data = [
          'contentExt' => $this->app['plugin']->getConfig('contentExt'),
          'form' => $form->createView(),
          'page' => $page,
          'url' => $pageId
        ];

        return $this->render('pages/edit.twig', $data);
    }

    /**
     * move page to trash
     */
    public function trash()
    {
        $form = $this->getTrashForm();
        $form->handleRequest($this->app['request']);
        $success = false;
        if ($form->isValid()) {
            try {
                $data = $form->getData();
                $source = $this->app['siezi.phileAmdinMarkdownEditor.contentRepositoryFactory']($data['type']);
                $page = $source->findByPath($data['pageId']);

                $target = $this->app['siezi.phileAmdinMarkdownEditor.contentRepositoryFactory']('trash');
                $pageId = StringHelper::slug($page->getContentFolderRelativeFolder())
                  . '-' . basename($page->getPageId())
                  . '-' . time();
                $target->add($page, $pageId);
                $source->delete($page);
                $success = true;
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $this->flash($message, 'error');
            }
        }
        if ($success) {
            $this->flash(
              $this->trans('siezi.phileAdminPages.message.trash.success'),
              'success'
            );
        } else {
            $this->flash(
              $this->trans('siezi.phileAdminPages.message.trash.failure'),
              'error'
            );
        }

        return $this->redirect('pages/');
    }

    protected function getTrashForm()
    {
        return $this->app['form.factory']
          ->createBuilder('form')
          ->add('pageId', 'hidden')
          ->add('type', 'hidden')
          ->getForm();
    }

    protected function getFolderSelect()
    {
        $repository = $this->app['siezi.phileAmdinMarkdownEditor.contentRepositoryFactory']('content');
        $folders = [
          'draft' => $this->trans('siezi.phileAdminPages.place.draft'),
          $this->trans('siezi.phileAdminPages.place.content') => $repository->getExistingFolders()
        ];

        return $folders;
    }

}
