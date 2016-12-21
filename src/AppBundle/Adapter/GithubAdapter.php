<?php
    
namespace AppBundle\Adapter;

use Symfony\Component\DependencyInjection\ContainerInterface;

use AppBundle\Handler\HttpHandler;

class GithubAdapter
{

    private $apiUsername;    
    private $apiToken;    

    public function __construct($apiUsername, $apiToken)
    {
        $this->apiUsername = $apiUsername;
        $this->apiToken = $apiToken;
    }

    /**
     * Get all the github users contributed to the given github repository
     *
     * Using github API - https://developer.github.com/v3/
     *
     * @return array - array of string containing all github usernames contributed to the package
     */
    public function getContributors($repoURL)
    {
        $contributors = array();

        //Extracting repo name from the repo URL
        $repoName = str_replace('https://github.com/', '', $repoURL);

        //Building the API URL fro getting contributors
        //I'm using my personal github account for a basic authentication as the request limits for anonymous callers are too low.
        $contributorsURL = sprintf(
            "https://%s:%s@api.github.com/repos/%s/contributors", 
            $this->apiUsername, 
            $this->apiToken, 
            $repoName);

        // echo $contributorsURL."\n";

        $response = HttpHandler::HttpGetRequest($contributorsURL);
        $responseJson = (array)json_decode($response);

        if (!isset( $responseJson['message']))
        {
            foreach ($responseJson as $contributor) 
            {
                try
                {
                    $contributors[] = $contributor->login;
                }
                catch (\Exception $e) {
                    //Ignoring this exception - Ideally the exception should be logged!
                    //Perhaps we have reached our limit for github API requests!!! Or the repository does not exists anymore!!!
                }
            }
        }
        return $contributors;
    }

    public function getTopRankedUsers()
    {

    }

}