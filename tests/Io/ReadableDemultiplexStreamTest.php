<?php

use React\Stream\ReadableStream;
use Clue\React\Docker\Io\ReadableDemultiplexStream;
use React\Stream\WritableStream;

class ReadableDemultiplexStreamTest extends TestCase
{
    private $stream;
    private $parser;

    public function setUp()
    {
        $this->stream = new ReadableStream();
        $this->parser = new ReadableDemultiplexStream($this->stream);
    }

    public function testStreamWillForwardEndAndClose()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableOnce());

        $this->stream->emit('end', array());

        $this->assertFalse($this->parser->isReadable());
    }

    public function testStreamWillForwardErrorAndClose()
    {
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());

        $this->stream->emit('error', array(new \RuntimeException('Test')));

        $this->assertFalse($this->parser->isReadable());
    }

    public function testStreamWillEmitErrorWhenEndingWithinStream()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());

        $this->stream->emit('data', array('XX'));
        $this->stream->emit('end', array());

        $this->assertFalse($this->parser->isReadable());
    }

    public function testStreamWillEmitDataOnCompleteFrame()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('test'));

        $this->stream->emit('data', array("\x01\x00\x00\x00" . "\x00\x00\x00\x04" . "test"));
    }

    public function testStreamWillNotEmitDataOnIncompleteFrameHeader()
    {
        $this->parser->on('data', $this->expectCallableNever());

        $this->stream->emit('data', array("\x01\0\0\0"));
    }

    public function testStreamWillNotEmitDataOnIncompleteFramePayload()
    {
        $this->parser->on('data', $this->expectCallableNever());

        $this->stream->emit('data', array("\x01\0\0\0" . "\0\0\0\x04" . "te"));
    }

    public function testStreamWillEmitDataOnCompleteFrameChunked()
    {
        $this->parser->on('data', $this->expectCallableOnceWith('test'));

        $this->stream->emit('data', array("\x01\x00\x00\x00" . "\x00\x00\x00\x04" . "te"));
        $this->stream->emit('data', array("st"));
    }

    public function testPipeWillBeForwardedToTargetStream()
    {
        $target = new WritableStream();
        $target->on('pipe', $this->expectCallableOnceWith($this->parser));

        $this->parser->pipe($target);
    }

    public function testPauseWillBeForwarded()
    {
        $this->stream = $this->getMock('React\Stream\ReadableStreamInterface');
        $this->stream->expects($this->once())->method('pause');
        $this->parser = new ReadableDemultiplexStream($this->stream);

        $this->parser->pause();
    }

    public function testResumeWillBeForwarded()
    {
        $this->stream = $this->getMock('React\Stream\ReadableStreamInterface');
        $this->stream->expects($this->once())->method('resume');
        $this->parser = new ReadableDemultiplexStream($this->stream);

        $this->parser->resume();
    }
}
