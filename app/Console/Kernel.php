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
            $stations = DB::table('stations')->get();
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

        Artisan::command('update:availability:all', function() {
            // for ($i=3; $i < 11; $i++) {
            //     $hoursBack = $i*24;
            //     try {
            //         $this->call('update:availability', [
            //             'hoursBack' => $hoursBack
            //         ]);
            //         sleep(5);
            //     }
            //     catch (Exception $e) {
            //         echo $i;
            //         sleep(5);
            //         $i--;
            //     }
            // }
        });
        
        Artisan::command('update:availability {hoursBack}', function ($hoursBack) {
            $hoursBackLessThan = $hoursBack-1;
            DB::statement("
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
                                        cloud_cover,
                                        weather_status
                                    )
                        SELECT  station_id,
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
                                cloud_cover,
                                weather_status
                        FROM (
                            SELECT  stations.id AS station_id,
                                    stations.name AS station_name,
                                    docks.station_status AS station_status,
                                    stations.latitude AS latitude,
                                    stations.longitude AS longitude,
                                    locations.zip AS zip,
                                    locations.borough AS borough,
                                    locations.hood_1 AS hood,
                                    MIN(docks.available_bikes) AS available_bikes,
                                    MAX(docks.available_docks) AS available_docks,
                                    CAST(CAST(FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(docks.created_at)/ (60*15))*(60*15)) AS CHAR) AS DATETIME) AS time_interval,
                                    weather.summary AS weather_summary,
                                    weather.precip_intensity AS precip_intensity,
                                    weather.temperature AS temperature,
                                    weather.humidity AS humidity,
                                    weather.wind_speed AS wind_speed,
                                    weather.wind_gust AS wind_gust,
                                    weather.cloud_cover AS cloud_cover,
                                    weather.status AS weather_status
                                FROM docks
                            JOIN stations
                                ON docks.station_id = stations.id
                            LEFT JOIN station_locations locations
                                ON docks.station_id = locations.station_id
                            LEFT JOIN weather
                                ON locations.zip = weather.zip
                                AND HOUR(docks.created_at) = HOUR(weather.timestamp)
                                AND DATE(docks.created_at) = DATE(weather.timestamp)
                            WHERE docks.station_id IS NOT NULL
                                AND docks.created_at > SUBTIME(CONCAT(CURDATE(), ' ', MAKETIME(HOUR(NOW()),0,0)), '{$hoursBack}:00:00')
                                AND docks.created_at < SUBTIME(CONCAT(CURDATE(), ' ', MAKETIME(HOUR(NOW()),0,0)), '{$hoursBackLessThan}:00:00')
                            GROUP BY time_interval, stations.name, stations.id, docks.station_status, locations.borough, locations.hood_1, stations.latitude, stations.longitude, locations.zip, weather.temperature, weather.summary,weather.precip_intensity,weather.temperature,weather.humidity,weather.wind_speed,weather.wind_gust,weather.cloud_cover,weather.status
                        ) temp
                        ON DUPLICATE KEY UPDATE 
                            station_status = temp.station_status,
                            latitude = temp.latitude, 
                            longitude = temp.longitude, 
                            zip = temp.zip, 
                            borough = temp.borough, 
                            hood = temp.hood, 
                            available_bikes = temp.available_bikes, 
                            available_docks = temp.available_docks, 
                            weather_summary = temp.weather_summary,
                            precip_intensity = temp.precip_intensity,
                            temperature = temp.temperature,
                            humidity = temp.humidity,
                            wind_speed = temp.wind_speed,
                            wind_gust = temp.wind_gust,
                            cloud_cover = temp.cloud_cover,
                            weather_status = temp.weather_status
            ;"); 
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
                foreach ($pastDateWeather->hourly->data as $hourOfWeather) {
                    $hourUnix = new DateTime('@' . $hourOfWeather->time);
                    $hourUnix->setTimeZone(new DateTimeZone('America/New_York'));
                    $timestampEst = $hourUnix->format('Y-m-d H:i:s');
                    $hourAgo = strtotime("-1 hour", $now);
                    $hourAgo = new DateTime('@' . $hourAgo);
                    $hourAgo->setTimeZone(new DateTimeZone('America/New_York'));
                    $hourAgoEst = $hourAgo->format('Y-m-d H:i:s');
                    $weatherStatus = $hourAgoEst > $timestampEst ? 'observed' : 'predicted';

                    DB::statement("
                        INSERT INTO weather(
                                        zip, 
                                        timestamp, 
                                        summary,
                                        icon,
                                        precip_intensity,
                                        temperature,
                                        apparent_temperature,
                                        dew_point,
                                        humidity,
                                        wind_speed,
                                        wind_gust,
                                        cloud_cover,
                                        uv_index,
                                        visibility,
                                        ozone,
                                        status
                                    ) 
                        VALUES (
                            '{$zip->zip}',
                            '{$timestampEst}',
                            '{$hourOfWeather->summary}',
                            '{$hourOfWeather->icon}',
                            {$hourOfWeather->precipIntensity},
                            {$hourOfWeather->temperature},
                            {$hourOfWeather->apparentTemperature},
                            {$hourOfWeather->dewPoint},
                            {$hourOfWeather->humidity},
                            {$hourOfWeather->windSpeed},
                            {$hourOfWeather->windGust},
                            {$hourOfWeather->cloudCover},
                            {$hourOfWeather->uvIndex},
                            {$hourOfWeather->visibility},
                            {$hourOfWeather->ozone},
                            '{$weatherStatus}'
                        )
                        ON DUPLICATE KEY UPDATE
                            summary = '{$hourOfWeather->summary}',
                            icon = '{$hourOfWeather->icon}',
                            precip_intensity = {$hourOfWeather->precipIntensity},
                            temperature = {$hourOfWeather->temperature},
                            apparent_temperature = {$hourOfWeather->apparentTemperature},
                            dew_point = {$hourOfWeather->dewPoint},
                            humidity = {$hourOfWeather->humidity},
                            wind_speed = {$hourOfWeather->windSpeed},
                            wind_gust = {$hourOfWeather->windGust},
                            cloud_cover = {$hourOfWeather->cloudCover},
                            uv_index = {$hourOfWeather->uvIndex},
                            visibility = {$hourOfWeather->visibility},
                            ozone = {$hourOfWeather->ozone},
                            status = '{$weatherStatus}'
                    ;");
                }
                sleep(1);
            }
        });

        Artisan::command('update:weather', function () {
            DB::statement("
            update availability a
                left join weather w 
                    on a.zip = w.zip
                    and day(a.time_interval) = day(w.timestamp)
                    and hour(a.time_interval) = hour(w.timestamp)
                SET
                    a.weather_summary = w.summary,
                    a.precip_intensity = w.precip_intensity,
                    a.temperature = w.temperature,
                    a.humidity = w.humidity,
                    a.wind_speed = w.wind_speed,
                    a.wind_gust = w.wind_gust,
                    a.cloud_cover = w.cloud_cover,
                    a.weather_status = w.status
                where weather_status = 'predicted'
                    and a.time_interval < now()
                and (a.weather_status != w.status
                    or a.temperature != w.temperature)
            ;");
        });

    }
}
