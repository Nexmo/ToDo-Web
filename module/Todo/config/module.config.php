<?php
return [
    'router' => [
        'routes' => [
            'auth' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route' => '/auth[/:action]',
                    'defaults' => [
                        'controller' => 'Todo\AuthController',
                        'action' => 'signup'
                    ]
                ],
                'may_terminate' => true,
            ],
            'app' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route' => '/[app][/:action]',
                    'defaults' => [
                        'controller' => 'Todo\AppController',
                        'action' => 'index'
                    ]
                ],
                'may_terminate' => true,
            ]
        ]
    ],
    'controllers' => [
        'invokables' => [
            'Todo\AuthController' => 'Todo\AuthController',
            'Todo\AppController' => 'Todo\AppController',
            'Todo\SetupController' => 'Todo\SetupController'
        ]
    ],
    'view_manager' => [
        'template_map' => [
            'todo/auth/signin' => __DIR__ . '/../view/signin.phtml',
            'todo/auth/signup' => __DIR__ . '/../view/signup.phtml',
            'todo/auth/forgot'   => __DIR__ . '/../view/forgot.phtml',
            'todo/app/index'   => __DIR__ . '/../view/index.phtml',
        ]
    ],
    'console' => [
        'router' => [
            'routes' => [
                'setup' => [
                    'options' => [
                        'route'    => 'setup [parse|config]:action',
                        'defaults' => [
                            'controller' => 'Todo\SetupController',
                        ]
                    ]
                ]
            ]
        ]
    ]
];
