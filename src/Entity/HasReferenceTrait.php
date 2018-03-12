<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\References\Entity;

use Vrok\References\Exception;

/**
 * Allows the implementing entity to reference any given record without requiring an
 * explicit relation. Allows polymorphic references.
 *
 * You have to define $references in your entity class and add the
 * corresponding class/identifier fields, the nameing convention is
 * {refName}Class, {refName}Identifiers. This allows you to specify a reference
 * as (or part of) a primary key.
 *
 * The identifier field should use a string data type (instead of JSON/array)
 * because in the UnitOfWork Doctrine creates an idHash (when the column is used
 * as part of a key), if the column would return an array here the implode() in
 *  UOW would throw a notice: "Array to string conversion".
 */
trait HasReferenceTrait
{
    /**
     * List of all references the entity can store.
     * Notation is {string}name => {bool}required.
     *
     * @var array
     */
    //protected $references = [];

    /**
     * Get a list of references (by name) this entity can store.
     *
     * @return array
     */
    public function getReferenceNames() : array
    {
        return array_keys($this->references);
    }

    /**
     * Returns wether or not the reference given by name can be empty.
     *
     * @param string $name
     *
     * @return bool
     * @throws \Vrok\References\Exception\DomainException
     */
    public function isReferenceNullable(string $name) : bool
    {
        if (! isset($this->references[$name])) {
            throw new Exception\DomainException("Unknown reference name $name!");
        }

        return ! $this->references[$name];
    }

    /**
     * Retrieve the referenced object as [class, identifiers] for the
     * given reference name.
     * If no object is associated NULL is returned.
     *
     * @param string $name
     *
     * @return array|null.
     * @throws \Vrok\References\Exception\DomainException
     */
    public function getReference(string $name) : ?array
    {
        if (! isset($this->references[$name])) {
            throw new Exception\DomainException("Unknown reference name '$name'!");
        }

        $class = $this->{$name.'Class'};
        $identifiers = $this->{$name.'Identifiers'};

        if ($class && $identifiers) {
            return [
                'class'       => $class,
                'identifiers' => json_decode($identifiers, true),
            ];
        }

        return null;
    }

    /**
     * Stores the reference for the given name. className and identifiers can
     * be NULL for optional associations.
     *
     * @param string $name
     * @param string|null $className
     * @param array|null $identifiers
     * @throws \Vrok\References\Exception\BadMethodCallException when the parameters are incomplete
     * @throws \Vrok\References\Exception\DomainException when the reference does not exist or is not nullable
     */
    public function setReference(string $name, ?string $className, ?array $identifiers)
    {
        if (! isset($this->references[$name])) {
            throw new Exception\DomainException("Unknown reference name '$name'!");
        }

        if ($className xor ! empty($identifiers)) {
            throw new Exception\BadMethodCallException(
                'When setting a reference, both $className and $identifiers must be set or empty'
            );
        }

        if (! $this->isReferenceNullable($name) && ! $className) {
            throw new Exception\DomainException("Reference '$name' cannot be NULL!");
        }

        $this->{$name.'Class'} = $className ?: null;
        $this->{$name.'Identifiers'} = $identifiers
            ? json_encode($identifiers)
            : null;
    }

    /**
     * Returns a list of [column => value] to create DQL / add to a QueryBuilder
     * to filter for entities referencing a given class or concrete instance.
     *
     * @param string $name
     * @param string|null $className
     * @param array|null $identifiers
     * @return array
     * @throws Exception\DomainException when the requested reference does not exist
     * @throws Exception\BadMethodCallException when identifiers are given but no classname
     */
    public function getFilterValues(string $name, ?string $className, ?array $identifiers) : array
    {
        if (! isset($this->references[$name])) {
            throw new Exception\DomainException("Unknown reference name '$name'!");
        }

        // neither class nor identifiers given -> search for "reference not set"
        if (empty($className) && empty($identifiers)) {
            return [
                $name.'Class'       => null,
                $name.'Identifiers' => null,
            ];
        }

        // only class given -> search for "all object referencing this class"
        if (empty($identifiers)) {
            return [
                $name.'Class' => $className,
            ];
        }

        // only identifiers given -> forbidden
        if (empty($className)) {
            throw new Exception\BadMethodCallException(
                'When filtering by reference the classname must be set!'
            );
        }

        return [
            $name.'Class'       => $className,
            $name.'Identifiers' => json_encode($identifiers),
        ];
    }
}
