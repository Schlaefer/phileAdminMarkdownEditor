<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor\Lib;

use Cake\Filesystem\File;
use Phile\Core\ServiceLocator;
use Phile\Exception;

class ContentFile extends File
{

    public function __construct($path, $create = false, $mode = 0755)
    {
        if (is_callable([$path, 'getFileName'])) {
            $path = $path->getFilePath();
        }
        parent::__construct($path, $create, $mode);
    }


    public function write($data, $mode = 'w', $force = false)
    {
        $this->clearCache();

        return parent::write($data, $mode, $force);
    }

    public function delete()
    {
        $this->clearCache();

        return parent::delete();
    }

    protected function clearCache()
    {
        if (!ServiceLocator::hasService('Phile_Cache')) {
            return;
        }
        $cache = ServiceLocator::getService('Phile_Cache');
        $cache->clean();
    }

}
