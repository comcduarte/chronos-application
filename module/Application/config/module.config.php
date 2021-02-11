<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Application;

use Application\Controller\CustomReportController;
use Application\Controller\FilesController;
use Application\Controller\IndexController;
use Application\Controller\Factory\CustomReportControllerFactory;
use Application\Controller\Factory\IndexControllerFactory;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'router' => [
        'routes' => [
            'home' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'custom-report' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/custom-report[/:uuid]',
                    'defaults' => [
                        'controller' => Controller\CustomReportController::class,
                        'action'     => 'view',
                    ],
                ],
            ],
            'application' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/application[/:action]',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => TRUE,
                'child_routes' => [
                    'files' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/files',
                            'defaults' => [
                                'controller' => Controller\FilesController::class,
                            ],
                        ],
                    ],
                ],
            ],
            
        ],
    ],
    'acl' => [
        'EVERYONE' => [
            'home' => ['index'],
        ],
    ],
    'controllers' => [
        'factories' => [
            IndexController::class => IndexControllerFactory::class,
            FilesController::class => InvokableFactory::class,
            CustomReportController::class => CustomReportControllerFactory::class,
        ],
    ],
    'navigation' => [
        'default' => [
            'home' => [
                'label' => 'Home',
                'route' => 'home',
                'order' => 0,
            ],
            'settings' => [
                'label' => 'Settings',
                'pages' => [
                    [
                        'label' => 'Tax Document Upload',
                        'route' => 'application/files',
                        'action' => 'upload',
                        'resource' => 'application/files',
                        'privilege' => 'upload',
                    ],
                ],
            ],
        ],
        
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => [
            'navigation'              => __DIR__ . '/../view/partials/navigation.phtml',
            'flashmessenger'          => __DIR__ . '/../view/partials/flashmessenger.phtml',
            'layout/layout'           => __DIR__ . '/../../User/view/layout/user-layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];
