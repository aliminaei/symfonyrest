<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Mmoreram\RSQueueBundle\Command\ConsumerCommand;
use Doctrine\ORM\EntityManager;

/**
 * Testing consumer command
 */
class PackagePersistorConsumerCommand extends ConsumerCommand
{
    protected $entityManager;
    protected $logger;

    /**
     * Configuration method
     */
    protected function configure()
    {
        $this
            ->setName('package_crawler:persist')
            ->setDescription('Package parser consumer command');
        ;

        parent::configure();
    }

    /**
     * Relates queue name with appropiated method
     */
    public function define()
    {
        $this->entityManager = $this->getContainer()->get('doctrine')->getManager();
        $this->logger = $this->getContainer()->get('logger');
        $this->addQueue('persistor', 'persistPackage');
    }

    /**
     * If many queues are defined, as Redis respects order of queues, you can shuffle them
     * just overwritting method shuffleQueues() and returning true
     *
     * @return boolean Shuffle before passing to Gearman
     */
    public function shuffleQueues()
    {
        return true;
    }

    /**
     * Consume method with retrieved queue value
     *
     * @param InputInterface  $input   An InputInterface instance
     * @param OutputInterface $output  An OutputInterface instance
     * @param Mixed           $payload Data retrieved and unserialized from queue
     */
    protected function persistPackage(InputInterface $input, OutputInterface $output, $payload)
    {
        if (!is_array($payload))
        {
            $this->logger->error('Payload must be an array.');
            return;
        }

        if(!array_key_exists("package_name", $payload))
        {
            $this->logger->error('Package name is missing');
            return;
        }

        $packageName = $payload["package_name"];
        $repoUrl = $payload["repo_url"];
        $contributors = $payload["contributors"];

        $package = $this->savePackage($packageName, $repoUrl);

        $output->writeln(print_r($contributors, true));
    }

    protected function saveContributors($names, $package)
    {
        foreach ($names as $name) {
            $contributor = $this->saveContributor($name, $package);
        }
    }

    protected function saveContributor($name, $package)
    {
        $contributor = $this->entityManager->getRepository('AppBundle\Entity\Contributor')->findOneByName($name)
        if (!$contributor)
        {
            $contributor = new Contributor();
            $contributor->setName($name);
            $contributor->setPackages([$package]);
        }
        else
        {
            $packages = $contributor->getPackages();
            $packages[] = $package;
            $contributor->setPackages($packages);
        }
        return $contributor;
    }

    protected function savePackage($packageName, $repoUrl)
    {
        $package = $this->entityManager->getRepository('AppBundle\Entity\Package')->findOneByName($packageName)
        if (!$package)
        {
            $package = new Package();
            $package->setName($packageName);
            $package->setRepoUrl($repoUrl);
        }
        return $package;
    }
}