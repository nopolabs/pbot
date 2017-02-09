<?php
namespace PBot;

use Doctrine\Common\Cache\FilesystemCache;
use Mpociot\BotMan\BotMan;
use Mpociot\BotMan\BotManFactory;
use Mpociot\BotMan\Cache\DoctrineCache;
use Mpociot\BotMan\Storages\Drivers\FileStorage;
use React\EventLoop\Factory;
use React\EventLoop\Timer\Timer;
use Slack\RealTimeClient;

class PBot
{
    private $loop;
    private $client;
    private $cache;
    private $storage;
    private $bot;
    private $twitter;
    private $i = 0;
    private $channelId;

    public function __construct(array $config)
    {
        $this->loop = Factory::create();

        $this->client = new RealTimeClient($this->loop);

        $this->cache = new DoctrineCache(new FilesystemCache(__DIR__ . '/' . $config['cache_dir']));

        $this->storage = new FileStorage(__DIR__ . '/' . $config['storage_dir']);

        $this->bot = BotManFactory::createUsingRTM($config['bot'], $this->client, $this->cache, $this->storage);

        $this->twitter = new Twitter($config['twitter']);
    }

    public function init()
    {
        $this->bot->hears('last', function (BotMan $bot) {
            $last = $this->storage->get('last_fav');

            $user = '<@' . $bot->getMessage()->getUser() . '>';

            if ($last['url']) {
                $favorite = '<' . $last['url'] . '|favorite>';
                $bot->reply($user . ' This was your last ' . $favorite . '.');
            } else {
                $bot->reply($user . ' I do not know your last favorite.');
            }
        });

        $this->loop->addPeriodicTimer(300, function() {
            $this->updateFavs();
        });

        $this->loop->addPeriodicTimer(1, function(Timer $timer) {
            $this->client->getChannelByName('general')->then(function ($channel) use ($timer) {
                $this->channelId = $channel->getId();
                $this->loop->cancelTimer($timer);
            });
        });
    }

    public function run()
    {
        $this->loop->run();
    }

    public function updateFavs()
    {
        $last = $this->storage->get('last_fav');
        $lastFav = isset($last['url']) ? $last['url'] : null;
        $favs = $this->twitter->getFavs();
        $newFavs = [];
        $fav = null;
        foreach ($favs as $fav) {
            if ($fav == $lastFav) {
                break;
            }
            $newFavs[] = $fav;
        }

        if ($fav) {
            $this->storage->save(['url'=>$fav], 'last_fav');
        }

        if ($newFavs) {
            if ($this->channelId) {
                $this->bot->say(join("\n", array_reverse($newFavs)), $this->channelId);
            }
        }
    }
}