<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace RefHelperTest;

use PHPUnit\Framework\TestCase;
use RefHelperTest\Bootstrap;
use Vrok\References\Service\ReferenceHelper;

class ModuleTest extends TestCase
{
    /**
     * Test service manager config + factory
     */
    public function testCanCreateHelper()
    {
        $serviceManager = Bootstrap::getServiceManager();
        $refHelper = $serviceManager->get(ReferenceHelper::class);
        $this->assertInstanceOf(ReferenceHelper::class, $refHelper);
    }

    /**
     * Test configuration is injected via factory.
     */
    public function testHelperHasConfig()
    {
        $serviceManager = Bootstrap::getServiceManager();
        $refHelper = $serviceManager->get(ReferenceHelper::class);
        /* @var $refHelper ReferenceHelper */

        $targets = $refHelper->getAllowedTargets(Entity\Source::class, 'nullable');
        $this->assertEquals([Entity\Target::class], $targets);
    }
}
