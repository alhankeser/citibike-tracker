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
            $data = $response->getBody();
            DB::table('stations_raw')->insert([
                ['data' => $data]
            ]);
        });
    }
}
