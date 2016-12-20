<?php
    
namespace AppBundle\Adapter;

use AppBundle\Common\HttpHandler;

class PackagistAdapter
{
    /**
     * Get all the package names from Packagist
     *
     * Using Packagist API - https://packagist.org/apidoc
     *
     * @return array - array of string containing all package names on Packagist  
     */
    public function getPackageNames()
    {
        $response = HttpHandler::HttpGetRequest("https://packagist.org/packages/list.json");
        $responseJson = json_decode($response);
        
        try
        {
            return $responseJson->packageNames;
        } 
        catch (\Exception $e)
        {   
            return [];
        }
    }

    /**
     * Get the package github repo URL from Packagist for the given package name 
     *
     * Using Packagist API - https://packagist.org/apidoc
     *
     * @param string $packageName - name of the package on Packagist
     *
     * @return string - string containing the package github URL
     */
    public function getPackageGithubURL($packageName)
    {
        //The API URL to get the package details by package name.. 
        $requestURL = sprintf("https://packagist.org/packages/%s.json", $packageName);
        $response = HttpHandler::HttpGetRequest($requestURL);
        
        $responseJson = json_decode($response);
        try
        {
            return $responseJson->package->repository;
        } 
        catch (\Exception $e)
        {   
            return "";
        }
    }

}
