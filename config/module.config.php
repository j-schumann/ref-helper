<?php

/**
 * RefHelper config.
 */
return [
// <editor-fold defaultstate="collapsed" desc="service_manager">
    'service_manager' => [
        'factories' => [
            'Vrok\References\Service\ReferenceHelper' => 'Vrok\References\Service\ReferenceHelperFactory',
        ],
    ],
// </editor-fold>
// <editor-fold defaultstate="collapsed" desc="reference_helper">
    /*'reference_helper' => [
        'entityClass' => [
            'referenceName' => [
                'targetClass1',
                'targetClass2',
            ],
        ],
    ],*/
// </editor-fold>
];
