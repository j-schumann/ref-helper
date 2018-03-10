<?php

/**
 * @copyright   (c) 2014-18, Vrok
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @author      Jakob Schumann <schumann@vrok.de>
 */

namespace Vrok\References\Service;

use Doctrine\ORM\EntityManagerInterface;
use Vrok\References\Entity;
use Vrok\References\Exception;

/**
 * ZF2|3 & Doctrine2 helper to handly polymorphic associations between entities
 * that are not predetermined by inheritance and not handled by the
 * ResolveTargetEntityListener as he can only resolve to one entity.
 */
class ReferenceHelper
{
    /**
     * Hash of all registered entities that may have a reference and the
     * corresponding possibly referenced classes:
     * [
     *   entityClass => [
     *     referenceName => [
     *       targetClass1,
     *       ...
     *     ],
     *     ...
     *   ],
     *   ...
     * ]
     *
     * @var array
     */
    protected $allowedTargets = [];

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager = null;

    /**
     * Class constructor - stores the dependency.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    /**
     * Retrieve the associated object for the reference given by name.
     * This cannot be done in the entity itself as it has no way to load
     * another entity and we don't want to inject the entityManager.
     *
     * @param \Vrok\References\Entity\HasReferenceInterface $object
     * @param string $refName
     * @return object|null
     * @throws \Vrok\References\Exception\DomainException
     */
    public function getReferencedObject(
        Entity\HasReferenceInterface $object,
        string $refName
    ) /*: ?object*/ {
        $ref = $object->getReference($refName);
        if (! $ref) {
            return null;
        }

        $repo = $this->entityManager->getRepository($ref['class']);
        return $repo->find($ref['identifiers']);
    }

    /**
     * Set the association given by name with the $refObject in $object.
     * This cannot be done in the entity itself as it has no way to determine
     * the refObjects identifiersand we don't want to inject the entityManager.
     *
     * @param \Vrok\References\Entity\HasReferenceInterface $object
     * @param string $refName
     * @param object|null $refObject
     * @throws \Vrok\References\Exception\DomainException when the reference
     *     does not exist or is not nullable
     * @throws \Vrok\References\Exception\InvalidArgumentException when the
     *     refObject has no identifiers or is not allowed for the reference
     */
    public function setReferencedObject(
        Entity\HasReferenceInterface $object,
        string $refName,
        /*?object*/ $refObject
    ) {
        if (! $refObject) {
            $object->setReference($refName, null, null);
            return;
        }

        if (! $this->isAllowedTarget($object, $refName, $refObject)) {
            $target = get_class($refObject);
            $class = get_class($object);
            throw new Exception\InvalidArgumentException(
                "Class $target is not allowed for reference $refName on entity $class!"
            );
        }

        $refClass = get_class($refObject);
        $refMeta = $this->entityManager->getClassMetadata($refClass);
        $identifiers = $refMeta->getIdentifierValues($refObject);
        if (! count($identifiers)) {
            throw new Exception\InvalidArgumentException(
                'Target object has no identifiers, must be persisted first!'
            );
        }

        $object->setReference($refName, $refClass, $identifiers);
    }

    /**
     * Checks if the given object is allowed for the given entity and reference.
     * This does _not_ check if the reference exists on the entity!
     *
     * @param Entity\HasReferenceInterface $entity
     * @param string $refName
     * @param object $target
     *
     * @return bool
     */
    public function isAllowedTarget(
        Entity\HasReferenceInterface $entity,
        string $refName,
        /*object*/ $target
    ) : bool {
        $refFound = false;

        foreach ($this->allowedTargets as $entityClass => $references) {
            if (! $entity instanceof $entityClass
                && ! is_subclass_of($entity, $entityClass)
            ) {
                continue;
            }

            if (! isset($references[$refName])) {
                continue;
            }

            // remember that there was a matching source with the requested
            // association, regardless if the target matched or not
            $refFound = true;

            foreach ($references[$refName] as $targetClass) {
                if ($target instanceof $targetClass
                    || is_subclass_of($target, $targetClass)
                ) {
                    return true;
                }
            }
        }

        // when the user configured a reference for a source and no target
        // matched we return false, else we assume no restrictions apply (e.g.
        // if the source or the refName is not listed) and return true
        return ! $refFound;
    }

    /**
     * Retrieve all target classes that are allowed for the given entity and
     * reference. Does not include the child classes of the targetClass.
     * An empty array is returned if either the user allowed no target classes
     * (probably unintended), the reference or the source class is not listed
     * (in both cases every target class is allowed).
     * This does _not_ check if the reference exists on the entity!
     *
     * @param string $entityClass
     * @param string $refName
     *
     * @return array
     */
    public function getAllowedTargets($entityClass, $refName) : array
    {
        return $this->allowedTargets[$entityClass][$refName] ?? [];
    }

    /**
     * Adds an target class for the given entity and the given reference.
     *
     * @param string $entityClass
     * @param string $refName
     * @param string $targetClass
     */
    public function addAllowedTarget($entityClass, $refName, $targetClass)
    {
        if (! isset($this->allowedTargets[$entityClass])) {
            $this->allowedTargets[$entityClass] = [];
        }

        if (! isset($this->allowedTargets[$entityClass][$refName])) {
            $this->allowedTargets[$entityClass][$refName] = [];
        }

        $this->allowedTargets[$entityClass][$refName][] = $targetClass;
    }

    /**
     * Sets multiple entity=>owner relations at once.
     *
     * @param array $allowedTargets [entity => [refName => [target1, ...], ...], ...]
     */
    public function addAllowedTargets(array $allowedTargets)
    {
        $this->allowedTargets = array_merge($this->allowedTargets, $allowedTargets);
    }

    /**
     * Sets all configuration options for this service.
     *
     * @param array $config
     */
    public function setOptions(array $config)
    {
        if (isset($config['allowed_targets'])) {
            $this->allowedTargets = $config['allowed_targets'];
        }
    }
}
