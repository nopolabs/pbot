<?php
use PBot\PBot;

require 'vendor/autoload.php';

$config = require './config.php';

$pbot = new PBot($config);

$pbot->init();

$pbot->run();
