<?php

namespace AppBundle\Handler;

class HttpHandler
{

    public static function HttpGetRequest($url, $username="", $password="")
    {
        $ch = curl_init();  
 
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Codular Sample cURL Request');
        if ($username != "" && $password != "")
        {
            curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        }

        $output=curl_exec($ch);
     
        if($output === false)
        {
            echo "Error Number:".curl_errno($ch)."<br>";
            echo "Error String:".curl_error($ch);
        }
        curl_close($ch);
        return $output;
    }
}
