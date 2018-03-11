# Reference Helper

Library to support polymorphic asssociations with Doctrine 2 in Zend Framework 3.

[![Build Status](https://travis-ci.org/j-schumann/ref-helper.svg?branch=master)](https://travis-ci.org/j-schumann/ref-helper) [![Coverage Status](https://coveralls.io/repos/github/j-schumann/ref-helper/badge.svg?branch=master)](https://coveralls.io/github/j-schumann/ref-helper?branch=master)

## Usage

1) Implement the ```HasReferenceInterface``` on your entity that should store the
reference to another object, use ```HasReferenceTrait``` for simplicity.
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
entity. Because ```${refName}Class``` is a separate column from the identifiers
we can easily filter for every object referencing an entity of class ```Target```:

```php
$em = $sm->get('Doctrine\ORM\EntityManager');
$list = $em->getRepository(Source::class)->findBy([
    'requiredClass' => Target::class,
]);
```

Using the ReferenceHelper you can now set and fetch references:
```php
use Vrok\References\Service\ReferenceHelper;

$refHelper = $services->get(ReferenceHelper::class);
$em = $sm->get('Doctrine\ORM\EntityManager');

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
for everey reference.