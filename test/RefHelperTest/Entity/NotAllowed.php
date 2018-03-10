<?php

namespace RefHelperTest\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vrok\References\Entity\HasReferenceInterface;
use Vrok\References\Entity\HasReferenceTrait;

/**
 * @ORM\Entity()
 * @ORM\Table(name="unused")
 */
class NotAllowed implements HasReferenceInterface
{
    use HasReferenceTrait;

    protected $references = [
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * Returns the objects ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Just to support some interfaces/hydrators.
     *
     * @param int $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }
}
