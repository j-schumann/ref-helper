<?php

/**
 * @copyright   (c) 2018, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace RefHelperTest;

use ReflectionObject;
use PHPUnit\Framework\TestCase;
use Vrok\References\Exception\DomainException;
use Vrok\References\Exception\InvalidArgumentException;

class HasReferenceTest extends TestCase
{
    public function testGetReferenceNames()
    {
        $source = new Entity\Source();
        $res = $source->getReferenceNames();
        $this->assertEquals(['nullable', 'required'], $res);
    }

    public function testIsReferenceNullable()
    {
        $source = new Entity\Source();

        $this->assertTrue($source->isReferenceNullable('nullable'));
        $this->assertFalse($source->isReferenceNullable('required'));

        $this->expectException(DomainException::class);
        $source->isReferenceNullable('undefined');
    }

    public function testGetFilterValuesChecksReference()
    {
        $source = new Entity\Source();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown reference name');
        $source->getFilterValues('unknown', null, ['id' => 1]);
    }

    public function testGetNullFilterValues()
    {
        $source = new Entity\Source();
        $filter = $source->getFilterValues('nullable', null, null);

        $this->assertEquals([
            'nullableClass'       => null,
            'nullableIdentifiers' => null,
        ], $filter);
    }

    public function testGetClassFilterValues()
    {
        $source = new Entity\Source();
        $filter = $source->getFilterValues('nullable', Entity\Target::class, null);

        $this->assertEquals([
            'nullableClass' => Entity\Target::class,
        ], $filter);
    }

    public function testGetEntityFilterValues()
    {
        $source = new Entity\Source();
        $filter = $source->getFilterValues(
            'nullable',
            Entity\Target::class,
            ['id' => 1]
        );

        $this->assertEquals([
            'nullableClass'       => Entity\Target::class,
            'nullableIdentifiers' => '{"id":1}',
        ], $filter);
    }

    public function testGetIdentifierFilterValues()
    {
        $source = new Entity\Source();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'When filtering by reference the classname must be set!'
        );
        $source->getFilterValues('nullable', null, ['id' => 1]);
    }

    public function testSetNullReferenceWorks()
    {
        $source = new Entity\Source();
        $source->setReference('nullable', null, null);

        $refl = new ReflectionObject($source);

        $nullableClassProp = $refl->getProperty('nullableClass');
        $nullableClassProp->setAccessible(true);
        $this->assertNull($nullableClassProp->getValue($source));

        $nullableIdentifierProp = $refl->getProperty('nullableIdentifiers');
        $nullableIdentifierProp->setAccessible(true);
        $this->assertNull($nullableIdentifierProp->getValue($source));
    }

    public function testSetReferenceWorks()
    {
        $source = new Entity\Source();
        $source->setReference('nullable', Entity\Target::class, ['id' => 1]);

        $refl = new ReflectionObject($source);

        $nullableClassProp = $refl->getProperty('nullableClass');
        $nullableClassProp->setAccessible(true);
        $className = $nullableClassProp->getValue($source);
        $this->assertEquals(Entity\Target::class, $className);

        $nullableIdentifierProp = $refl->getProperty('nullableIdentifiers');
        $nullableIdentifierProp->setAccessible(true);
        $identifiers = $nullableIdentifierProp->getValue($source);
        $this->assertEquals('{"id":1}', $identifiers);
    }

    public function testSetUnknownReferenceFails()
    {
        $source = new Entity\Source();

        $this->expectException(DomainException::class);
        $source->setReference('unknown', Entity\Target::class, ['id' => 1]);
    }

    public function testReferenceClassRequired()
    {
        $source = new Entity\Source();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'When setting a reference, both $className and $identifiers must be set or empty'
        );
        $source->setReference('required', '', ['id' => 1]);
    }

    public function testReferenceIdentifierRequired()
    {
        $source = new Entity\Source();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'When setting a reference, both $className and $identifiers must be set or empty'
        );
        $source->setReference('required', Entity\Target::class, null);
    }

    public function testRequiredReference()
    {
        $source = new Entity\Source();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Reference 'required' cannot be NULL!");
        $source->setReference('required', null, null);
    }

    public function testGetReferenceWorks()
    {
        $source = new Entity\Source();
        $source->setReference('nullable', Entity\Target::class, ['id' => 1]);

        $ref = $source->getReference('nullable');
        $this->assertEquals(
            ['class' => Entity\Target::class, 'identifiers' => ['id' => 1]],
            $ref
        );

        $ref2 = $source->getReference('required');
        $this->assertEquals(
            null,
            $ref2
        );
    }

    public function testGetUnknownReferenceFails()
    {
        $source = new Entity\Source();

        $this->expectException(DomainException::class);
        $source->GetReference('unknown');
    }
}
