# Reference Helper

Library to support polymorphic asssociations with Doctrine 2 in Zend Framework 3.

[![Build Status](https://travis-ci.org/j-schumann/ref-helper.svg?branch=master)](https://travis-ci.org/j-schumann/ref-helper) [![Coverage Status](https://coveralls.io/repos/github/j-schumann/ref-helper/badge.svg?branch=master)](https://coveralls.io/github/j-schumann/ref-helper?branch=master)

Many applications have polymorphic relations where we don't know (and don't want
to know in advance) which entities may be assigned to which (e.g. which entity
is owned by which). For example a bank account may be owned by an user or an
organization, a validation may belong to a bank account or an user.

To support this loose coupling we store the class of the referenced entity and
the identifiers forming the primary key (e.g. autoincrement or composite keys)
in the referencing entity in separate columns.
The referencing entities should be kept simple POPOs, they need to implement
```Vrok\References\Entity\HasReferenceInterface```, which is implemented in
```Vrok\References\Entity\HasReferenceTrait``` for re-use. The interface and trait
support multiple references on one entity, for example to reference a creator
and an owner.

The ```Vrok\References\Service\ReferenceHelper``` builds on this principle by
providing functions to set and retrieve the referenced entities. The helper
also has the ability to restrict references to only one or some targeted classes.

## Usage
### Entity preparation

1) Implement the ```HasReferenceInterface``` on your entity that should store the
reference to another object, use ```HasReferenceTrait``` for simplicity
2) Add one or more references to the entity by defining the ```$references```
property and ```${refName}Class```, ```${refName}Identifiers``` properties for
each reference

```php
use Doctrine\ORM\Mapping as ORM;
use Vrok\References\Entity\HasReferenceInterface;
use Vrok\References\Entity\HasReferenceTrait;

/**
 * @ORM\Entity()
 * @ORM\Table(name="sources")
 */
class Source implements HasReferenceInterface
{
    use HasReferenceTrait;

    /**
     * @var array ['refName' => (bool)required, ...]
     */
    protected $references = [
        'nullable' => false,
        'required' => true,
    ];

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $requiredClass;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $requiredIdentifiers;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $nullableClass;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $nullableIdentifiers;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId()
    {
        return $this->id;
    }
}
```
Now ```nullable``` and ```required``` can store references to any other Doctrine
entity.

### Set & get references with the ReferenceHelper

Using the ```ReferenceHelper``` you can now set and fetch references:
```php
use Vrok\References\Service\ReferenceHelper;

$refHelper = $services->get(ReferenceHelper::class);
$em = $services->get('Doctrine\ORM\EntityManager');

// the referenced object must have its identifiers (primary key columns) set,
// e.g. be already persisted when using autoincrement ID
$target = $em->getRepository(Target::class)->find(1);

$source = new Source();
$refHelper->setReferencedObject($source, 'required', $target);
$em->persist($source);
$em->flush();
$sourceId = $source->getId();

// later:
$loaded = $em->getRepository(Source::class)->find(sourceId);
$refObject = $refHelper->getReferencedObject($source, 'required');
// $refObject == $target
```

### Restrict reference target classes

To restrict the ReferenceHelper to allow only one or some classes as targets
add something like this to your config:
```php
    'reference_helper' => [
        'allowed_targets' => [
            'Entity\Source' => [
                'nullable' => [
                    'Entity\Target',
                ],
            ],
        ],
    ],
```
Now for the reference ```nullable``` only instances of ```Entity\Target```
(and child classes) are accepted.
Every other reference on ```Entity\Source``` will still allow every target class.
Entity classes not listed in ```allowed_targets``` accept every target
for every reference.

### Querying for entities

Because ```${refName}Class``` is a separate column from the identifiers
we can easily filter for every object referencing an entity of class ```Target```:
```php
$em = $services->get('Doctrine\ORM\EntityManager');
$list = $em->getRepository(Entity\Source::class)->findBy([
    'requiredClass' => Entity\Target::class,
]);
```

We can also use the ```ReferenceHelper``` to get the values to filter for usage
in a queryBuilder etc:
```php
use Vrok\References\Service\ReferenceHelper;

$refHelper = $services->get(ReferenceHelper::class);
$em = $services->get('Doctrine\ORM\EntityManager');

$values = $refHelper->getClassFilterData(Entity\Source::class, 'required', Entity\Target::class);
// $values == ['requiredClass' => 'Entity\Target']

$target = $em->getRepository(Entity\Target::class)->find(1);
$values = $refHelper->getEntityFilterData(Entity\Source::class, 'required', $target);
// $values == ['requiredClass' => 'Entity\Target', 'requiredIdentifiers' => '{"id":1}']

$values = $refHelper->getEntityFilterData(Entity\Source::class, 'required', null);
// $values == ['requiredClass' => null, 'requiredIdentifiers' => null]

// add filter to a QueryBuilder:
$qb = $em->getRepository(Entity\Source::class)->createQueryBuilder('s');
$whereClause = [];
foreach ($values as $column => $value) {
    $whereClause[] = "s.$column = :$column';
    $qb->setParameter($column, $value);
}
$qb->andWhere(implode(' AND ', $whereClause));
```
The ReferenceHelper can not add the conditions to a given QueryBuilder for you
because it would have to know the alias to use (e.g. if you are using joins) and
wether to to combine with previous conditions using "AND" or "OR". Maybe you
even want to search for multiple conditions at once:
```php
SELECT * FROM Entity\Source s WHERE s.deleted = 0 AND (s.requiredClass = 'Entity\Target'
  OR s.requiredClass = 'Entity\OtherTarget')
```