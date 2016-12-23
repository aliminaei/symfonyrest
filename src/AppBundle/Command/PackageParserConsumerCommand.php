<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Mmoreram\RSQueueBundle\Command\ConsumerCommand;
use Doctrine\ORM\EntityManager;

/**
 * Testing consumer command
 */
class PackageParserConsumerCommand extends ConsumerCommand
{
    protected $entityManager;
    protected $logger;
    protected $packagistAdapter;
    protected $githubAdapter;
    protected $queueProducer;

    /**
     * Configuration method
     */
    protected function configure()
    {
        $this
            ->setName('package_crawler:process')
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
        $this->packagistAdapter = $this->getContainer()->get("packagist_adapter");
        $this->githubAdapter = $this->getContainer()->get("github_adapter");
        $this->queueProducer = $this->getContainer()->get("rs_queue.producer");
        $this->addQueue('crawler', 'processPackage');
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
    protected function processPackage(InputInterface $input, OutputInterface $output, $payload)
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

        $repoUrl = $this->packagistAdapter->getPackageGithubURL($packageName);

        //Extracting repo name from the repo URL
        $repoName = str_replace('https://github.com/', '', $repoUrl);

        //Building the API URL fro getting contributors
        //I'm using my personal github account for a basic authentication as the request limits for anonymous callers are too low.
        $contributorsUrl = sprintf(
            "https://%s:%s@api.github.com/repos/%s/contributors", 
            $this->apiUsername, 
            $this->apiToken, 
            $repoName);

        $contributors = $this->githubAdapter->getContributors($contributorsUrl);

        if (count($contributors) < 1)
        {
            $this->logger->warn('Package "'.$packageName.'" has no contributors!');
            return;
        }

        $data = [
            "package_name" => $packageName,
            "contributors_url" => $contributorsUrl,
            "contributors" => $contributors
        ];
        $this->queueProducer->produce("persistor", $data);

        $output->writeln(print_r($contributors, true));
    }
}