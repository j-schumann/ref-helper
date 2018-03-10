<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\References\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferenceHelperFactory implements FactoryInterface
{
    /**
     * Create a new ReferenceHelper.
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     *
     * @return ReferenceHelper
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $em = $container->get('Doctrine\ORM\EntityManager');
        if (! $em) {
            throw new ServiceNotCreatedException(
                'Doctrine\ORM\EntityManager could not be found when trying to create a ReferenceHelper'
            );
        }

        $helper = new ReferenceHelper($em);

        $configuration = $container->get('Config');

        if (isset($configuration['reference_helper'])) {
            $helper->setOptions($configuration['reference_helper']);
        }

        return $helper;
    }
}
