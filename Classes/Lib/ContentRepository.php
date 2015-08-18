<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor\Lib;

use Cake\Filesystem\Folder;
use Phile\Model\Page;
use Phile\Plugin\Siezi\PhileAdmin\Lib\Helper\StringHelper;
use Phile\Repository\Page as Repository;
use Phile\Repository\PageCollection;

class ContentRepository extends Repository
{

    protected $contentExt;

    protected $folder;

    public function __construct(array $settings = [])
    {
        foreach (['contentExt', 'folder'] as $attribute) {
            $this->$attribute = $settings[$attribute];
            unset($settings[$attribute]);
        }
        unset($settings['folder'], $settings['contentExt']);
        if (empty($this->folder)) {
            throw new \InvalidArgumentException(
              'Draft folder is not set.',
              1432125100
            );
        }
        $this->setupFolder();

        return parent::__construct();
    }

    protected function setupFolder()
    {
        if (empty($this->folder) || strpos($this->folder, ROOT_DIR) !== 0) {
            return;
        }
        if (!file_exists($this->folder)) {
            mkdir($this->folder, 0775, true);
        }
    }

    public function findByPath($pageId, $folder = CONTENT_DIR)
    {
        $page = parent::findByPath($pageId, $this->folder);
        if (empty($page)) {
            throw new \RuntimeException("Page \"$pageId\" not found.");
        }

        return $this->decorate($page);
    }

    public function findAll(array $options = array(), $folder = CONTENT_DIR)
    {
        $pages = parent::findAll($options, $this->folder);

        return $this->decorate($pages);
    }

    /**
     * get repository folder path
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * return all existing "folders" in repository as [<pageId> => '/<pageId>/']
     */
    public function getExistingFolders()
    {
        $folder = new Folder($this->getFolder());
        $paths = $folder->tree(null, false, 'dir');
        $folders = [];
        foreach ($paths as $path) {
            $path = str_replace($this->getFolder(), '', $path);
            $title = '/' . $path . '/';
            $title = str_replace('//', '/', $title);
            $folders[$path] = $title;
        }
        unset($folders['.']);
        asort($folders);

        return $folders;
    }

    /**
     * add $page to repository as $pageId
     */
    public function add($page, $pageId)
    {
        if (!is_string($pageId)) {
            $pageId = $pageId->getContentFolderRelativePath();
        }
        $targetPath = $this->getFolder() . $pageId . $this->contentExt;
        $target = new ContentFile($targetPath);
        if ($target->exists()) {
            throw new \RuntimeException(
              "\"$targetPath\" already exists.",
              1432278115
            );
        }
        $file = new ContentFile($page);
        if (!$file->copy($targetPath)) {
            throw new \RuntimeException(
              "Couldn't create \"$targetPath\".",
              1432278114
            );
        }

        return $this->findByPath($pageId);
    }

    /**
     * delete page with $pageId
     */
    public function delete($pageId)
    {
        if (!is_string($pageId)) {
            $pageId = $pageId->getContentFolderRelativePath();
        }
        $path = $this->getFolder() . $pageId;
        $file = new ContentFile($path);
        if (!$file->delete()) {
            throw new \RuntimeException(
              "Couldn't delete \"$path\".",
              1432278132
            );
        }
    }

    /**
     * create new page with $title, in $folder with $content
     */
    public function create($title, $folder, $content = '')
    {
        $title = $this->slug($title);
        $relative = trim($folder, '/') . '/' . $title;
        $path = $this->folder . $relative . $this->contentExt;
        $file = new ContentFile($path);
        if ($file->exists()) {
            throw new \InvalidArgumentException("File \"$path\" already exists.");
        }
        if (!file_exists($this->folder . $folder)) {
            throw new \InvalidArgumentException("Folder \"$folder\" does not exist.");
        }
        $file->create();
        $file->write($content);

        return $this->findByPath($relative);
    }


    /**
     * Create PageDecorator objects
     *
     * @param Page|PageCollection $pages pages to decorate
     * @return PageDecorator|PageCollection
     */
    protected function decorate($pages)
    {
        if ($pages instanceof PageCollection) {
            $new = [];
            foreach ($pages as $key => $page) {
                $new[$key] = $this->decorate($page);
            }
            return new PageCollection(function () use ($new) {
                return $new;
            });
        } elseif ($pages instanceof Page) {
            return new PageDecorator($pages);
        }
        throw new \InvalidArgumentException();
    }

    protected function slug($text)
    {
        return StringHelper::slug($text);
    }

}