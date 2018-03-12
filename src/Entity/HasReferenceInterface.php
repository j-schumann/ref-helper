<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\References\Entity;

/**
 * General interface for entities that support polymorphic references.
 *
 * An object could have more than one reference, e.g. a source and an owner, so
 * we use names to identify those.
 * We also use two columns for each reference as we want to be able to easily
 * filter by class: Give me all objects that reference an object of class X.
 *
 * The reference getter&setter do not accept/return objects as this would
 * require an instance of the entity manager to load the entity or get its
 * identifiers, we want to keep the entity simple.
 */
interface HasReferenceInterface
{
    /**
     * Get a list of references (by name) this entity can store.
     *
     * @return array
     */
    public function getReferenceNames() : array;

    /**
     * Returns wether or not the reference given by name can be empty.
     *
     * @param string $name
     *
     * @return bool
     */
    public function isReferenceNullable(string $name) : bool;

    /**
     * Retrieve the referenced object as [class, identifiers] for the
     * given reference name.
     * If no object is associated NULL is returned.
     *
     * @param string $name
     *
     * @return array|null.
     */
    public function getReference(string $name) : ?array;

    /**
     * Stores the reference for the given name. className and identifiers can
     * be NULL for optional associations.
     *
     * @param string $name
     * @param string|null $className
     * @param array|null $identifiers
     */
    public function setReference(string $name, ?string $className, ?array $identifiers);

    /**
     * Returns a list of [column => value] to create DQL / add to a QueryBuilder
     * to filter for entities referencing a given class or concrete instance.
     *
     * @param string $name
     * @param string|null $className
     * @param array|null $identifiers
     *
     * @return array
     */
    public function getFilterValues(string $name, ?string $className, ?array $identifiers) : array;
}
