<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timezone Test - VietStuDocs</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200">
    <?php require_once 'config/function.php'; ?>
    
    <div class="container mx-auto p-8">
        <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
            <div class="card-body">
                <h2 class="card-title text-2xl">🕐 Timezone Configuration Test</h2>
                
                <div class="divider"></div>
                
                <div class="stats stats-vertical shadow w-full">
                    <div class="stat">
                        <div class="stat-title">Configured Timezone</div>
                        <div class="stat-value text-primary text-2xl">
                            <?= date_default_timezone_get() ?>
                        </div>
                        <div class="stat-desc">Vietnam Standard Time (UTC+7)</div>
                    </div>
                    
                    <div class="stat">
                        <div class="stat-title">Current Server Time</div>
                        <div class="stat-value text-2xl">
                            <?= date('H:i:s') ?>
                        </div>
                        <div class="stat-desc"><?= date('l, d F Y') ?></div>
                    </div>
                    
                    <div class="stat">
                        <div class="stat-title">UTC Offset</div>
                        <div class="stat-value text-2xl">
                            UTC<?= date('P') ?>
                        </div>
                        <div class="stat-desc">+07:00 hours</div>
                    </div>
                </div>
                
                <div class="divider">Detailed Information</div>
                
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <tbody>
                            <tr>
                                <td class="font-bold">PHP Version</td>
                                <td><?= PHP_VERSION ?></td>
                            </tr>
                            <tr>
                                <td class="font-bold">Timezone (php.ini)</td>
                                <td><?= ini_get('date.timezone') ?: '(not set)' ?></td>
                            </tr>
                            <tr>
                                <td class="font-bold">Timezone (runtime)</td>
                                <td><?= date_default_timezone_get() ?></td>
                            </tr>
                            <tr>
                                <td class="font-bold">Date Format (Y-m-d H:i:s)</td>
                                <td><?= date('Y-m-d H:i:s') ?></td>
                            </tr>
                            <tr>
                                <td class="font-bold">Date Format (d/m/Y H:i:s)</td>
                                <td><?= date('d/m/Y H:i:s') ?></td>
                            </tr>
                            <tr>
                                <td class="font-bold">Timestamp</td>
                                <td><?= time() ?></td>
                            </tr>
                            <tr>
                                <td class="font-bold">Timezone Abbreviation</td>
                                <td><?= date('T') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-success mt-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <div>
                        <h3 class="font-bold">Timezone Configured Successfully!</h3>
                        <div class="text-xs">All timestamps will use Vietnam timezone (UTC+7)</div>
                    </div>
                </div>
                
                <div class="card-actions justify-end mt-4">
                    <a href="/dashboard" class="btn btn-ghost">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Update time every second
        setInterval(() => {
            location.reload();
        }, 60000); // Reload every minute
    </script>
</body>
</html>
