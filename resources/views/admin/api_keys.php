<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../API/core/ApiKeyGenerator.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý API Keys - Admin Panel";
$current_page = 'api_keys';

// Handle create API key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_key'])) {
    header('Content-Type: application/json');
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $permissions = json_decode($_POST['permissions'] ?? '[]', true);
    $rate_limit = intval($_POST['rate_limit'] ?? 100);
    $expires_days = intval($_POST['expires_days'] ?? 0);
    $ip_whitelist = !empty($_POST['ip_whitelist']) ? explode(',', trim($_POST['ip_whitelist'])) : [];
    
    if (!$user_id || !$name) {
        echo json_encode(['success' => false, 'message' => 'User ID và tên key là bắt buộc']);
        exit;
    }
    
    // Validate IPs
    foreach ($ip_whitelist as $ip) {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            echo json_encode(['success' => false, 'message' => "IP không hợp lệ: {$ip}"]);
            exit;
        }
    }
    
    $expires_at = null;
    if ($expires_days > 0) {
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
    }
    
    $result = ApiKeyGenerator::create($user_id, $name, [
        'description' => $description,
        'permissions' => $permissions,
        'rate_limit' => $rate_limit,
        'expires_at' => $expires_at,
        'ip_whitelist' => $ip_whitelist
    ]);
    
    echo json_encode($result);
    exit;
}

