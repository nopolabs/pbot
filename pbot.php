<?php
require 'vendor/autoload.php';

use Mpociot\BotMan\BotManFactory;
use React\EventLoop\Factory;

require './config.php';

$loop = Factory::create();
$botman = BotManFactory::createForRTM($config, $loop);

$botman->hears('hey', function($bot) {
    $bot->reply('I heard you! :)');
});

$loop->run();

