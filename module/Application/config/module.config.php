<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Application;

use Application\Controller\CustomReportController;
use Application\Controller\IndexController;
use Application\Controller\TelestaffImportController;
use Application\Controller\UnitedWayController;
use Application\Controller\Factory\CustomReportControllerFactory;
use Application\Controller\Factory\IndexControllerFactory;
use Application\Controller\Factory\TelestaffImportControllerFactory;
use Application\Controller\Factory\UnitedWayControllerFactory;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;

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
                    'route'    => '/application',
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
                    'unitedway' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/unitedway[/:action[/:uuid]]',
                            'defaults' => [
                                'controller' => Controller\UnitedWayController::class,
                            ],
                        ],
                    ],
                    'telestaff-import' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/telestaff[/:action[/:uuid]]',
                            'defaults' => [
                                'controller' => Controller\TelestaffImportController::class,
                                'action'     => 'index',
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
            Controller\FilesController::class => Controller\Factory\FilesControllerFactory::class,
            CustomReportController::class => CustomReportControllerFactory::class,
            UnitedWayController::class => UnitedWayControllerFactory::class,
            TelestaffImportController::class => TelestaffImportControllerFactory::class,
        ],
    ],
    'log' => [
        'syslogger' => [
            'writers' => [
                'syslog' => [
                    'name' => \Laminas\Log\Writer\Syslog::class,
                    'priority' => \Laminas\Log\Logger::INFO,
                    'options' => [
                        'application' => 'CHRONOS',
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'default' => [
            'home' => [
                'label' => 'Home',
                'route' => 'home',
                'order' => 0,
            ],
            'unitedway' => [
                'label' => 'United Way',
                'route' => 'application/unitedway',
                'action' => 'index',
                'resource' => 'application/unitedway',
                'privilege' => 'index',
            ],
            'utilities' => [
                'label' => 'Utilities',
                'route' => 'home',
                'action' => 'index',
                'resource' => 'application/utilities',
                'privilege' => 'menu',
                'class' => 'dropdown',
                'order' => 100,
                'pages' => [
                    [
                        'label' => 'Telestaff Upload',
                        'route' => 'application/telestaff-import',
                        'action' => 'index',
                        'resource' => 'application/telestaff-import',
                        'privilege' => 'index',
                    ],
                ],
            ],
            'settings' => [
                'label' => 'Settings',
                'pages' => [
                    [
                        'label' => 'Document Upload',
                        'route' => 'application/files',
                        'action' => 'upload',
                        'resource' => 'application/files',
                        'privilege' => 'upload',
                    ],
                ],
            ],
        ],
        
    ],
    'service_manager' => [
        'aliases' => [
            'unitedway-model-adapter' => 'timecard-model-adapter',
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
            'unitedway'               => __DIR__ . '/../view/application/united-way/internal.phtml',
            'layout/layout'           => __DIR__ . '/../view/layout/custom-layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
            'telestaff/config'        => __DIR__ . '/../view/application/telestaff/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];
