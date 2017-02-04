<?php
namespace PBot;

use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp;
use Mpociot\BotMan\BotMan;
use Mpociot\BotMan\BotManFactory;
use Mpociot\BotMan\Cache\DoctrineCache;
use Mpociot\BotMan\Storages\Drivers\FileStorage;
use React\EventLoop\Factory;
use React\EventLoop\Timer\Timer;
use React\Stream\BufferedSink;
use Slack\RealTimeClient;
use React\Http\Request as HttpRequest;
use React\Http\Response as HttpResponse;

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

        $this->httpServer = new HttpServer($config['http_server'], $this->loop);
    }

    public function init()
    {
        $this->httpServer->init(function (HttpRequest $request, HttpResponse $response) {
            $this->app($request, $response);
        });

        $this->bot->hears('favs', function (BotMan $bot) {
            $favs = $this->getFavs();
            $bot->reply(join("\n", $favs));
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
        $favs = $this->getFavs();
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

    public function getFavs() : array
    {
        $rsp = $this->twitter->get('favorites/list.json');
        $list = GuzzleHttp\json_decode($rsp->getBody());
        $favs = [];
        foreach ($list as $fav) {
            $favs[] = sprintf(
                'https://twitter.com/%s/status/%s',
                $fav->user->screen_name,
                $fav->id_str
            );
        }
        return $favs;
    }

    public function app(HttpRequest $request, HttpResponse $response)
    {
        $this->i++;

        $text = "This is request number $this->i.\n";

        $this->bot->say($text, $this->channelId);

        $sink = new BufferedSink();
        $request->pipe($sink);
        $sink->promise()->then(function ($data) {
            $this->bot->say($data, $this->channelId);
        });

        $headers = array('Content-Type' => 'text/plain');
        $response->writeHead(200, $headers);
        $response->write($text);
        $response->end();
    }
}