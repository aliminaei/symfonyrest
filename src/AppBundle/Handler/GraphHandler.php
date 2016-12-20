<?php

namespace AppBundle\Handler;

use Symfony\Component\DependencyInjection\ContainerInterface;

use AppBundle\Adapter\PackagistAdapter;
use AppBundle\Adapter\GithubAdapter;
use AppBundle\Adapter\ArangoDBAdapter;
use Doctrine\ORM\EntityManager;
use AppBundle\Entity\Package;
use AppBundle\Entity\Contributor;

class GraphHandler
{
    protected $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Retrives the shortest path between the two contributors.
     * 
     * 
     * @param  string $user1  -  The first contributor's github username
     * @param  string $user2  -  The second contributor's github username
     *
     * @return the shortest path in json format.
     */
    public function getShortestPath($user1, $user2)
    {
        return "Not Connected";
    }


    /**
     * Retrives the list of top users who might want to contribute to the given package.
     * Users are ranked based on their number of contributions to other packages.
     * 
     * 
     * @param  string $package  -  The name of the package.
     *
     * @return the shortest path in json format.
     */
    public function getPotentialContributors($package)
    {

    }
}