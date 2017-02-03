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

$cache = new DoctrineCache(new FilesystemCache(__DIR__.'/'.$config['cache_dir']));

$storage = new FileStorage(__DIR__.'/'.$config['storage_dir']);

$bot = BotManFactory::createUsingRTM($config, $client, $cache, $storage);

$bot->hears('favs', function (BotMan $bot) use ($twitter) {
    $rsp = $twitter->get('favorites/list.json');
    $list = GuzzleHttp\json_decode($rsp->getBody());
    $favs = [];
    foreach ($list as $fav) {
        $favs[] = sprintf(
            'https://twitter.com/%s/status/%s',
            $fav->user->screen_name,
            $fav->id_str
        );
    }
    $bot->reply(join("\n", $favs));
});

$update = function() use ($twitter, $storage, $bot, $client) {
    $last = $storage->get('last_fav');
    $lastUrl = isset($last['url']) ? $last['url'] : null;
    $rsp = $twitter->get('favorites/list.json');
    $list = GuzzleHttp\json_decode($rsp->getBody());
    $favs = [];
    $url = null;
    foreach ($list as $fav) {
        $url = sprintf(
            'https://twitter.com/%s/status/%s',
            $fav->user->screen_name,
            $fav->id_str
        );
        if ($url == $lastUrl) {
            break;
        }
        $favs[] = $url;
    }
    if ($url) {
        $storage->save(['url'=>$url], 'last_fav');
    }

    if ($favs) {
        $favs = array_reverse($favs);
        $client->getChannelByName('general')->then(function ($channel) use ($bot, $favs) {
            $bot->say(join("\n", $favs), $channel->getId());
        });
    }
};

$loop->addPeriodicTimer(60, function() use ($update) {
    echo "tick\n";
    $update();
});

$loop->run();

// https://twitter.com/PostOpinions/status/827504414650941442