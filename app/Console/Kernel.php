<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\StationRaw;
use Artisan;
use DB;
use Illuminate\Support\Arr;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');

        Artisan::command('getstations', function () {
            $client = new Client([
                'base_uri' => 'https://feeds.citibikenyc.com/',
                'timeout' => 10
            ]); 
            $response = $client->request('GET','stations/stations.json');
            $data = json_decode($response->getBody());
            DB::table('stations_raw')->insert([
                ['data' => json_encode($data)]
            ]);
            $id = DB::table('stations_raw')->max('id');
            foreach($data->stationBeanList as $station) {
                DB::table('stations')->updateOrInsert(
                    ['id' => $station->id],
                    [
                        'name' => $station->stationName,
                        'total_docks' => $station->totalDocks,
                        'latitude' => $station->latitude,
                        'longitude' => $station->longitude,
                        'status' => $station->statusValue,
                        'status_key' => $station->statusKey,
                        'st_address_1' => $station->stAddress1,
                        'st_address_2' => $station->stAddress2,
                        'city' => $station->city,
                        'postal_code' => $station->postalCode,
                        'location' => $station->location,
                        'altitude' => $station->altitude,
                        'is_test_station' => $station->testStation,
                        'land_mark' => $station->landMark
                    ]
                );
                DB::table('docks')->insert([
                    [
                        'station_id' => $station->id,
                        'available_bikes' => $station->availableBikes,
                        'available_docks' => $station->availableDocks,
                        'last_communication_time' => $station->lastCommunicationTime
                    ]
                ]);
            }   
            
            $client = new Client([
                'base_uri' => 'https://maps.googleapis.com/',
                'timeout' => 10
            ]); 
            $gmapsApiKey = env('GMAPS_API_KEY');
            $stations = DB::table('stations')
                            ->where('id','<', env('GMAPS_ENV_LIMIT'))
                            ->get();
            foreach($stations as $station) {
                $locationCount = DB::table('station_locations')
                                        ->where('station_id', $station->id)
                                        ->count();
                if (!$locationCount) {
                    try {
                        $response = $client->request('GET', 'maps/api/geocode/json?latlng=' . $station->latitude . ',' . $station->longitude . '&key=' . $gmapsApiKey);
                        $geocodes = json_decode($response->getBody())->results;
                    } catch (Exception $e) {
                        $geocodes = [];
                    }
                    
                    $zip = false;
                    $firstHood = false;
                    $secondHood = false;
                    $borough = false;
                    foreach($geocodes as $geocode) {
                        foreach($geocode->address_components as $geo_component) {
                            $zipIndex = array_search('postal_code', $geo_component->types);
                            $hoodIndex = array_search('neighborhood', $geo_component->types);
                            $boroughIndex = array_search('sublocality', $geo_component->types);
                            if ($zipIndex > -1 && !$zip) {
                                $zip = $geo_component->short_name;
                            }
                            if ($hoodIndex > -1 && !$firstHood) {
                                $firstHood = $geo_component->short_name;
                            }
                            elseif ($hoodIndex > -1 && $firstHood && 
                                    !$secondHood && $firstHood != $geo_component->short_name) {
                                $secondHood = $geo_component->short_name;
                            }
                            if ($boroughIndex > -1 && !$borough) {
                                $borough = $geo_component->short_name;
                            }
                        }
                    }
            
                    DB::table('station_locations')->insert([
                        'station_id' => $station->id,
                        'zip' => $zip,
                        'hood_1' => $firstHood,
                        'hood_2' => $secondHood,
                        'borough' => $borough
                    ]);
                    
                }
            }
        }); // End getstations
    }
}
