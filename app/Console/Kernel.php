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
            $this->call('extract:stations');
        });

        Artisan::command('extract:stations', function () {
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
        }); // End getstations

        Artisan::command('extract:locations', function () {
            $client = new Client([
                'base_uri' => 'https://maps.googleapis.com/',
                'timeout' => 10
            ]); 
            $gmapsApiKey = env('GMAPS_API_KEY');
            $stations = DB::table('stations')
                            ->where('id','<', (int)env('GMAPS_ENV_LIMIT'))
                            ->get();
            foreach($stations as $station) {
                $locationCount = DB::table('station_locations')
                                        ->where('station_id', $station->id)
                                        ->count();
                if (!$locationCount) {
                    
                    $response = $client->request('GET', 'maps/api/geocode/json?latlng=' . $station->latitude . ',' . $station->longitude . '&key=' . $gmapsApiKey);
                    $geocodes = json_decode($response->getBody())->results;

                    $zip = null;
                    $firstHood = null;
                    $secondHood = null;
                    $borough = null;
                    $county = null;
                    $state = null;
                    foreach($geocodes as $geocode) {
                        foreach($geocode->address_components as $geo_component) {
                            $zipIndex = array_search('postal_code', $geo_component->types);
                            $hoodIndex = array_search('neighborhood', $geo_component->types);
                            $boroughIndex = array_search('sublocality', $geo_component->types);
                            $countyIndex = array_search('administrative_area_level_2', $geo_component->types);
                            $stateIndex = array_search('administrative_area_level_1', $geo_component->types);
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
                            if ($countyIndex > -1 && !$county) {
                                $county = $geo_component->short_name;
                            }
                            if ($stateIndex > -1 && !$state) {
                                $state = $geo_component->long_name;
                            }
                        }
                    }

                    if (!$borough) {
                        $borough = $state;
                    }
                    if (!$firstHood) {
                        $firstHood = $county;
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
        });

        Artisan::command('transform:day', function () {
            DB::statement('
                INSERT INTO availability (station_id, station_name, latitude, longitude, zip, borough, hood, available_bikes, time_interval)
                SELECT  stations.id as station_id,
                        stations.name as station_name,
                        stations.latitude,
                        stations.longitude,
                        locations.zip,
                        locations.borough,
                        locations.hood_1 AS hood,
                        MIN(docks.available_bikes) AS available_bikes,
                        CAST(CAST(FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(SUBTIME(docks.created_at, \'04:00:00\'))/ (60*15))*(60*15)) AS CHAR) AS DATETIME) AS time_interval
                    FROM docks docks
                JOIN stations stations
                ON docks.station_id = stations.id
                LEFT JOIN station_locations locations
                ON docks.station_id = locations.station_id
                WHERE docks.station_id IS NOT NULL
                    AND SUBTIME(docks.created_at, \'04:00:00\') > ADDTIME(CAST(SUBDATE(current_date, 1) AS DATETIME), \'04:00:00\')
                    AND SUBTIME(docks.created_at, \'04:00:00\') < ADDTIME(CAST(current_date AS DATETIME), \'04:00:00\')
                GROUP BY time_interval, stations.name, stations.id, locations.borough, hood, stations.latitude, stations.longitude, locations.zip
            ');
        });
    }
}
