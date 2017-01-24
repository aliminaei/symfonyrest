<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Mmoreram\RSQueueBundle\Command\ConsumerCommand;
use Doctrine\ORM\EntityManager;
use rmccue\requests;
use \DateTime;

/**
 * Testing consumer command
 */
class PackageParserConsumerCommand extends ConsumerCommand
{
    protected $entityManager;
    protected $logger;
    protected $queueProducer;

    /**
     * Configuration method
     */
    protected function configure()
    {
        $this
            ->setName('package_crawler:process')
            ->setDescription('Package parser consumer command')
            ->addArgument("api_username")
            ->addArgument('api_token');
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

        //reading the github api username and token for github API call. Github has rate limit for API calls and the limit for anonymous users is 60 call per hour.
        //We can start this command by passing different api credentials to be able to build the database faster!
        $apiUsername = "";
        $apiToken = "";
        try
        {
            $apiUsername = $input->getArgument('api_username');
        }
        catch(\Exception $e)
        {
            $apiUsername = "";
        }

        try
        {
            $apiToken = $input->getArgument('api_token');
        }
        catch(\Exception $e)
        {
            $apiToken = "";
        }
        
        $packageName = $payload["package_name"];

        //Building the API URL for getting contributors
        $contributorsUrl = sprintf(
            "https://api.github.com/repos/%s/contributors", 
            $packageName);
        

        if ($apiUsername == "" || $apiToken == "")
        {
            $options = [];
        }
        else
        {
            $options = array('auth' => array($apiUsername, $apiToken));
        }
        $parseContributorsResponse = \Requests::get($contributorsUrl, [], $options);

        if ($parseContributorsResponse->status_code == 200)
        {
            $responseJson = (array)json_decode($parseContributorsResponse->body);
            $contributors = array();

            foreach ($responseJson as $contributor) 
            {
                try
                {
                    $contributors[] = $contributor->login;
                }
                catch (\Exception $e)
                {
                    $this->logger->error("Unknown error while parsing contributors for '".$packageName."' with url => '".$contributorsUrl."'");
                }
            }

            if (count($contributors) < 1)
            {
                $this->logger->warn('Package "'.$packageName.'" has no contributors!');
                return;
            }
            $this->logger->info("Adding package '".$packageName. "' to the persistor queue.");
            $this->addPackageToThePersistorQueue($packageName, $contributorsUrl, $contributors);
        }
        elseif ($parseContributorsResponse->status_code == 403)
        {
            if ($parseContributorsResponse->headers['X-RateLimit-Remaining'] == '0')
            {
                $rateLimitReset = $parseContributorsResponse->headers['X-RateLimit-Reset'];
                $resetTime = new DateTime("@$rateLimitReset");
                $currentTime = new DateTime();
                $interval = $resetTime->diff($currentTime);
                $waitTime = $interval->h * 3600 + $interval->i * 60 + $interval->s;
                
                $this->logger->info("Adding package '".$packageName. "' to the crawler queue again as we we reached our github api limit and we could not process this package.");
                $this->addPackageToTheCrawlerQueue($packageName);

                $this->logger->error("API rate limit exceeded! Have to wait for ".$waitTime. " seconds!");
                sleep($waitTime);
            }
        }
        else
        {
            $this->logger->error("Unknown error while parsing contributors for '".$packageName."' with url => '".$contributorsUrl."'");
        }
    }

    protected function addPackageToTheCrawlerQueue($packageName)
    {
        $data = [
            "package_name" => $packageName
        ];
        $this->queueProducer->produce("crawler", $data);
    }

    protected function addPackageToThePersistorQueue($packageName, $contributorsUrl, $contributors)
    {
        $data = [
            "package_name" => $packageName,
            "contributors_url" => $contributorsUrl,
            "contributors" => $contributors
        ];
        $this->queueProducer->produce("persistor", $data);
    }
}