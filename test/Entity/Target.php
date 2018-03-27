<?php

namespace RefHelperTest\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="targets")
 */
class Target
{
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
