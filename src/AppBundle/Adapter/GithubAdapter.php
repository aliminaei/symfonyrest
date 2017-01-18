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
    public function parseContributors($contributorsUrl)
    {
        $contributors = array();

        $options = array('auth' => array($this->apiUsername, $this->apiToken));
        $request = Requests::get($contributorsUrl, [], $options);

        if ($request->status_code == 403)
        {
            if ($request->headers['X-RateLimit-Remaining'] == '0')
            {
                $rateLimitReset = $request->headers['X-RateLimit-Reset'];
                $responseJson = json_decode($request->body);
                $response = [
                    "ack" => "Error",
                    "error_message" => $responseJson['message'],
                    "rate_limit_reset" => $$rateLimitReset
                ];

                return $response;
            }
        }
        elseif ($request->status_code == 200) {
            $responseJson = (array)json_decode($request->body);

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

            $response = [
                "ack" => "OK",
                "contributors" => $contributors
            ];
            return $response;
        }
        else
        {
            $response = [
                "ack" => "Error",
                "error_message" => "Unknown Error"
            ];

            return $response;
        }
        
    }

    public function getTopRankedUsers()
    {

    }

}
