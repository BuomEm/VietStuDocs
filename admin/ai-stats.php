<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/categories.php';
require_once __DIR__ . '/../includes/ai_review_handler.php';

redirectIfNotAdmin();

$page_title = "Thống kê AI";
$admin_active_page = 'ai-stats';

$handler = new AIReviewHandler($VSD);

// Database Stats
$stats_db = [
    'total_reviews' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_status = 'completed'"),
    'avg_score' => $VSD->get_row("SELECT AVG(ai_score) as avg FROM documents WHERE ai_status = 'completed'")['avg'] ?? 0,
    'decision_approved' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_decision IN ('APPROVED', 'Chấp Nhận')"),
    'decision_conditional' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_decision IN ('CONDITIONAL', 'Xem Xét')"),
    'decision_rejected' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_decision IN ('REJECTED', 'Từ Chối')"),
    'total_vsd_value' => $VSD->get_row("SELECT SUM(ai_price) as total FROM documents WHERE ai_status = 'completed'")['total'] ?? 0
];

// Usage by Category (Chart Data)
$cat_stats = $VSD->get_results("SELECT d.ai_decision, COUNT(*) as count FROM documents d WHERE d.ai_status = 'completed' GROUP BY d.ai_decision");

// Review Trend (Last 7 days)
$trend_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_status = 'completed' AND DATE(created_at) = '$date'");
    $trend_data[] = [
        'date' => date('d/m', strtotime($date)),
        'count' => $count
    ];
}

