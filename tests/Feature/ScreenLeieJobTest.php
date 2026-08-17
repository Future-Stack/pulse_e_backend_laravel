<?php

namespace Tests\Feature;

use App\Jobs\ScreenLeieJob;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class ScreenLeieJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_leie_job_flags_matching_npi_as_excluded(): void
    {
        $provider = Provider::factory()->create([
            'npi' => '9999999999',
            'status' => 'candidate',
            'location' => new Point(37.7749, -122.4194),
        ]);

        $csvData = "LASTNAME,FIRSTNAME,MIDNAME,BUSNAME,GENERAL,SPECIALTY,UPIN,NPI,DOB,ADDRESS,CITY,STATE,ZIP,EXCLTYPE,EXCLDATE,REINDATE\n"
                 . "SMITH,JOHN,,DOCTOR CLINIC,,,NPI123,9999999999,19700101,123 MAIN ST,CITY,ST,12345,1128A,20200101,00000000\n";

        Http::fake([
            'https://oig.hhs.gov/exclusions/downloadables/UPDATED.csv' => Http::response($csvData, 200),
        ]);

        (new ScreenLeieJob())->handle();

        $this->assertDatabaseHas('providers', [
            'id' => $provider->id,
            'status' => 'excluded',
        ]);

        $this->assertDatabaseHas('vetting_records', [
            'provider_id' => $provider->id,
            'check_type' => 'leie',
            'status' => 'fail',
        ]);
    }
}
