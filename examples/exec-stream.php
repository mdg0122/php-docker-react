<?php
// this example executes some commands within the given running container and
// displays the streaming output as it happens.

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;
use React\Stream\Stream;

$container = 'asd';
//$cmd = array('echo', 'hello world');
//$cmd = array('sleep', '2');
$cmd = array('sh', '-c', 'echo -n hello && sleep 1 && echo world && sleep 1 && env');
//$cmd = array('cat', 'invalid-path');

if (isset($argv[1])) {
    $container = $argv[1];
    $cmd = array_slice($argv, 2);
}

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$out = new Stream(STDOUT, $loop);
$out->pause();

// unkown exit code by default
$exit = 1;

$client->execCreate($container, $cmd)->then(function ($info) use ($client, $out, &$exit) {
    $stream = $client->execStartStream($info['Id']);
    $stream->pipe($out);

    $stream->on('error', 'printf');

    // remember exit code of executed command once it closes
    $stream->on('close', function () use ($client, $info, &$exit) {
        $client->execInspect($info['Id'])->then(function ($info) use (&$exit) {
            $exit = $info['ExitCode'];
        }, 'printf');
    });
}, 'printf');

$loop->run();

exit($exit);