include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-secondary"></i>
                    Thống kê AI Review
                </h1>
                <p class="text-base-content/60 text-sm mt-1">Phân tích hiệu quả và chất lượng của hệ thống trí tuệ nhân tạo</p>
            </div>
            <div class="badge badge-outline gap-2 p-4">
                <i class="fa-solid fa-calendar text-xs opacity-50"></i>
                Tháng <?= date('m/Y') ?>
            </div>
        </div>

        <!-- Top Widgets -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="stats shadow bg-base-100 overflow-hidden border border-base-200">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <i class="fa-solid fa-robot text-3xl opacity-20"></i>
                    </div>
                    <div class="stat-title text-xs uppercase font-bold opacity-50">Tổng lượt duyệt</div>
                    <div class="stat-value text-primary text-2xl"><?= number_format($stats_db['total_reviews']) ?></div>
                    <div class="stat-desc mt-1">Tài liệu đã qua xử lý</div>
                </div>
            </div>

            <div class="stats shadow bg-base-100 overflow-hidden border border-base-200">
                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <i class="fa-solid fa-star text-3xl opacity-20"></i>
                    </div>
                    <div class="stat-title text-xs uppercase font-bold opacity-50">Điểm trung bình</div>
                    <div class="stat-value text-secondary text-2xl"><?= round($stats_db['avg_score'], 1) ?></div>
                    <div class="stat-desc mt-1">Trên thang điểm 100</div>
                </div>
            </div>

            <div class="stats shadow bg-base-100 overflow-hidden border border-base-200">
                <div class="stat">
                    <div class="stat-figure text-warning">
                        <i class="fa-solid fa-coins text-3xl opacity-20"></i>
                    </div>
                    <div class="stat-title text-xs uppercase font-bold opacity-50">Tổng giá trị định giá</div>
                    <div class="stat-value text-warning text-2xl"><?= number_format($stats_db['total_vsd_value']) ?></div>
                    <div class="stat-desc mt-1">Đơn vị VSD Coin</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Decision Pie Chart Placeholder -->
            <div class="card bg-base-100 shadow-sm border border-base-200 col-span-1">
                <div class="card-body">
                    <h3 class="font-bold text-lg mb-4">Tỷ lệ Quyết định</h3>
                    <div class="space-y-4">
                        <?php 
                        $total = max(1, $stats_db['decision_approved'] + $stats_db['decision_conditional'] + $stats_db['decision_rejected']);
                        $data = [
                            ['label' => 'Chấp Nhận', 'count' => $stats_db['decision_approved'], 'color' => 'bg-success'],
                            ['label' => 'Xem Xét', 'count' => $stats_db['decision_conditional'], 'color' => 'bg-warning'],
                            ['label' => 'Từ Chối', 'count' => $stats_db['decision_rejected'], 'color' => 'bg-error'],
                        ];
                        foreach($data as $d): 
                            $pct = round(($d['count'] / $total) * 100, 1);
                        ?>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs font-bold uppercase">
                                <span><?= $d['label'] ?></span>
                                <span class="opacity-60"><?= $d['count'] ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="w-full bg-base-200 rounded-full h-2 overflow-hidden">
                                <div class="<?= $d['color'] ?> h-full transition-all" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-8 p-4 bg-primary/5 rounded-xl border border-primary/10">
                        <div class="text-[10px] uppercase font-bold opacity-40 mb-2">Thông tin hệ thống</div>
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between text-xs">
                                <span class="opacity-60">OpenAI Model:</span>
                                <span class="font-mono"><?= getSetting('ai_model_judge', 'gpt-4o') ?></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="opacity-60">Cấu hình giá:</span>
                                <span class="badge badge-xs badge-success">Sẵn sàng</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Usage Log -->
            <div class="card bg-base-100 shadow-sm border border-base-200 lg:col-span-2">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-lg">Hoạt động mới nhất</h3>
                        <a href="ai-queue.php" class="btn btn-ghost btn-xs">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="table table-sm w-full">
                            <thead>
                                <tr class="bg-base-200/30">
                                    <th>Thời gian</th>
                                    <th>Cấp học</th>
                                    <th>Quyết định</th>
                                    <th>Điểm</th>
                                    <th>Giá (VSD)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $recent = $VSD->get_results("SELECT id, ai_status, ai_decision, ai_score, ai_price, created_at FROM documents WHERE ai_status='completed' ORDER BY created_at DESC LIMIT 8");
                                if (empty($recent)): ?>
                                    <tr><td colspan="5" class="text-center py-8 opacity-40 italic">Chưa có hoạt động nào</td></tr>
                                <?php else: foreach($recent as $r): ?>
                                    <tr>
                                        <?php 
                                        $cat_info = getDocumentCategoryWithNames($r['id']);
                                        $level_name = $cat_info['education_level_name'] ?? '---';
                                        ?>
                                        <td class="text-[10px] opacity-60"><?= date('H:i - d/m', strtotime($r['created_at'])) ?></td>
                                        <td class="font-medium text-xs truncate max-w-[120px]"><?= $level_name ?></td>
                                        <td>
                                            <?php
                                            $decision_label = $r['ai_decision'];
                                            $decision_class = '';
                                            if ($decision_label === 'APPROVED' || $decision_label === 'Chấp Nhận') {
                                                $decision_label = 'Chấp Nhận';
                                                $decision_class = 'bg-success/20 text-success border-success/30';
                                            } elseif ($decision_label === 'CONDITIONAL' || $decision_label === 'Xem Xét') {
                                                $decision_label = 'Xem Xét';
                                                $decision_class = 'bg-warning/20 text-warning border-warning/30';
                                            } elseif ($decision_label === 'REJECTED' || $decision_label === 'Từ Chối') {
                                                $decision_label = 'Từ Chối';
                                                $decision_class = 'bg-error/20 text-error border-error/30';
                                            }
                                            ?>
                                            <span class="px-2 py-0.5 rounded-md text-[9px] font-bold border uppercase tracking-wider <?= $decision_class ?> inline-block">
                                                <?= $decision_label ?>
                                            </span>
                                        </td>
                                        <td class="font-mono font-bold"><?= $r['ai_score'] ?></td>
                                        <td class="text-primary font-bold"><i class="fa-solid fa-coins text-[9px] text-warning mr-0.5"></i> <?= $r['ai_price'] ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-6 h-[250px] w-full relative">
                        <canvas id="reviewTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('reviewTrendChart').getContext('2d');
    
    // Gradient for the chart
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(150, 241, 176, 0.4)');
    gradient.addColorStop(1, 'rgba(150, 241, 176, 0)');

    const trendData = <?= json_encode($trend_data) ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.date),
            datasets: [{
                label: 'Lượt duyệt AI',
                data: trendData.map(d => d.count),
                borderColor: '#96f1b0',
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#96f1b0',
                pointBorderColor: '#fff',
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#1d232a',
                    titleColor: '#96f1b0',
                    bodyColor: '#fff',
                    borderColor: '#96f1b020',
                    borderWidth: 1,
                    padding: 10,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.parsed.y + ' tài liệu';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1,
                        color: 'rgba(255, 255, 255, 0.4)',
                        font: {
                            size: 10
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.4)',
                        font: {
                            size: 10
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
