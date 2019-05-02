<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\StationRaw;
use Artisan;
use DB;

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
                    $response = $client->request('GET', 'maps/api/geocode/json?latlng=' . $station->latitude . ',' . $station->longitude . '&key=' . $gmapsApiKey);
                    $geolocation = json_decode($response->getBody())->results;
                    $zip = $geolocation[0]->address_components[3]->short_name;
                    $geo5 = $geolocation[5]->address_components[0]->short_name;
                    $geo6 = $geolocation[6]->address_components[0]->short_name;
                    $geo7 = $geolocation[7]->address_components[0]->short_name;
                    DB::table('station_locations')->insert([
                        'station_id' => $station->id,
                        'zip' => $zip,
                        'location_5' => $geo5,
                        'location_6' => $geo6,
                        'location_7' => $geo7
                    ]);
                }
            }
        }); // End getstations
    }
}
