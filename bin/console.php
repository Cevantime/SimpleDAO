<?php

use SimpleDAO\Generator\Command\GenerateEntities;
use Symfony\Component\Console\Application;

require_once __DIR__.'/../vendor/autoload.php';

$application = new Application();

$application->add(new GenerateEntities());

$application->run();
