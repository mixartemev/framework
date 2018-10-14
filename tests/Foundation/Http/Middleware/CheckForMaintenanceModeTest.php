<?php

namespace Illuminate\Tests\Foundation\Http\Middleware;

use Mockery as m;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;

class CheckForMaintenanceModeTest extends TestCase
{
    /**
     * @var string
     */
    protected $storagePath;

    /**
     * @var string
     */
    protected $downFilePath;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    public function setUp()
    {
        if (is_null($this->files)) {
            $this->files = new Filesystem;
        }

        $this->storagePath = __DIR__.'/tmp';
        $this->downFilePath = $this->storagePath.'/framework/down';

        $this->files->makeDirectory($this->storagePath.'/framework', 0755, true);
    }

    public function tearDown()
    {
        $this->files->deleteDirectory($this->storagePath);

        m::close();
    }

    public function testApplicationIsRunningNormally()
    {
        $app = m::mock(Application::class);
        $app->shouldReceive('isDownForMaintenance')->once()->andReturn(false);

        $middleware = new CheckForMaintenanceMode($app);

        $result = $middleware->handle(Request::create('/'), function ($request) {
            return 'Running normally.';
        });

        $this->assertSame('Running normally.', $result);
    }

    public function testApplicationAllowsSomeIPs()
    {
        $ips = ['127.0.0.1', '2001:0db8:85a3:0000:0000:8a2e:0370:7334'];

        // Check IPv4.
        $middleware = new CheckForMaintenanceMode($this->createMaintenanceApplication($ips));

        $request = m::mock(Request::class);
        $request->shouldReceive('ip')->once()->andReturn('127.0.0.1');

        $result = $middleware->handle($request, function ($request) {
            return 'Allowing [127.0.0.1]';
        });

        $this->assertSame('Allowing [127.0.0.1]', $result);

        // Check IPv6.
        $middleware = new CheckForMaintenanceMode($this->createMaintenanceApplication($ips));

        $request = m::mock(Request::class);
        $request->shouldReceive('ip')->once()->andReturn('2001:0db8:85a3:0000:0000:8a2e:0370:7334');

        $result = $middleware->handle($request, function ($request) {
            return 'Allowing [2001:0db8:85a3:0000:0000:8a2e:0370:7334]';
        });

        $this->assertSame('Allowing [2001:0db8:85a3:0000:0000:8a2e:0370:7334]', $result);
    }

    /**
     * @expectedException \Illuminate\Foundation\Http\Exceptions\MaintenanceModeException
     * @expectedExceptionMessage This application is down for maintenance.
     */
    public function testApplicationDeniesSomeIPs()
    {
        $middleware = new CheckForMaintenanceMode($this->createMaintenanceApplication());

        $result = $middleware->handle(Request::create('/'), function ($request) {
        });
    }

    public function testApplicationAllowsSomeURIs()
    {
        $app = $this->createMaintenanceApplication();

        $middleware = new class($app) extends CheckForMaintenanceMode {
            public function __construct($app)
            {
                parent::__construct($app);

                $this->except = ['foo/bar'];
            }
        };

        $result = $middleware->handle(Request::create('/foo/bar'), function ($request) {
            return 'Excepting /foo/bar';
        });

        $this->assertSame('Excepting /foo/bar', $result);
    }

    /**
     * @expectedException \Illuminate\Foundation\Http\Exceptions\MaintenanceModeException
     * @expectedExceptionMessage This application is down for maintenance.
     */
    public function testApplicationDeniesSomeURIs()
    {
        $middleware = new CheckForMaintenanceMode($this->createMaintenanceApplication());

        $result = $middleware->handle(Request::create('/foo/bar'), function ($request) {
        });
    }

    /**
     * Create a mock of maintenance application.
     *
     * @param  string|array  $ips
     * @return \Mockery\MockInterface
     */
    protected function createMaintenanceApplication($ips = null)
    {
        $this->makeDownFile($ips);

        $app = m::mock(Application::class);
        $app->shouldReceive('isDownForMaintenance')->once()->andReturn(true);
        $app->shouldReceive('storagePath')->once()->andReturn($this->storagePath);

        return $app;
    }

    /**
     * Make a down file with the given allowed ips.
     *
     * @param  string|array  $ips
     * @return array
     */
    protected function makeDownFile($ips = null)
    {
        $data = [
            'time' => time(),
            'retry' => 86400,
            'message' => 'This application is down for maintenance.',
        ];

        if ($ips !== null) {
            $data['allowed'] = $ips;
        }

        $this->files->put($this->downFilePath, json_encode($data, JSON_PRETTY_PRINT));

        return $data;
    }
}
