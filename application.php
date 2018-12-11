<?php

require __DIR__.'/vendor/autoload.php';
require 'ValidateEmailsCommand.php';

use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ValidateEmailsCommand());
$application->run();