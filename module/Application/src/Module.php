<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Application;

class Module
{
    const TITLE = "Chronos - Time Entry System";
    const VERSION = "v1.1.3";
    
    public function getConfig() : array
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
