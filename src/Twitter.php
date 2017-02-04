<?php
namespace PBot;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

class Twitter
{
    /** @var Client */
    private $client;

    public function __construct(array $config)
    {
        $stack = HandlerStack::create();
        $middleware = new Oauth1($config['oauth']);
        $stack->push($middleware);
        $this->client = new Client([
            'auth' => 'oauth',
            'base_uri' => 'https://api.twitter.com/1.1/',
            'handler' => $stack
        ]);
    }

    public function get($uri)
    {
        return $this->client->get($uri);
    }
}