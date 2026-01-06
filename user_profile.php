<?php
session_start();
require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/premium.php';
require_once 'config/categories.php';

// Get User ID from URL
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($profile_id === 0) {
    header("Location: dashboard.php");
    exit;
}

// Fetch User Info
$profile_user = $VSD->get_row("SELECT id, username, email, avatar, created_at FROM users WHERE id = $profile_id");

if (!$profile_user) {
    include 'includes/head.php';
    include 'includes/navbar.php';
    echo '<div class="container mx-auto p-10 text-center"><h1 class="text-2xl font-bold mb-4">Người dùng không tồn tại</h1><a href="dashboard.php" class="btn btn-primary">Về trang chủ</a></div>';
    include 'includes/footer.php';
    exit;
}

$page_title = htmlspecialchars($profile_user['username']) . " - Hồ sơ công khai";

// Fetch User's Public Documents (Approved only)
$user_docs = $VSD->get_list("
    SELECT d.*, u.username, u.avatar 
    FROM documents d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.user_id = $profile_id AND d.is_public = 1 AND d.status = 'approved'
    ORDER BY d.created_at DESC
");

// Calculate Stats
$total_docs = count($user_docs);
$total_likes_query = $VSD->get_row("
    SELECT COUNT(*) as total 
    FROM document_interactions di
    JOIN documents d ON di.document_id = d.id
    WHERE d.user_id = $profile_id AND di.type = 'like' AND d.is_public = 1 AND d.status = 'approved'
");
$total_likes = intval($total_likes_query['total'] ?? 0);

$current_user_id = getCurrentUserId();
include 'includes/head.php'; 
?>
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.2);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .profile-banner {
        height: 260px;
        background: linear-gradient(135deg, oklch(var(--p)), oklch(var(--s)));
        position: relative;
        overflow: hidden;
    }

    .profile-banner::after {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                          radial-gradient(circle at 80% 80%, rgba(255,255,255,0.05) 0%, transparent 50%);
    }

    .profile-header-card {
        max-width: 1200px;
        margin: -80px auto 40px;
        position: relative;
        z-index: 10;
        padding: 0 24px;
    }

    .profile-info-vsd {
        background: var(--glass-bg);
        backdrop-filter: blur(40px);
        -webkit-backdrop-filter: blur(40px);
        border: 1px solid var(--glass-border);
        border-radius: 3rem;
        padding: 48px;
        box-shadow: 0 40px 100px -20px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .profile-avatar-vsd {
        width: 160px;
        height: 160px;
        margin-top: -128px;
        margin-bottom: 24px;
        border-radius: 3.5rem;
        padding: 8px;
        background: oklch(var(--b1));
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2);
        position: relative;
    }

    .profile-avatar-vsd img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: calc(3.5rem - 8px);
    }

    .profile-avatar-vsd .placeholder-icon {
        width: 100%;
        height: 100%;
        background: oklch(var(--bc) / 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
        color: oklch(var(--p) / 0.3);
        border-radius: calc(3.5rem - 8px);
    }

    .profile-name-vsd {
        font-size: 2.5rem;
        font-weight: 900;
        letter-spacing: -0.05em;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .premium-badge-vsd {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 100px;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        box-shadow: 0 10px 20px -5px rgba(251, 191, 36, 0.4);
    }

    .profile-stats-vsd {
        display: flex;
        gap: 40px;
        margin-top: 32px;
        padding-top: 32px;
        border-top: 1px solid oklch(var(--bc) / 0.05);
    }

    .stat-vsd-item {
        text-align: center;
    }

    .stat-vsd-value {
        font-size: 1.75rem;
        font-weight: 900;
        color: oklch(var(--p));
        line-height: 1;
    }

    .stat-vsd-label {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: oklch(var(--bc) / 0.4);
        margin-top: 4px;
    }

    /* Document Grid Layout */
    .docs-section-vsd {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 24px 60px;
    }

    .section-title-vsd {
        font-size: 1.5rem;
        font-weight: 900;
        letter-spacing: -0.02em;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .section-title-vsd::after {
        content: '';
        flex: 1;
        height: 1px;
        background: oklch(var(--bc) / 0.05);
    }

    .doc-grid-vsd {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 32px;
    }

    .doc-card-vsd {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 2rem;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        cursor: pointer;
    }

    .doc-card-vsd:hover {
        transform: translateY(-12px);
        box-shadow: 0 30px 60px -12px rgba(0,0,0,0.1);
        border-color: oklch(var(--p) / 0.3);
    }

    .doc-preview-vsd {
        height: 200px;
        background: oklch(var(--bc) / 0.03);
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .doc-preview-vsd i {
        font-size: 4rem;
        color: oklch(var(--bc) / 0.1);
    }

    .doc-preview-vsd img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .doc-badge-vsd {
        position: absolute;
        bottom: 12px;
        right: 12px;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(10px);
        color: white;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: 0.1em;
    }

    .doc-content-vsd {
        padding: 24px;
    }

    .doc-title-vsd {
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.4;
        margin-bottom: 16px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.8em;
    }

    .doc-meta-vsd {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 16px;
        border-top: 1px solid oklch(var(--bc) / 0.05);
    }

    .doc-type-badge {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: oklch(var(--p));
        background: oklch(var(--p) / 0.1);
        padding: 4px 10px;
        border-radius: 100px;
    }

    .doc-likes-vsd {
        font-size: 11px;
        font-weight: 900;
        color: oklch(var(--bc) / 0.4);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .doc-likes-vsd i {
        color: oklch(var(--p));
    }
</style>

<body class="bg-base-100">
    <?php include 'includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include 'includes/navbar.php'; ?>

        <main class="flex-1">
            <!-- Banner -->
            <div class="profile-banner"></div>

            <!-- Profile Info Section -->
            <div class="profile-header-card">
                <div class="profile-info-vsd animate-in fade-in zoom-in duration-700">
                    <div class="profile-avatar-vsd">
                        <?php if (!empty($profile_user['avatar']) && file_exists('uploads/avatars/' . $profile_user['avatar'])): ?>
                            <img src="uploads/avatars/<?= htmlspecialchars($profile_user['avatar']) ?>" alt="<?= htmlspecialchars($profile_user['username']) ?>" />
                        <?php else: ?>
                            <div class="placeholder-icon">
                                <i class="fa-solid fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h1 class="profile-name-vsd">
                        <?= htmlspecialchars($profile_user['username']) ?>
                        <?php if (isPremium($profile_id)): ?>
                            <div class="premium-badge-vsd">
                                <i class="fa-solid fa-crown mr-1"></i> Premium
                            </div>
                        <?php endif; ?>
                    </h1>

                    <div class="flex items-center gap-4 text-xs font-black uppercase tracking-widest opacity-40">
                        <span><i class="fa-regular fa-calendar mr-1"></i> Thành viên từ <?= date('m/Y', strtotime($profile_user['created_at'])) ?></span>
                    </div>

                    <div class="profile-stats-vsd">
                        <div class="stat-vsd-item">
                            <div class="stat-vsd-value"><?= number_format(intval($total_docs)) ?></div>
                            <div class="stat-vsd-label">Tài liệu</div>
                        </div>
                        <div class="stat-vsd-item">
                            <div class="stat-vsd-value"><?= number_format(intval($total_likes)) ?></div>
                            <div class="stat-vsd-label">Tổng lượt thích</div>
                        </div>
                    </div>

                    <?php if ($current_user_id === $profile_id): ?>
                        <div class="mt-8">
                            <a href="profile.php" class="btn btn-primary btn-sm rounded-xl px-6 font-black tracking-widest text-[10px]">
                                <i class="fa-solid fa-pen-to-square mr-1"></i> CHỈNH SỬA HỒ SƠ
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="docs-section-vsd">
                <h2 class="section-title-vsd">
                    <i class="fa-solid fa-layer-group text-primary"></i>
                    Tài liệu đã chia sẻ
                </h2>

                <?php if ($total_docs > 0): ?>
                    <div class="doc-grid-vsd">
                        <?php foreach ($user_docs as $doc): 
                            $doc_id = $doc['id'];
                            $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                            $file_path = "uploads/" . $doc['file_name'];
                            
                            $likes = intval($VSD->num_rows("SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='like'") ?: 0);
                            
                            $doc_category = getDocumentCategoryWithNames($doc_id);
                            $doc_type = $doc_category ? htmlspecialchars($doc_category['doc_type_name']) : 'Khác';
                            
                            $doc_name_short = preg_replace('/\.[^.]+$/', '', $doc['original_name']);
                            
                            $thumbnail = $doc['thumbnail'] ?? null;
                            $thumbnail_path = 'uploads/' . $thumbnail;
                            $has_thumbnail = $thumbnail && file_exists($thumbnail_path);
                            $page_count = intval($doc['total_pages'] > 0 ? $doc['total_pages'] : 1);
                        ?>
                            <div class="doc-card-vsd animate-in fade-in slide-in-from-bottom-4 duration-500" onclick="window.location.href='view.php?id=<?= $doc_id ?>'">
                                <div class="doc-preview-vsd">
                                    <?php if ($has_thumbnail): ?>
                                        <img src="<?= $thumbnail_path ?>" alt="Thumbnail" />
                                    <?php else: ?>
                                        <i class="fa-solid fa-file-pdf"></i>
                                    <?php endif; ?>
                                    <div class="doc-badge-vsd"><?= $page_count ?> TRANG</div>
                                </div>
                                <div class="doc-content-vsd">
                                    <h3 class="doc-title-vsd"><?= htmlspecialchars($doc_name_short) ?></h3>
                                    <div class="doc-meta-vsd">
                                        <span class="doc-type-badge"><?= htmlspecialchars($doc_type) ?></span>
                                        <div class="doc-likes-vsd">
                                            <i class="fa-solid fa-thumbs-up"></i>
                                            <?= number_format($likes) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-20 bg-base-200/30 rounded-[3rem] border border-dashed border-base-content/10">
                        <i class="fa-solid fa-folder-open text-6xl opacity-10 mb-6"></i>
                        <h3 class="text-xl font-black opacity-30 uppercase tracking-widest">Chưa có tài liệu nào</h3>
                        <p class="text-sm font-bold opacity-20 mt-2">Người dùng này chưa chia sẻ tài liệu công khai nào.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include 'includes/footer.php'; ?>
    </div>
</body>
</html>
