<?php

/**
 * Concurrency check for the SF / PF number generator.
 *
 *     php scripts/check-concurrency.php [workers=8] [records-per-worker=25] [shared-forms=20]
 *
 * It works on a THROW-AWAY SQLite file in the system temp directory (never on
 * your real database):
 *
 *   1. creates and migrates the temporary database,
 *   2. starts N separate PHP processes at (almost) the same instant,
 *   3. phase A - every process creates SF and PF records through the real
 *      application code (App\Services\FormRecordService),
 *   4. phase B - every process submits the SAME form tokens (a "double click"
 *      raced by many processes at once),
 *   5. verifies: no duplicate numbers, no gaps, counters advanced by exactly the
 *      number of records created, one record per shared token, no errors.
 *
 * Exit code 0 = everything held, 1 = a problem was found.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

if (($argv[1] ?? '') === 'worker') {
    exit(runWorker($root, (int) $argv[2], (int) $argv[3], (int) $argv[4], (float) $argv[5]));
}

exit(runParent($root, max(2, (int) ($argv[1] ?? 8)), max(1, (int) ($argv[2] ?? 25)), max(1, (int) ($argv[3] ?? 20))));

// --------------------------------------------------------------------------------------------

function runParent(string $root, int $workers, int $perWorker, int $shared): int
{
    $db = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sfpf-concurrency-'.bin2hex(random_bytes(4)).'.sqlite';
    touch($db);

    $env = array_merge(getenv(), [
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $db,
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'LOG_CHANNEL' => 'null',
        'SF_START_NUMBER' => '26090119',
        'PF_START_NUMBER' => '26090138',
    ]);

    echo "Temporary database : $db\n";
    echo "Workers            : $workers processes, $perWorker SF + $perWorker PF each, plus $shared shared (raced) forms\n";

    try {
        $migrate = new_process([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], $root, $env);
        [$code, $out, $err] = finish($migrate);
        if ($code !== 0) {
            echo "Migration failed:\n$out\n$err\n";

            return 1;
        }

        $startAt = microtime(true) + 3 + $workers * 0.4; // everybody boots first, then starts together
        $procs = [];
        for ($i = 1; $i <= $workers; $i++) {
            $procs[$i] = new_process([PHP_BINARY, __FILE__, 'worker', (string) $i, (string) $perWorker, (string) $shared, (string) $startAt], $root, $env);
        }

        $results = [];
        foreach ($procs as $i => $proc) {
            [$code, $out, $err] = finish($proc);
            $decoded = json_decode($out, true);
            if ($code !== 0 || ! is_array($decoded)) {
                echo "Worker $i crashed (exit $code):\n$out\n$err\n";

                return 1;
            }
            $results[$i] = $decoded;
        }

        return verify($db, $results, $workers, $perWorker, $shared);
    } finally {
        foreach ([$db, $db.'-wal', $db.'-shm', $db.'-journal'] as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}

function verify(string $db, array $results, int $workers, int $perWorker, int $shared): int
{
    $problems = [];
    $sfStart = 26090119;
    $pfStart = 26090138;

    foreach ($results as $i => $r) {
        foreach ($r['errors'] as $error) {
            $problems[] = "worker $i: $error";
        }
    }

    // Phase B: every worker must have been handed the SAME number for the SAME shared token.
    $sharedPf = [];
    for ($k = 0; $k < $shared; $k++) {
        $seen = array_unique(array_map(fn ($r) => $r['shared'][$k] ?? null, $results));
        if (count($seen) !== 1 || in_array(null, $seen, true)) {
            $problems[] = "shared form #$k was given different numbers: ".json_encode(array_values($seen));
        }
    }

    $expectedSf = $workers * $perWorker + $shared;
    $expectedPf = $workers * $perWorker;

    $pdo = new PDO('sqlite:'.$db);
    $sfNumbers = $pdo->query('select sf_number from sf_records order by sf_number')->fetchAll(PDO::FETCH_COLUMN);
    $pfNumbers = $pdo->query('select pf_number from pf_records order by pf_number')->fetchAll(PDO::FETCH_COLUMN);
    $sfNext = (int) $pdo->query("select next_number from number_sequences where key = 'SF'")->fetchColumn();
    $pfNext = (int) $pdo->query("select next_number from number_sequences where key = 'PF'")->fetchColumn();
    $sfTokens = (int) $pdo->query('select count(distinct submission_token) from sf_records')->fetchColumn();

    foreach ([['SF', $sfNumbers, $sfStart, $expectedSf, $sfNext], ['PF', $pfNumbers, $pfStart, $expectedPf, $pfNext]] as [$label, $numbers, $start, $expected, $next]) {
        $numbers = array_map('intval', $numbers);
        if (count($numbers) !== $expected) {
            $problems[] = "$label: expected $expected records, database has ".count($numbers);
        }
        if (count(array_unique($numbers)) !== count($numbers)) {
            $problems[] = "$label: DUPLICATE numbers found";
        }
        foreach ($numbers as $index => $number) {
            if ($number !== $start + $index) {
                $problems[] = "$label: numbering is not gap-free (position $index holds $number, expected ".($start + $index).')';
                break;
            }
        }
        if ($next !== $start + $expected) {
            $problems[] = "$label: counter says next=$next, expected ".($start + $expected);
        }
    }

    if ($sfTokens !== $expectedSf) {
        $problems[] = "SF: expected $expectedSf distinct submission tokens, found $sfTokens";
    }

    echo "\nSF records         : ".count($sfNumbers)." (expected $expectedSf), range ".($sfNumbers[0] ?? '-').' .. '.($sfNumbers[count($sfNumbers) - 1] ?? '-')."\n";
    echo 'PF records         : '.count($pfNumbers)." (expected $expectedPf), range ".($pfNumbers[0] ?? '-').' .. '.($pfNumbers[count($pfNumbers) - 1] ?? '-')."\n";
    echo "Counters           : SF next=$sfNext, PF next=$pfNext\n";

    if ($problems === []) {
        echo "\nPASS - no duplicates, no gaps, one record per raced form, counters exact.\n";

        return 0;
    }

    echo "\nFAIL\n - ".implode("\n - ", $problems)."\n";

    return 1;
}

// --------------------------------------------------------------------------------------------

function runWorker(string $root, int $id, int $perWorker, int $shared, float $startAt): int
{
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $service = $app->make(App\Services\FormRecordService::class);
    $own = new App\Models\User(['username' => 'worker'.$id]);        // not persisted: only the name is used
    $sharedUser = new App\Models\User(['username' => 'shared']);

    while (microtime(true) < $startAt) {
        usleep(300);
    }

    $result = ['errors' => [], 'shared' => []];

    // Phase A: unique forms, maximum contention on the two counters.
    for ($n = 0; $n < $perWorker; $n++) {
        try {
            $service->createSf($own, ['vnid' => "W$id-$n", 'customer_name' => "Customer $id", 'service' => 'Stress test'], (string) Illuminate\Support\Str::uuid());
            $service->createPf($own, ['project_name' => "Project $id-$n"], (string) Illuminate\Support\Str::uuid());
        } catch (Throwable $e) {
            $result['errors'][] = get_class($e).': '.$e->getMessage();
        }
    }

    // Phase B: every process submits the same tokens (same user) - only one record may result per token.
    for ($k = 0; $k < $shared; $k++) {
        $hash = md5("shared-form-$k");
        $token = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);

        try {
            $record = $service->createSf($sharedUser, ['vnid' => "S$k", 'customer_name' => 'Shared', 'service' => 'Race'], $token);
            $result['shared'][$k] = $record->sf_number;
        } catch (Throwable $e) {
            $result['errors'][] = get_class($e).': '.$e->getMessage();
        }
    }

    echo json_encode($result);

    return 0;
}

function new_process(array $command, string $cwd, array $env): array
{
    $proc = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);

    if (! is_resource($proc)) {
        fwrite(STDERR, "Could not start: ".implode(' ', $command)."\n");
        exit(1);
    }

    return [$proc, $pipes];
}

function finish(array $process): array
{
    [$proc, $pipes] = $process;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($proc), $out, $err];
}
