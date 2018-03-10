<?php
return [
    'doctrine' => [
        'connection' => [
            'orm_default' => [
                'driverClass' => 'Doctrine\DBAL\Driver\PDOSqlite\Driver',
                'params'      => [
                    'host'   => null,
                    'dbname' => null,
                    'memory' => true,
                ],
            ],
        ],
        'configuration' => [
            'orm_default' => [
            ],
        ],
        'driver' => [
            'ref_entities' => [
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => [__DIR__.'/RefHelperTest/Entity'],
            ],
            'orm_default' => [
                'drivers' => [
                    'RefHelperTest\Entity' => 'ref_entities',
                ],
            ],
        ],
    ],
    'modules' => [
        'Zend\Router',
        'DoctrineModule',
        'DoctrineORMModule',
        'Vrok\References',
    ],
    'module_listener_options' => [
        'config_glob_paths'    => [
            __DIR__.'/TestConfig.php',
            __DIR__.'/TestConfig.local.php',
        ],
        'module_paths' => [
            'module',
            'vendor',
        ],
    ],
    'reference_helper' => [
        'allowed_targets' => [
            'RefHelperTest\Entity\Source' => [
                'nullable' => [
                    'RefHelperTest\Entity\Target',
                ],
                // do not list the "required" reference here, unlisted
                // references will accept any target class -> test this
            ],
        ],
    ],
];
