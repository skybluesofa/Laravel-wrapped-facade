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
    public function x_testIndexIsCold(): void
    {
        Cache::spy();
        Log::spy();

        WrappedApiRepository::index();

        Cache::shouldHaveReceived('get')
            ->once()
            ->with('ApiRepository.cachedResults');
        Cache::shouldReceive('set')
            ->once()
            ->withArgs(['ApiRepository.cachedResults', [
                '1' => 'Apple',
                '2' => 'Banana',
                '3' => 'Carrot',
                '4' => 'Durifruit',
                '5' => 'Eggplant',
            ]]);

        Log::shouldHaveReceived('info')
            ->twice();
        Log::shouldHaveReceived('info')
            ->with('ApiRepository cache has expired');
        Log::shouldHaveReceived('info')
            ->with('Caching ApiRepository data');
    }

    public function testIndexCacheIsWarm(): void
    {
        $expectedResults = [
            'Automobile',
            'Boomerang',
            'Camper',
            'Dog',
            'Efficiency',
        ];
        Cache::set('ApiRepository.cachedResults', $expectedResults);

        Log::spy();

        $actualResults = WrappedApiRepository::index();
        $this->assertSame($expectedResults, $actualResults);

        Log::shouldHaveReceived('info')
            ->twice();
        Log::shouldHaveReceived('info')
            ->with('Returning cached ApiRepository data');
        Log::shouldHaveReceived('info')
            ->with('ApiRepository data was alredy in the cache');
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

    public function provideLoggingInformation(): array
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
                'logFacadeInfoCalls' => 5,
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
                'logFacadeInfoCalls' => 5,
            ],
            'log in dev and testing' => [
                'set_environment' => 'testing',
                'environments' => ['dev', 'testing'],
                'logFacadeInfoCalls' => 5,
            ],
        ];
    }
}
