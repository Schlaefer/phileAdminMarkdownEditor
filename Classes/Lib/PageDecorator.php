<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor\Lib;

use Phile\Model\Page;

class PageDecorator
{

    use RememberTrait;

    /**
     * @var Page
     */
    protected $page;

    public function __construct(Page $page)
    {
        $this->page = $page;
    }

    public function __call($name, $args)
    {
        if (is_callable([$this->page, $name])) {
            return call_user_func_array([$this->page, $name], $args);
        }
        $name = 'get' . ucfirst($name);
        if (is_callable([$this->page, $name])) {
            return call_user_func_array([$this->page, $name], $args);
        }
        $class = get_class($this->page);
        throw new \BadMethodCallException("Method '$name' not defined in class '$class'.");
    }

    public function setRawData($content)
    {
        $this->setProtected('rawData', $content);
    }

    protected function setProtected($key, $value)
    {
        $this->getAccessible($key)->setValue($this->page, $value);
    }

    protected function getAccessible($attribute)
    {
        return $this->remember($attribute, function () use ($attribute) {
            $class = new \ReflectionClass($this->page);
            $property = $class->getProperty($attribute);
            $property->setAccessible(true);

            return $property;
        });
    }

    public function save()
    {
        $file = $this->getFile();
        if (!$file->write($this->getRawData())) {
            throw new \RuntimeException(
              "Couldn't save page at {$file->path}.",
              1432281245
            );
        }
        $this->reload();
    }

    /**
     * @return ContentFile
     */
    protected function getFile()
    {
        return $this->remember('file', function () {
            return new ContentFile($this);
        });
    }

    public function getRawData()
    {
        return $this->getProtected('rawData');
    }

    protected function getProtected($key)
    {
        return $this->getAccessible($key)->getValue($this->page);
    }

    protected function reload()
    {
        $this->page->__construct($this->getFilePath(),
          $this->getContentFolder());
    }

    protected function getContentFolder()
    {
        return $this->getProtected('contentFolder');
    }

    public function getBasename($extension = true)
    {
        $basename = basename($this->page->getFilePath());
        if (!$extension) {
            $basename = preg_replace('/^(.*)(\..+?)$/', "\\1", $basename);
        }
        return $basename;
    }

    public function getContentFolderRelativeFolder()
    {
        $folder = dirname($this->getContentFolderRelativePath());
        if ($folder === '.') {
            $folder = '';
        }

        return $folder;
    }

    public function getContentFolderRelativePath()
    {
        return str_replace($this->getContentFolder(), '',
          $this->page->getFilePath());
    }

}
