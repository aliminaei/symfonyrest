<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Contributor
 *
 * @ORM\Table(name="contributor")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ContributorRepository")
 */
class Contributor
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="text", unique=true)
     */
    private $name;

    /**
     * @var array
     *
     * @ORM\Column(name="packages", type="array", nullable=true)
     */
    private $packages;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Contributor
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set packages
     *
     * @param array $packages
     *
     * @return Contributor
     */
    public function setPackages($packages)
    {
        $this->packages = $packages;

        return $this;
    }

    /**
     * Get packages
     *
     * @return array
     */
    public function getPackages()
    {
        return $this->packages;
    }
}

