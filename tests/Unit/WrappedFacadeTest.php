<?php
/**
 * Test the ValidatesWithJsonSchema trait, which is used before saving in JsonModel
 * and can be used before emitting in ApiController->validatedJson()
 */
declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Tests\ExampleClasses\WrappedApiRepository;

/**
 * Class ValidatesWithJsonSchemaTest
 */
class WrappedFacadeTest extends TestCase
{
    public function testIndexIsCold(): void
    {
        Cache::shouldReceive('get')
            ->with('wrapped-facade.prefixes')
            ->andReturn(null);
        Cache::shouldReceive('set')
            ->with('wrapped-facade.prefixes', ['pre' => 'pre', 'post' => 'post']);
        Cache::shouldReceive('get')
            ->with('ApiRepository.cachedResults')
            ->andReturn(null);
        Cache::shouldReceive('get')
            ->with('wrapped-facade.shouldLog')
            ->andReturn(true);
        Cache::shouldReceive('set')
            ->with(
                'ApiRepository.cachedResults',
                [1 => 'Apple', 2 => 'Banana', 3 => 'Carrot', 4 => 'Durifruit', 5 => 'Eggplant']
            );

        $expectedResults = [
            '1' => 'Apple',
            '2' => 'Banana',
            '3' => 'Carrot',
            '4' => 'Durifruit',
            '5' => 'Eggplant',
        ];

        $actualResults = WrappedApiRepository::index();
        $this->assertSame($expectedResults, $actualResults);
    }

    public function testIndexCacheIsWarm(): void
    {
        Cache::shouldReceive('get')
            ->with('wrapped-facade.prefixes')
            ->andReturn(null);
        Cache::shouldReceive('set')
            ->with('wrapped-facade.prefixes', ['pre' => 'pre', 'post' => 'post']);
        Cache::shouldReceive('get')
            ->with('wrapped-facade.shouldLog')
            ->andReturn(true);

        $expectedResults = [
            'Automobile',
            'Boomerang',
            'Camper',
            'Dog',
            'Efficiency',
        ];
        Cache::shouldReceive('set')
            ->with('ApiRepository.cachedResults', $expectedResults);
        Cache::set('ApiRepository.cachedResults', $expectedResults);
        Cache::shouldReceive('get')
            ->with('ApiRepository.cachedResults')
            ->andReturn($expectedResults);

        WrappedApiRepository::spy();

        $actualResults = WrappedApiRepository::index();

        $this->assertSame($expectedResults, $actualResults);

    }

    /**
     * @dataProvider provideLoggingInformation
     */
    public function testWrappedFacadeLogging(
        string $currentEnvironment,
        $environmentsThatLog,
        int $numberOfLoggingCalls,
    ): void {
        Config::set('app.env', $currentEnvironment);
        Config::set('wrapped-facade.log_in_environment', $environmentsThatLog);

        Log::spy();

        WrappedApiRepository::index();

        Log::shouldHaveReceived('info')
            ->times($numberOfLoggingCalls);
    }

    public static function provideLoggingInformation(): array
    {
        return [
            'log NO environments' => [
                'set_environment' => 'testing',
                'environments' => null,
                'logFacadeInfoCalls' => 2,
            ],
            'log all environments' => [
                'set_environment' => 'testing',
                'environments' => '*',
                'logFacadeInfoCalls' => 6,
            ],
            'only log production' => [
                'set_environment' => 'testing',
                'environments' => 'production',
                'logFacadeInfoCalls' => 2,
            ],
            'only log dev and prod' => [
                'set_environment' => 'testing',
                'environments' => ['dev', 'prod'],
                'logFacadeInfoCalls' => 2,
            ],
            'log in testing' => [
                'set_environment' => 'testing',
                'environments' => 'testing',
                'logFacadeInfoCalls' => 6,
            ],
            'log in dev and testing' => [
                'set_environment' => 'testing',
                'environments' => ['dev', 'testing'],
                'logFacadeInfoCalls' => 6,
            ],
        ];
    }
}
