<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor;

use Phile\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{

    protected $events = [
      'siezi.phileAdmin.beforeAppRun' => 'onAdmin'
    ];

    protected function onAdmin($eventData)
    {
        $adminPlugin = $eventData['app']['adminPlugin_factory'];
        $adminPlugin
          ->setMenu('siezi.phileAdminPages.title', '/pages')
          ->setLocalesFolder($this->getPluginPath('locales'))
          ->setTemplateFolder($this->getPluginPath('views'))
          ->setRoutes(['/pages' => new AdminMarkdownEditor()]);
        $eventData['plugins']->add($adminPlugin);
    }

}
