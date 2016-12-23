<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Package
 *
 * @ORM\Table(name="package")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PackageRepository")
 */
class Package
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
     * @var string
     *
     * @ORM\Column(name="contributors_url", type="text", nullable=true)
     */
    private $contributorsUrl;

    /**
     * @var array
     *
     * @ORM\Column(name="contributors", type="array", nullable=true)
     */
    private $contributors;


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
     * @return Package
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
     * Set contributorsUrl
     *
     * @param string $contributorsUrl
     *
     * @return Package
     */
    public function setContributorsUrl($contributorsUrl)
    {
        $this->contributorsUrl = $contributorsUrl;

        return $this;
    }

    /**
     * Get contributorsUrl
     *
     * @return string
     */
    public function getContributorsUrl()
    {
        return $this->contributorsUrl;
    }

    /**
     * Set contributors
     *
     * @param array $contributors
     *
     * @return Package
     */
    public function setContributors($contributors)
    {
        $this->contributors = $contributors;

        return $this;
    }

    /**
     * Get contributors
     *
     * @return array
     */
    public function getContributors()
    {
        return $this->contributors;
    }
}

