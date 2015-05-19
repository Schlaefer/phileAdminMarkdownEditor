<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor;

use Phile\Plugin\Siezi\PhileAdmin\Lib\AdminController;
use Phile\Repository\Page;

class AdminMarkdownEditor extends AdminController
{

    protected function getRoutes($controllers)
    {
        $controllers->match('edit/{pageId}', [$this, 'edit'])
          ->assert('pageId', '.*');

        return $controllers;
    }

    public function index()
    {
        $repository = new Page();
        $pages = $repository->findAll(['pages_order' => 'meta.date:desc page.filePath:asc']);
        $contentDir = $this->app['plugin']->getConfig('contentDir');
        foreach ($pages as $page) {
            $page->folder = str_replace($contentDir, '', $page->getFilePath());
        }

        return $this->render('pages/index.twig', ['pages' => $pages]);
    }

    public function edit($pageId)
    {
        $file = new Lib\ContentFile($pageId);
        $data = ['content' => $file->read()];
        $form = $this->app['form.factory']
          ->createBuilder('form', $data, ['csrf_protection' => false])
          ->add('content', 'textarea')
          ->getForm();

        $form->handleRequest($this->app['request']);
        if ($form->isValid()) {
            $data = $form->getData();
            $file->write($data['content']);
            $this->flash(
              $this->trans('siezi.phileAdminPages.message.saved.success'),
              'success'
            );
        }

        $data = [
          'url' => $pageId,
          'form' => $form->createView()
        ];

        return $this->render('pages/edit.twig', $data);
    }

}
