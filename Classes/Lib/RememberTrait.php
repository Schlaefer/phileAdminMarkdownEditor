<?php

namespace Phile\Plugin\Siezi\PhileAdminMarkdownEditor\Lib;

trait RememberTrait {

    private $remember = [];

    protected function remember($key, callable $callback, $force = false) {
        if (!$force && isset($this->remember[$key])) {
            return $this->remember[$key];
        }
        return $this->remember[$key] = call_user_func($callback);
    }

}
