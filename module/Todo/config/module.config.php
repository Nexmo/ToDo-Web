<?php
return [
    'router' => [
        'routes' => [
            'auth' => [
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route' => '/auth[/:action]',
                    'defaults' => [
                        'controller' => 'Todo\Controller\Auth',
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
                        'controller' => 'Todo\Controller\App',
                        'action' => 'index'
                    ]
                ],
                'may_terminate' => true,
            ]
        ]
    ],
    'controllers' => [
        'invokables' => [
            'Todo\Controller\Auth' => 'Todo\Controller\AuthController',
            'Todo\Controller\App' => 'Todo\Controller\AppController'
        ]
    ],
    'view_manager' => [
        'template_map' => [
            'todo/auth/signin' => __DIR__ . '/../view/signin.phtml',
            'todo/auth/signup' => __DIR__ . '/../view/signup.phtml',
            'todo/app/index'   => __DIR__ . '/../view/index.phtml'
        ]
    ]
];
