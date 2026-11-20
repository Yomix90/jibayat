<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Schedule;

/*
| Tâches planifiées (cron : * * * * * php artisan schedule:run).
*/

// Sauvegarde automatique quotidienne (Paramètres ➜ Sauvegardes).
Schedule::command('jibayat:backup --type=auto --if-enabled')
    ->dailyAt(Setting::get('backup.time', '02:00') ?: '02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Alertes du jour (cloche et e-mail) et rapports de recettes par e-mail.
Schedule::command('jibayat:alerts')->dailyAt('07:00')->onOneServer();
Schedule::command('jibayat:alerts --reports=quotidien')->dailyAt('07:15')->onOneServer();
Schedule::command('jibayat:alerts --reports=hebdomadaire')->weeklyOn(1, '07:20')->onOneServer();
Schedule::command('jibayat:alerts --reports=mensuel')->monthlyOn(1, '07:25')->onOneServer();
