<?php
/**
 * Boots the application and requests every GET route as a signed-in user,
 * reporting the HTTP status of each. Useful as a fast end-to-end check that the
 * controllers, views and Oracle queries all agree with each other.
 *
 * Usage: php scripts/smoke.php [email]
 */

use App\Models\ComplianceTask;
use App\Models\DevelopmentRequest;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';

$app    = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? 'admin@servicedesk.local';
$user  = User::where('email', $email)->firstOrFail();

echo "Signed in as {$user->email} ({$user->role?->code})\n\n";

$ticket     = Ticket::first();
$devRequest = DevelopmentRequest::first();
$compliance = ComplianceTask::first();
$task       = Task::first();

$urls = [
    '/dashboard',
    '/dashboard/dgm',
    '/dashboard/management',
    '/dashboard/compliance',
    '/dashboard/development',
    '/tickets',
    '/tickets?sla=overdue',
    '/tickets?sla=breached',
    '/tickets/create',
    '/development',
    '/development/create',
    '/compliance',
    '/compliance/create',
    '/tasks',
    '/tasks/create',
    '/notifications',
    '/reports',
    '/reports/tickets',
    '/reports/sla',
    '/reports/compliance',
    '/reports/development',
    '/users',
    '/users/create',
    '/audit',
    '/admin/organizations',
    '/admin/organizations/create',
    '/admin/companies',
    '/admin/companies/create',
    '/admin/locations',
    '/admin/locations/create',
    '/admin/departments',
    '/admin/departments/create',
];

if ($ticket)     { $urls[] = "/tickets/{$ticket->id}"; $urls[] = "/tickets/{$ticket->id}/edit"; }
if ($devRequest) { $urls[] = "/development/{$devRequest->id}"; }
if ($compliance) { $urls[] = "/compliance/{$compliance->id}"; }
if ($task)       { $urls[] = "/tasks/{$task->id}"; }
if ($u = User::skip(1)->first()) { $urls[] = "/users/{$u->id}/edit"; }

$failures = 0;
$denied   = 0;

foreach ($urls as $url) {
    // Each request gets a fresh container state but keeps the authenticated user.
    Auth::login($user);

    $request  = Request::create($url, 'GET');
    $request->setLaravelSession($app['session']->driver());

    try {
        $response = $kernel->handle($request);
        $status   = $response->getStatusCode();
    } catch (\Throwable $e) {
        $status = 'EX: ' . mb_substr($e->getMessage(), 0, 120);
    }

    // 403 is a correct outcome for a route the role is not entitled to; only
    // server errors and exceptions count as failures.
    $ok = in_array($status, [200, 302, 403], true);
    $ok || $failures++;

    if ($status === 403) {
        $denied++;
    }

    printf("%-6s %s\n", is_int($status) ? $status : 'FAIL', $url);

    if (! $ok) {
        echo "        {$status}\n";
    }
}

$total = count($urls);
echo "\n{$total} routes checked, {$denied} correctly denied (403), {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
