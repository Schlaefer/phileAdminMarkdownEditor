<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor\Lib;

class ContentRepositoryFactory {

    /**
     * @param $type
     * @return ContentRepository
     */
    public static function create($type, \Silex\Application $app) {
        switch ($type) {
            case ('draft'):
                $storageDir = $app['plugin']->getConfig('storageDir');
                $folder = $storageDir . 'siezi-adminMarkdownEditor/drafts/';
                break;
            case ('trash'):
                $storageDir = $app['plugin']->getConfig('storageDir');
                $folder = $storageDir . 'siezi-adminMarkdownEditor/trash/';
                break;
            case ('content'):
                $folder = $app['plugin']->getConfig('contentDir');
                break;
            default:
                throw new \InvalidArgumentException("Couldn't create repository for type '$type'.");
        }
        $settings = [
          'folder' => $folder,
          'contentExt' => $app['plugin']->getConfig('contentExt')
        ];
        return new ContentRepository($settings);
    }

}

