<?php

namespace Protonemedia\LaravelPaddle\Tests;

use Mockery;
use Orchestra\Testbench\TestCase;
use ProtoneMedia\LaravelPaddle\Api\Api;
use ProtoneMedia\LaravelPaddle\Api\PaddleApiException;
use ProtoneMedia\LaravelPaddle\LaravelPaddleServiceProvider;
use Zttp\PendingZttpRequest;

class ApiTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LaravelPaddleServiceProvider::class];
    }

    private function mockZttp(): PendingZttpRequest
    {
        return tap(Mockery::mock(PendingZttpRequest::class), function ($zttp) {
            $zttp->shouldReceive('asFormParams')->andReturnSelf();

            $this->app->singleton('laravel-paddle.http', function () use ($zttp) {
                return $zttp;
            });
        });
    }

    /** @test */
    public function it_throws_an_exception_if_the_request_was_unsuccessful()
    {
        $zttp = $this->mockZttp();
        $zttp->shouldReceive('post')->andReturnSelf();
        $zttp->shouldReceive('isSuccess')->andReturnFalse();
        $zttp->shouldReceive('status')->andReturn(500);

        try {
            $request = (new Api)->subscription()->plans()->send();
        } catch (PaddleApiException $exception) {
            return $this->assertEquals("Response with status code 500", $exception->getMessage());
        }

        $this->fail('Should have thrown PaddleApiException');
    }

    /** @test */
    public function it_throws_an_exception_if_the_success_attribute_is_false()
    {
        $zttp = $this->mockZttp();
        $zttp->shouldReceive('post')->andReturnSelf();
        $zttp->shouldReceive('isSuccess')->andReturnTrue();
        $zttp->shouldReceive('json')->andReturn([
            'success' => false,
            'error'   => ['code' => 1336, 'message' => 'Whoops!'],
        ]);

        try {
            $request = (new Api)->subscription()->plans()->send();
        } catch (PaddleApiException $exception) {
            return $this->assertEquals("[1336] Whoops!", $exception->getMessage());
        }

        $this->fail('Should have thrown PaddleApiException');
    }

    /** @test */
    public function it_returns_the_response_attribute_if_the_request_was_successful()
    {
        $zttp = $this->mockZttp();
        $zttp->shouldReceive('post')->andReturnSelf();
        $zttp->shouldReceive('isSuccess')->andReturnTrue();
        $zttp->shouldReceive('json')->andReturn([
            'success'  => true,
            'response' => 'Hello!',
        ]);

        $response = (new Api)->subscription()->plans()->send();

        $this->assertEquals('Hello!', $response);
    }
}
