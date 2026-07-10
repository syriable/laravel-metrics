<?php

declare(strict_types=1);

namespace Syriable\Metrics\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\Metrics\MetricsServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
    }

    protected function getPackageProviders($app)
    {
        return [
            MetricsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('app.timezone', 'UTC');
    }

    protected function createTables(): void
    {
        Schema::create('orders', static function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('paid');
            $table->unsignedInteger('customer_id')->default(1);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('refund_total', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('expenses', static function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }
}
