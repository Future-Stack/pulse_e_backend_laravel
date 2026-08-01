<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('ST_GeomFromText', function ($wkt, $srid = 0) {
                if (! $wkt) return null;
                try {
                    return \Brick\Geo\Point::fromText($wkt, (int) ($srid ?? 0))->asBinary();
                } catch (\Throwable) {
                    return $wkt;
                }
            });
            $pdo->sqliteCreateFunction('GeomFromText', function ($wkt, $srid = 0) {
                if (! $wkt) return null;
                try {
                    return \Brick\Geo\Point::fromText($wkt, (int) ($srid ?? 0))->asBinary();
                } catch (\Throwable) {
                    return $wkt;
                }
            });
            $pdo->sqliteCreateFunction('ST_AsText', function ($geom) {
                if (! $geom) return null;
                try {
                    return \Brick\Geo\Point::fromBinary($geom)->asText();
                } catch (\Throwable) {
                    return $geom;
                }
            });
        }
    }
}
