<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor;

use Phile\Plugin\AbstractPlugin;
use Phile\Plugin\Siezi\PhileAdminMarkdownEditor\Lib\ContentRepositoryFactory;

class Plugin extends AbstractPlugin
{

    protected $events = [
      'siezi.phileAdmin.beforeAppRun' => 'onAdmin'
    ];

    protected function onAdmin($eventData)
    {
        $eventData['app']['siezi.phileAmdinMarkdownEditor.contentRepositoryFactory'] = function ($c) {
            return function ($type) use ($c) {
                return ContentRepositoryFactory::create($type, $c);
            };
        };

        $adminPlugin = $eventData['app']['adminPlugin_factory'];
        $adminPlugin
          ->setMenu('siezi.phileAdminPages.title', '/pages')
          ->setLocalesFolder($this->getPluginPath('locales'))
          ->setTemplateFolder($this->getPluginPath('templates'))
          ->setRoutes(['/pages' => new AdminMarkdownEditor()]);
        $eventData['plugins']->add($adminPlugin);
    }

}
