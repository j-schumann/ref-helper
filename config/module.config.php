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
        'allowed_targets' => [
            'RefHelperTest\Entity\Source' => [
                'nullable' => [
                    'RefHelperTest\Entity\Target',
                ],
            ],
        ],
    ],*/
// </editor-fold>
];
