<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace RefHelperTest;

use PHPUnit\Framework\TestCase;
use RefHelperTest\Bootstrap;
use Vrok\References\Exception;
use Vrok\References\Service\ReferenceHelper;

class ReferenceHelperTest extends TestCase
{
    /**
     * @var ReferenceHelper
     */
    protected $refHelper = null;

    protected function setUp()
    {
        $serviceManager  = Bootstrap::getServiceManager();
        $this->refHelper = $serviceManager->get(ReferenceHelper::class);
    }

    public function testGetAllowedTargets()
    {
        $targets1 = $this->refHelper->getAllowedTargets(Entity\Source::class, 'nullable');
        $this->assertEquals([Entity\Target::class], $targets1);

        $targets2 = $this->refHelper->getAllowedTargets(Entity\Source::class, 'required');
        $this->assertCount(0, $targets2);

        $targets3 = $this->refHelper->getAllowedTargets(Entity\Target::class, 'unused');
        $this->assertCount(0, $targets3);
    }

    public function testAddAllowedTarget()
    {
        $this->refHelper->addAllowedTarget('NewSource', 'NewRef', 'NewTarget');
        $res = $this->refHelper->getAllowedTargets('NewSource', 'NewRef');
        $this->assertEquals(['NewTarget'], $res);
    }

    public function testAddAllowedTargets()
    {
        $this->refHelper->addAllowedTargets([
            's1' => ['s1ref' => ['t1']],
            's2' => ['s2ref' => ['t2']],
        ]);
        $res1 = $this->refHelper->getAllowedTargets('s1', 's1ref');
        $this->assertEquals(['t1'], $res1);
        $res2 = $this->refHelper->getAllowedTargets('s2', 's2ref');
        $this->assertEquals(['t2'], $res2);
    }

    /**
     * Test that configured allowed targets and their child classes are really
     * accepted.
     */
    public function testListedTargetIsAllowed()
    {
        $source = new Entity\Source();
        $target = new Entity\Target();

        $targetAllowed = $this->refHelper->isAllowedTarget($source, 'nullable', $target);
        $this->assertTrue($targetAllowed);

        $tc = new Entity\TargetChild();
        $tcAllowed = $this->refHelper->isAllowedTarget($source, 'nullable', $tc);
        $this->assertTrue($tcAllowed);

        $sc = new Entity\SourceChild();
        $scAllowed = $this->refHelper->isAllowedTarget($sc, 'nullable', $tc);
        $this->assertTrue($scAllowed);
    }

    /**
     * Test that target classes not listed (also not their parent classes) in a
     * reference are not accepted.
     */
    public function testUnlistedTargetIsForbidden()
    {
        $source = new Entity\Source();
        $forbidden = $this->refHelper->isAllowedTarget($source, 'nullable', $source);
        $this->assertFalse($forbidden);

        $sc = new Entity\SourceChild();
        $forbiddenSc = $this->refHelper->isAllowedTarget($source, 'nullable', $sc);
        $this->assertFalse($forbiddenSc);
    }

    /**
     * Test that references not listed in the config under allowed_targets for
     * the source class allow every target class.
     */
    public function testUnlistedReferenceIsAllowed()
    {
        $source = new Entity\Source();
        $allowed1 = $this->refHelper->isAllowedTarget($source, 'required', $source);
        $this->assertTrue($allowed1);

        $target = new Entity\Target();
        $allowed2 = $this->refHelper->isAllowedTarget($source, 'required', $target);
        $this->assertTrue($allowed2);
    }

    /**
     * Test that entity classes that are not listed in the config allow every
     * reference with every target.
     */
    public function testUnlistedSourceIsAllowed()
    {
        $notAllowed = new Entity\NotAllowed();
        $source = new Entity\Source();
        $target = new Entity\Target();

        $unlistedAllowed1 = $this->refHelper->isAllowedTarget($notAllowed, 'undefined', $source);
        $this->assertTrue($unlistedAllowed1);

        $unlistedAllowed2 = $this->refHelper->isAllowedTarget($notAllowed, 'undefined', $target);
        $this->assertTrue($unlistedAllowed2);
    }

    public function testCanSetAndGetReference()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */
        $refHelper = $sm->get(ReferenceHelper::class);
        /* @var $refHelper ReferenceHelper */

        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $source = new Entity\Source();
        $this->assertNull($refHelper->getReferencedObject($source, 'required'));

        $refHelper->setReferencedObject($source, 'required', $target);
        $this->assertInstanceOf(
            Entity\Target::class,
            $refHelper->getReferencedObject($source, 'required')
        );
    }

    public function testCanPersistReference()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */
        $refHelper = $sm->get(ReferenceHelper::class);
        /* @var $refHelper ReferenceHelper */

        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();
        $targetId = $target->getId();

        $source = new Entity\Source();
        $refHelper->setReferencedObject($source, 'required', $target);
        $em->persist($source);
        $em->flush();
        $sourceId = $source->getId();

        // clear to refetch from db
        $em->clear();

        $dbSource = $em->getRepository(Entity\Source::class)->find($sourceId);

        $dbTarget = $refHelper->getReferencedObject($dbSource, 'required');
        $this->assertInstanceOf(Entity\Target::class, $dbTarget);
        $this->assertEquals($targetId, $dbTarget->getId());
    }

    public function testCanSetNullReference()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */
        $refHelper = $sm->get(ReferenceHelper::class);
        /* @var $refHelper ReferenceHelper */

        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $source = new Entity\Source();

        $refHelper->setReferencedObject($source, 'nullable', $target);
        $this->assertInstanceOf(
            Entity\Target::class,
            $refHelper->getReferencedObject($source, 'nullable')
        );

        $refHelper->setReferencedObject($source, 'nullable', null);
        $this->assertNull($refHelper->getReferencedObject($source, 'nullable'));
    }

    public function testRefWithoutIdentifiersDenied()
    {
        $sm = Bootstrap::getServiceManager();
        $refHelper = $sm->get(ReferenceHelper::class);
        /* @var $refHelper ReferenceHelper */

        $target = new Entity\Target();
        $source = new Entity\Source();

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Target object has no identifiers, must be persisted first!');
        $refHelper->setReferencedObject($source, 'nullable', $target);
    }

    public function testPreventForbiddenTarget()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */
        $refHelper = $sm->get(ReferenceHelper::class);
        /* @var $refHelper ReferenceHelper */

        $target = new Entity\NotAllowed();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $source = new Entity\Source();

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not allowed for reference');
        $refHelper->setReferencedObject($source, 'nullable', $target);
    }

    /**
     * MUST BE THE LAST TEST, overwrites the config.
     */
    public function testSetOptions()
    {
        $this->refHelper->setOptions(['allowed_targets' => [
            'os' => ['oref' => ['ot']],
        ]]);

        $res1 = $this->refHelper->getAllowedTargets('os', 'oref');
        $this->assertEquals(['ot'], $res1);

        // previous config is overwritten
        $res2 = $this->refHelper->getAllowedTargets('Source', 'nullable');
        $this->assertEquals([], $res2);
    }
}
