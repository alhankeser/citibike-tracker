<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\StationRaw;
use Artisan;
use DB;
use DateTime;
use DateTimeZone;
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


        Artisan::command('get:docks', function () {
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
                        'last_communication_time' => $station->lastCommunicationTime,
                        'station_status' => $station->statusValue
                    ]
                ]);
            }   
        });

        Artisan::command('get:locations', function () {
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

        Artisan::command('update:availability', function () {
            DB::statement('
            REPLACE INTO availability (
                station_id, 
                station_name, 
                station_status, 
                latitude, 
                longitude, 
                zip, 
                borough, 
                hood, 
                available_bikes, 
                available_docks, 
                time_interval,
                weather_summary,
                precip_intensity,
                temperature,
                humidity,
                wind_speed,
                wind_gust,
                cloud_cover)
            SELECT  stations.id as station_id,
                    stations.name as station_name,
                    docks.station_status as station_status,
                    stations.latitude,
                    stations.longitude,
                    locations.zip,
                    locations.borough,
                    locations.hood_1 AS hood,
                    MIN(docks.available_bikes) AS available_bikes,
                    MAX(docks.available_docks) AS available_docks,
                    CAST(CAST(FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(SUBTIME(docks.created_at, \'04:00:00\'))/ (60*15))*(60*15)) AS CHAR) AS DATETIME) AS time_interval,
                    weather.summary,
                    weather.precip_intensity,
                    weather.temperature,
                    weather.humidity,
                    weather.wind_speed,
                    weather.wind_gust,
                    weather.cloud_cover
                FROM docks
            JOIN stations stations
            ON docks.station_id = stations.id
            LEFT JOIN station_locations locations
            ON docks.station_id = locations.station_id
            LEFT JOIN weather
            ON locations.zip = weather.zip
            AND HOUR(SUBTIME(docks.created_at, \'04:00:00\')) = HOUR(weather.timestamp_est)
            AND DATE(SUBTIME(docks.created_at, \'04:00:00\')) = DATE(weather.timestamp_est)
            WHERE docks.station_id IS NOT NULL
                AND docks.created_at > SUBTIME(CONCAT(CURDATE(), \' \', MAKETIME(HOUR(NOW()),0,0)), \'00:15:00\')
            GROUP BY time_interval, stations.name, stations.id, station_status, locations.borough, hood, stations.latitude, stations.longitude, locations.zip, weather.temperature, weather.summary,weather.precip_intensity,weather.temperature,weather.humidity,weather.wind_speed,weather.wind_gust,weather.cloud_cover;
            ');
        });

        Artisan::command('transform:hour', function () {
            DB::statement('
            INSERT INTO availability (
                station_id, 
                station_name, 
                station_status, 
                latitude, 
                longitude, 
                zip, 
                borough, 
                hood, 
                available_bikes, 
                available_docks, 
                time_interval, 
                weather_summary,
                precip_intensity,
                temperature,
                humidity,
                wind_speed,
                wind_gust,
                cloud_cover)
            SELECT  stations.id as station_id,
                    stations.name as station_name,
                    docks.station_status as station_status,
                    stations.latitude,
                    stations.longitude,
                    locations.zip,
                    locations.borough,
                    locations.hood_1 AS hood,
                    MIN(docks.available_bikes) AS available_bikes,
                    MAX(docks.available_docks) AS available_docks,
                    CAST(CAST(FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(SUBTIME(docks.created_at, \'04:00:00\'))/ (60*15))*(60*15)) AS CHAR) AS DATETIME) AS time_interval,
                    weather.summary,
                    weather.precip_intensity,
                    weather.temperature,
                    weather.humidity,
                    weather.wind_speed,
                    weather.wind_gust,
                    weather.cloud_cover
                FROM docks
            JOIN stations stations
            ON docks.station_id = stations.id
            LEFT JOIN station_locations locations
            ON docks.station_id = locations.station_id
            LEFT JOIN weather
            ON locations.zip = weather.zip
            AND HOUR(SUBTIME(docks.created_at, \'04:00:00\')) = HOUR(weather.timestamp_est)
            AND DATE(SUBTIME(docks.created_at, \'04:00:00\')) = DATE(weather.timestamp_est)
            WHERE docks.station_id IS NOT NULL
                AND docks.created_at > SUBTIME(CONCAT(CURDATE(), \' \', MAKETIME(HOUR(NOW()),0,0)), \'01:00:00\')
                AND docks.created_at < SUBTIME(CONCAT(CURDATE(), \' \', MAKETIME(HOUR(NOW()),0,0)), \'00:00:00\')
            GROUP BY time_interval, stations.name, stations.id, station_status, locations.borough, hood, stations.latitude, stations.longitude, locations.zip, weather.temperature, weather.summary,weather.precip_intensity,weather.temperature,weather.humidity,weather.wind_speed,weather.wind_gust,weather.cloud_cover;
            ');
        });

        Artisan::command('transform:all', function () {
            DB::statement('
            INSERT INTO availability (
                station_id, 
                station_name, 
                station_status, 
                latitude, 
                longitude, 
                zip, 
                borough, 
                hood, 
                available_bikes, 
                available_docks, 
                time_interval, 
                weather_summary,
                precip_intensity,
                temperature,
                humidity,
                wind_speed,
                wind_gust,
                cloud_cover)
            SELECT  stations.id as station_id,
                    stations.name as station_name,
                    docks.station_status as station_status,
                    stations.latitude,
                    stations.longitude,
                    locations.zip,
                    locations.borough,
                    locations.hood_1 AS hood,
                    MIN(docks.available_bikes) AS available_bikes,
                    MAX(docks.available_docks) AS available_docks,
                    CAST(CAST(FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(SUBTIME(docks.created_at, \'04:00:00\'))/ (60*15))*(60*15)) AS CHAR) AS DATETIME) AS time_interval,
                    weather.summary,
                    weather.precip_intensity,
                    weather.temperature,
                    weather.humidity,
                    weather.wind_speed,
                    weather.wind_gust,
                    weather.cloud_cover
                FROM docks
            JOIN stations stations
            ON docks.station_id = stations.id
            LEFT JOIN station_locations locations
            ON docks.station_id = locations.station_id
            LEFT JOIN weather weather
            ON locations.zip = weather.zip
            AND HOUR(SUBTIME(docks.created_at, \'04:00:00\')) = HOUR(weather.timestamp_est)
            AND DATE(SUBTIME(docks.created_at, \'04:00:00\')) = DATE(weather.timestamp_est)
            WHERE docks.station_id IS NOT NULL
                AND docks.created_at < SUBTIME(CONCAT(CURDATE(), \' \', MAKETIME(HOUR(NOW()),0,0)), \'00:00:00\')
            GROUP BY time_interval, stations.name, stations.id, station_status, locations.borough, hood, stations.latitude, stations.longitude, locations.zip, weather.temperature, weather.summary,weather.precip_intensity,weather.temperature,weather.humidity,weather.wind_speed,weather.wind_gust,weather.cloud_cover;
            ');
        });

        Artisan::command('get:weather {daysAgo}', function ($daysAgo) {
            $client = new Client([
                'base_uri' => 'https://api.darksky.net/',
                'timeout' => 10
            ]);
            $darkSkyKey = env('DARK_SKY_API_KEY');
            $distinctZips = DB::table('station_locations')->select('zip')->distinct()->get();
            foreach ($distinctZips as $zip) {
                $stationId = DB::table('station_locations')->select('station_id')->where('zip',$zip->zip)->first()->station_id;
                $sampleStation = DB::table('stations')->where('id', $stationId)->first();
                $now = time();
                $pastDate = strtotime("-{$daysAgo} day", $now);
                $endPoint =  implode([$darkSkyKey, '/', $sampleStation->latitude, ',', $sampleStation->longitude, ',', $pastDate]);
                $response = $client->request('GET','forecast/' . $endPoint);
                $pastDateWeather = json_decode($response->getBody());
                foreach ($pastDateWeather->hourly->data as $hour) {
                    $dt = new DateTime('@' . $hour->time);
                    $dt->setTimeZone(new DateTimeZone('America/New_York'));
                    $timestamp_est = $dt->format('Y-m-d H:i:s');

                    DB::table('weather')->updateOrInsert(
                        ['zip'               => $zip->zip,
                        'timestamp_est'     => $timestamp_est],
                        [
                            'summary'           => $hour->summary,
                            'icon'              => $hour->icon,
                            'precip_intensity'  => $hour->precipIntensity,
                            'temperature'       => $hour->temperature,
                            'apparent_temperature' => $hour->apparentTemperature,
                            'dew_point'         => $hour->dewPoint,
                            'humidity'          => $hour->humidity,
                            'wind_speed'        => $hour->windSpeed,
                            'wind_gust'         => $hour->windGust,
                            'cloud_cover'       => $hour->cloudCover,
                            'uv_index'          => $hour->uvIndex,
                            'visibility'        => $hour->visibility,
                            'ozone'             => $hour->ozone
                        ]
                    );
                }
                sleep(1);
            }
        });

    }
}
