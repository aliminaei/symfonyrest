<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class BuildGraphCommand extends ContainerAwareCommand{

    protected function configure()
    {
        $this->setName('app:graph:update');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $packagistAdapter = $this->getContainer()->get("packagist_adapter");
        $packageNames = $packagistAdapter->getPackageNames();
        // foreach ($packageNames as $packageName)
        for ($i=0; $i < 80; $i++)
        {
            $packageName = $packageNames[$i];
            $data = [
                "package_name" => $packageName
            ];
            $this->getContainer()->get("rs_queue.producer")->produce("crawler", $data);
        }
    }
}