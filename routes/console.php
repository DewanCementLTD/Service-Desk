<?php

use Illuminate\Support\Facades\Schedule;

// SLA state depends on the clock, so refresh it regularly rather than
// recomputing it on every request.
Schedule::command('servicedesk:refresh-sla')->everyFifteenMinutes()->withoutOverlapping();

// Iterations roll on their own: the current week and month are opened for every
// company, and expired ones are closed with their unfinished work returned to the
// backlog. Early morning, so a closed week is already tidy when the team arrives.
Schedule::command('servicedesk:roll-sprints')->dailyAt('01:00')->withoutOverlapping();
