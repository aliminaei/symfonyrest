<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\JsonResponse;

class GraphBuilderController extends Controller
{
    public function buildGraphAction()
    {
        $kernel = $this->get('kernel');
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array(
           'command' => 'app:graph:update'
        ));
        // You can use NullOutput() if you don't need the output
        $output = new NullOutput();
        $application->run($input, $output);

        // return new Response(""), if you used NullOutput()
        return new JsonResponse(array("message"=> "Task queued."), 200);
    }
}