// Handle revoke/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $key_id = intval($_POST['key_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($action === 'revoke') {
        $result = ApiKeyGenerator::revoke($key_id);
        echo json_encode($result);
    } elseif ($action === 'delete') {
        $result = ApiKeyGenerator::delete($key_id);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Get all API keys
global $VSD;
$api_keys = $VSD->get_list("
    SELECT ak.*, u.username, u.email
    FROM api_keys ak
    JOIN users u ON ak.user_id = u.id
    ORDER BY ak.created_at DESC
");

// Get usage stats
foreach ($api_keys as &$key) {
    $key['recent_usage'] = $VSD->get_row("
        SELECT COUNT(*) as count, MAX(created_at) as last_request
        FROM api_logs 
        WHERE api_key_id = {$key['id']} 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
}

include __DIR__ . '/../includes/admin-header.php';
include __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="drawer-content flex flex-col">
    
    <main class="flex-1 p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2">Quản Lý API Keys</h1>
                <p class="text-base-content/70">Tạo và quản lý API keys cho third-party integrations</p>
            </div>

            <!-- Create Key Form -->
            <div class="card bg-base-100 shadow-xl mb-8">
                <div class="card-body">
                    <h2 class="card-title mb-4">
                        <i class="fa-solid fa-key"></i>
                        Tạo API Key Mới
                    </h2>
                    
                    <form id="createKeyForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Người dùng <span class="text-error">*</span></span>
                                </label>
                                <select name="user_id" class="select select-bordered" required>
                                    <option value="">Chọn người dùng</option>
                                    <?php
                                    $users = $VSD->get_list("SELECT id, username, email FROM users ORDER BY username");
                                    foreach ($users as $user):
                                    ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Tên Key <span class="text-error">*</span></span>
                                </label>
                                <input type="text" name="name" class="input input-bordered" placeholder="Mobile App API" required>
                            </div>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Mô tả</span>
                            </label>
                            <textarea name="description" class="textarea textarea-bordered" placeholder="API key cho mobile app iOS/Android"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Rate Limit (req/hour)</span>
                                </label>
                                <input type="number" name="rate_limit" class="input input-bordered" value="100" min="1" max="10000">
                            </div>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Hết hạn sau (ngày)</span>
                                </label>
                                <input type="number" name="expires_days" class="input input-bordered" value="0" min="0" placeholder="0 = không hết hạn">
                            </div>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">IP Whitelist</span>
                                </label>
                                <input type="text" name="ip_whitelist" class="input input-bordered" placeholder="192.168.1.1, 10.0.0.1 (để trống = tất cả)">
                            </div>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Permissions</span>
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                <?php
                                $available_permissions = [
                                    'documents:read',
                                    'documents:write',
                                    'documents:delete',
                                    'users:read',
                                    'users:write',
                                    'categories:read',
                                    'transactions:read',
                                    '*'
                                ];
                                foreach ($available_permissions as $perm):
                                ?>
                                <label class="label cursor-pointer">
                                    <input type="checkbox" name="permissions[]" value="<?= $perm ?>" class="checkbox checkbox-sm">
                                    <span class="label-text text-sm"><?= $perm ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="card-actions justify-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-plus"></i>
                                Tạo API Key
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- API Keys List -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title mb-4">
                        <i class="fa-solid fa-list"></i>
                        Danh Sách API Keys (<?= count($api_keys) ?>)
                    </h2>
                    
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Người dùng</th>
                                    <th>Tên</th>
                                    <th>Permissions</th>
                                    <th>Rate Limit</th>
                                    <th>Status</th>
                                    <th>Usage (24h)</th>
                                    <th>Last Used</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($api_keys)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-8 text-base-content/50">
                                        Chưa có API key nào
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($api_keys as $key): ?>
                                <tr>
                                    <td><?= $key['id'] ?></td>
                                    <td>
                                        <div class="font-semibold"><?= htmlspecialchars($key['username']) ?></div>
                                        <div class="text-xs opacity-70"><?= htmlspecialchars($key['email']) ?></div>
                                    </td>
                                    <td>
                                        <div class="font-semibold"><?= htmlspecialchars($key['name']) ?></div>
                                        <?php if ($key['description']): ?>
                                        <div class="text-xs opacity-70"><?= htmlspecialchars($key['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            <?php
                                            $perms = json_decode($key['permissions'] ?? '[]', true);
                                            foreach (array_slice($perms, 0, 3) as $perm):
                                            ?>
                                            <span class="badge badge-sm"><?= htmlspecialchars($perm) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($perms) > 3): ?>
                                            <span class="badge badge-sm">+<?= count($perms) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= $key['rate_limit'] ?>/h</td>
                                    <td>
                                        <?php
                                        $status_class = match($key['status']) {
                                            'active' => 'badge-success',
                                            'suspended' => 'badge-warning',
                                            'expired' => 'badge-error',
                                            default => 'badge-neutral'
                                        };
                                        ?>
                                        <span class="badge <?= $status_class ?>"><?= $key['status'] ?></span>
                                        <?php if ($key['expires_at']): ?>
                                        <div class="text-xs opacity-70 mt-1">Exp: <?= date('d/m/Y', strtotime($key['expires_at'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= intval($key['recent_usage']['count'] ?? 0) ?> requests
                                        <?php if ($key['last_used_at']): ?>
                                        <div class="text-xs opacity-70 mt-1">Last: <?= date('H:i', strtotime($key['last_used_at'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($key['last_used_at']): ?>
                                        <?= date('d/m/Y H:i', strtotime($key['last_used_at'])) ?>
                                        <?php else: ?>
                                        <span class="text-base-content/50">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex gap-2">
                                            <button onclick="revokeKey(<?= $key['id'] ?>)" class="btn btn-sm btn-warning" title="Suspend">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                            <button onclick="deleteKey(<?= $key['id'] ?>)" class="btn btn-sm btn-error" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/../includes/admin-footer.php'; ?>
</div>

<!-- Modal: Show Generated API Key (ONLY ONCE) -->
<dialog id="keyModal" class="modal">
    <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg mb-4">
            <i class="fa-solid fa-key text-warning"></i>
            API Key Đã Tạo
        </h3>
        <div class="alert alert-warning mb-4">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span class="text-sm font-bold">LƯU Ý: API key này chỉ hiển thị 1 lần duy nhất!</span>
        </div>
        <div class="form-control mb-4">
            <label class="label">
                <span class="label-text font-semibold">API Key:</span>
            </label>
            <input type="text" id="generatedKey" class="input input-bordered font-mono text-sm" readonly>
            <button onclick="copyKey()" class="btn btn-sm btn-primary mt-2">
                <i class="fa-solid fa-copy"></i>
                Copy Key
            </button>
        </div>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn btn-primary">Đã Lưu</button>
            </form>
        </div>
    </div>
</dialog>

<script>
document.getElementById('createKeyForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const permissions = Array.from(e.target.querySelectorAll('input[name="permissions[]"]:checked')).map(cb => cb.value);
    
    const data = {
        create_key: true,
        user_id: formData.get('user_id'),
        name: formData.get('name'),
        description: formData.get('description'),
        permissions: JSON.stringify(permissions),
        rate_limit: formData.get('rate_limit'),
        expires_days: formData.get('expires_days'),
        ip_whitelist: formData.get('ip_whitelist')
    };
    
    const response = await fetch('api-keys.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
        // Show modal with key (ONLY ONCE!)
        document.getElementById('generatedKey').value = result.api_key;
        document.getElementById('keyModal').showModal();
        
        // Reset form
        e.target.reset();
        
        // Reload page after 5 seconds
        setTimeout(() => location.reload(), 5000);
    } else {
        alert('Lỗi: ' + result.message);
    }
});

function revokeKey(id) {
    if (!confirm('Bạn có chắc muốn suspend API key này?')) return;
    
    fetch('api-keys.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=revoke&key_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Lỗi: ' + data.message);
        }
    });
}

function deleteKey(id) {
    if (!confirm('Bạn có chắc muốn XÓA API key này? Hành động này không thể hoàn tác!')) return;
    
    fetch('api-keys.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete&key_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Lỗi: ' + data.message);
        }
    });
}

function copyKey() {
    const keyInput = document.getElementById('generatedKey');
    keyInput.select();
    document.execCommand('copy');
    alert('Đã copy API key vào clipboard!');
}
</script>

