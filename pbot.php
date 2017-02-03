<?php
require 'vendor/autoload.php';

use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Mpociot\BotMan\BotMan;
use Mpociot\BotMan\BotManFactory;
use Mpociot\BotMan\Cache\DoctrineCache;
use Mpociot\BotMan\Storages\Drivers\FileStorage;
use React\EventLoop\Factory;
use Slack\RealTimeClient;

$config = require './config.php';

$stack = HandlerStack::create();
$middleware = new Oauth1($config['twitter_oauth']);
$stack->push($middleware);
$twitter = new Client([
    'auth' => 'oauth',
    'base_uri' => 'https://api.twitter.com/1.1/',
    'handler' => $stack
]);

$loop = Factory::create();

$client = new RealTimeClient($loop);

$cache = new DoctrineCache(new FilesystemCache(__DIR__.$config['cache_dir']));

$storage = new FileStorage(__DIR__.$config['storage_dir']);

$bot = BotManFactory::createUsingRTM($config, $client, $cache, $storage);

$bot->hears('hey', function (BotMan $bot) use ($twitter) {
    $userId = $bot->getMessage()->getUser();
    $bot->reply('I heard you! :) <@' . $userId . '>');
    $res = $twitter->get('favorites/list.json');
    $list = $res->getBody()->getContents();
    $bot->reply("LIST: $list");
});

//$loop->addPeriodicTimer(10, function() use ($bot, $client, $twitter) {
//    $client->getChannelByName('general')->then(function ($channel) use ($bot, $twitter) {
//        $res = $twitter->get('favorites/list.json');
//        $bot->say($res, $channel->getId());
//    });
//});

$loop->run();

