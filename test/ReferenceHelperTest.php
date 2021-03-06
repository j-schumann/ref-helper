<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace RefHelperTest;

use PHPUnit\Framework\TestCase;
use RefHelperTest\Bootstrap;
use Vrok\References\Exception\DomainException;
use Vrok\References\Exception\InvalidArgumentException;
use Vrok\References\Exception\RuntimeException;
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

    public function testGetReferenceData()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $refData = $this->refHelper->getReferenceData($target);
        $this->assertEquals([
            'class'       => Entity\Target::class,
            'identifiers' => ['id' => $target->getId()],
        ], $refData);
    }

    public function testGetObject()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $refData = $this->refHelper->getReferenceData($target);

        // clear to refetch from db
        $em->clear();

        $object = $this->refHelper->getObject($refData);
        $this->assertInstanceOf(Entity\Target::class, $object);
        $this->assertEquals($target->getId(), $object->getId());
    }

    public function testGetObjectChecksData()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"class" and "identifiers" must be set in the reference data!');
        $this->refHelper->getObject(['class' => '', 'identifiers' => []]);
    }

    public function testCanSetAndGetReference()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $source = new Entity\Source();
        $this->assertNull($this->refHelper->getReferencedObject($source, 'required'));

        $this->refHelper->setReferencedObject($source, 'required', $target);
        $this->assertInstanceOf(
            Entity\Target::class,
            $this->refHelper->getReferencedObject($source, 'required')
        );
    }

    public function testCanPersistReference()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();
        $targetId = $target->getId();

        $source = new Entity\Source();
        $this->refHelper->setReferencedObject($source, 'required', $target);
        $em->persist($source);
        $em->flush();
        $sourceId = $source->getId();

        // clear to refetch from db
        $em->clear();

        $dbSource = $em->getRepository(Entity\Source::class)->find($sourceId);

        $dbTarget = $this->refHelper->getReferencedObject($dbSource, 'required');
        $this->assertInstanceOf(Entity\Target::class, $dbTarget);
        $this->assertEquals($targetId, $dbTarget->getId());
    }

    public function testCanSetNullReference()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $source = new Entity\Source();

        $this->refHelper->setReferencedObject($source, 'nullable', $target);
        $this->assertInstanceOf(
            Entity\Target::class,
            $this->refHelper->getReferencedObject($source, 'nullable')
        );

        $this->refHelper->setReferencedObject($source, 'nullable', null);
        $this->assertNull($this->refHelper->getReferencedObject($source, 'nullable'));
    }

    public function testRefWithoutIdentifiersDenied()
    {
        $target = new Entity\Target();
        $source = new Entity\Source();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target object has no identifiers, must be persisted first!');
        $this->refHelper->setReferencedObject($source, 'nullable', $target);
    }

    public function testPreventForbiddenTarget()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

        $target = new Entity\NotAllowed();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $source = new Entity\Source();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is not allowed for reference');
        $this->refHelper->setReferencedObject($source, 'nullable', $target);
    }

    public function testGetNullFilterValues()
    {
        $filter = $this->refHelper->getEntityFilterData(
            Entity\Source::class,
            'nullable',
            null
        );

        $this->assertEquals([
            'nullableClass'       => null,
            'nullableIdentifiers' => null,
        ], $filter);
    }

    public function testGetClassFilterValues()
    {
        $filter = $this->refHelper->getClassFilterData(
            Entity\Source::class,
            'nullable',
            Entity\Target::class
        );

        $this->assertEquals([
            'nullableClass' => Entity\Target::class,
        ], $filter);
    }

    public function testGetClassFilterWithUnsupportedSource()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('does not implement the HasReferenceInterface');
        $this->refHelper->getClassFilterData(
            Entity\Target::class,
            'nullable',
            Entity\Target::class
        );
    }

    public function testGetClassFilterWithUnsupportedTarget()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is not allowed for reference');
        $this->refHelper->getClassFilterData(
            Entity\Source::class,
            'nullable',
            Entity\NotAllowed::class
        );
    }

    public function testGetEntityFilterWithUnsupportedTarget()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        /* @var $em \Doctrine\ORM\EntityManagerInterface */

        $target = new Entity\NotAllowed();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is not allowed for reference');
        $this->refHelper->getEntityFilterData(
            Entity\Source::class,
            'nullable',
            $target
        );
    }

    public function testGetEntityFilterWithUnsupportedSource()
    {
        $target = new Entity\Target();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('does not implement the HasReferenceInterface!');
        $this->refHelper->getEntityFilterData(
            Entity\Target::class,
            'nullable',
            $target
        );
    }

    public function testGetEntityFilterValues()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        $target = new Entity\Target();
        $em->persist($target);
        // flush to set identifiers
        $em->flush();

        $filter = $this->refHelper->getEntityFilterData(
            Entity\Source::class,
            'nullable',
            $target
        );

        $this->assertEquals([
            'nullableClass'       => Entity\Target::class,
            'nullableIdentifiers' => '{"id":'.$target->getId().'}',
        ], $filter);
    }

    public function testGetFilterValuesInvalidTarget()
    {
        $sm = Bootstrap::getServiceManager();
        $em = $sm->get('Doctrine\ORM\EntityManager');
        $na = new Entity\NotAllowed();
        $em->persist($na);
        // flush to set identifiers
        $em->flush();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is not allowed for reference');
        $this->refHelper->getEntityFilterData(
            Entity\Source::class,
            'nullable',
            $na
        );
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
