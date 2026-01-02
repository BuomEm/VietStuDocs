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
    header("Location: index.php");
    exit;
}

// Fetch User Info
$profile_user = $VSD->get_row("SELECT id, username, email, avatar, created_at FROM users WHERE id = $profile_id");

if (!$profile_user) {
    // User not found
    include 'includes/head.php';
    include 'includes/navbar.php';
    echo '<div class="container mx-auto p-10 text-center"><h1 class="text-2xl font-bold mb-4">Người dùng không tồn tại</h1><a href="index.php" class="btn btn-primary">Về trang chủ</a></div>';
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
$total_likes = $total_likes_query['total'] ?? 0;

$current_user_id = getCurrentUserId();
?>
<?php include 'includes/head.php'; ?>

<!-- PDF.js & DOCX Preview Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.4/dist/docx-preview.min.js"></script>

    <?php include 'includes/sidebar.php'; ?>
    <div class="drawer-content flex flex-col bg-base-100">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 w-full max-w-7xl mx-auto p-6">
        <!-- Profile Header -->
        <div class="card bg-base-100 shadow-xl mb-8 overflow-hidden transform transition-all hover:shadow-2xl">
            <!-- Cover Photo / Banner (Gradient) -->
            <div class="h-48 bg-gradient-to-r from-primary/80 to-secondary/80 w-full relative">
                <div class="absolute inset-0 bg-grid-white/[0.1] bg-[length:20px_20px]"></div>
            </div>
            
            <div class="card-body px-8 pt-0 pb-8 relative">
                <div class="flex flex-col md:flex-row gap-6 items-start">
                    <!-- Avatar container - overlapping the banner -->
                    <div class="-mt-16 flex-shrink-0">
                        <div class="avatar">
                            <div class="w-32 h-32 rounded-full ring-4 ring-base-100 shadow-2xl bg-base-100 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($profile_user['avatar']) && file_exists('uploads/avatars/' . $profile_user['avatar'])): ?>
                                    <img src="uploads/avatars/<?= htmlspecialchars($profile_user['avatar']) ?>" alt="<?= htmlspecialchars($profile_user['username']) ?>" class="object-cover" />
                                <?php else: ?>
                                    <div class="bg-primary/10 w-full h-full flex items-center justify-center">
                                        <i class="fa-solid fa-circle-user text-6xl text-primary/50"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Info -->
                    <div class="flex-1 pt-4 mt-2 md:mt-0">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div>
                                <h1 class="text-3xl font-extrabold text-base-content flex items-center gap-2">
                                    <?= htmlspecialchars($profile_user['username']) ?>
                                    <?php if (isPremium($profile_id)): ?>
                                        <div class="badge badge-warning gap-1 shadow-sm" title="Thành viên Premium">
                                            <i class="fa-solid fa-crown text-xs"></i> Premium
                                        </div>
                                    <?php endif; ?>
                                </h1>
                                <p class="text-base-content/60 text-sm mt-1 flex items-center gap-2">
                                    <i class="fa-regular fa-calendar"></i>
                                    Tham gia từ <?= date('d/m/Y', strtotime($profile_user['created_at'])) ?>
                                </p>
                            </div>
                            
                            <!-- Action Buttons (if looking at own profile) -->
                            <?php if ($current_user_id === $profile_id): ?>
                                <a href="profile.php" class="btn btn-outline btn-primary btn-sm gap-2">
                                    <i class="fa-solid fa-pen-to-square"></i> Chỉnh sửa hồ sơ
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stats -->
                        <div class="flex flex-wrap gap-4 mt-6">
                            <div class="stats shadow-sm border border-base-200 bg-base-100/50">
                                <div class="stat px-6 py-2">
                                    <div class="stat-figure text-primary">
                                        <i class="fa-regular fa-file-lines text-2xl"></i>
                                    </div>
                                    <div class="stat-title text-xs font-semibold uppercase tracking-wider">Tài liệu</div>
                                    <div class="stat-value text-primary text-2xl"><?= $total_docs ?></div>
                                </div>
                            </div>
                            
                            <div class="stats shadow-sm border border-base-200 bg-base-100/50">
                                <div class="stat px-6 py-2">
                                    <div class="stat-figure text-secondary">
                                        <i class="fa-regular fa-heart text-2xl"></i>
                                    </div>
                                    <div class="stat-title text-xs font-semibold uppercase tracking-wider">Lượt thích</div>
                                    <div class="stat-value text-secondary text-2xl"><?= $total_likes ?></div>
                                </div>
                            </div>
                            
                            <!-- Placeholder for future stats like Reviews -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Section -->
        <div>
            <div class="flex items-center justify-between mb-6 pb-2 border-b-2 border-primary/10">
                <h2 class="text-2xl font-bold flex items-center gap-3">
                    <span class="bg-primary/10 p-2 rounded-lg text-primary">
                        <i class="fa-solid fa-layer-group"></i>
                    </span>
                    Tài liệu đã chia sẻ
                </h2>
                <!-- Filter/Sort could go here -->
            </div>

            <?php if ($total_docs > 0): ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-6">
                    <?php foreach ($user_docs as $doc): 
                        $doc_id = $doc['id'];
                        $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                        $file_path = "uploads/" . $doc['file_name'];
                        
                        // Likes Stats
                        $likes = $VSD->num_rows("SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='like'");
                        $dislikes = $VSD->num_rows("SELECT * FROM document_interactions WHERE document_id=$doc_id AND type='dislike'");
                        $total_interactions = $likes + $dislikes;
                        $like_percentage = $total_interactions > 0 ? round(($likes / $total_interactions) * 100) : 0;
                        
                        // Category & Type
                        $doc_category = getDocumentCategoryWithNames($doc_id);
                        $doc_type = $doc_category ? htmlspecialchars($doc_category['doc_type_name']) : 'Khác';
                        
                        // Previews
                        $is_pdf = ($ext === 'pdf');
                        $is_docx = in_array($ext, ['doc', 'docx']);
                        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        $preview_id = 'preview_profile_' . $doc_id;
                        $page_count_id = 'pagecount_profile_' . $doc_id;
                        
                        $doc_name_short = preg_replace('/\.[^.]+$/', '', $doc['original_name']);
                        
                        $thumbnail = $doc['thumbnail'] ?? null;
                        $thumbnail_path = 'uploads/' . $thumbnail;
                        $has_thumbnail = $thumbnail && file_exists($thumbnail_path);
                        $page_count = $doc['total_pages'] > 0 ? $doc['total_pages'] : 1;
                        
                        // Simple content generation logic reused from dashboard
                        $preview_html = '';
                        if ($has_thumbnail) {
                            $preview_html = '<img src="' . $thumbnail_path . '" class="w-full h-full object-cover" />';
                        } elseif ($is_pdf) {
                            $preview_html = '<div class="flex items-center justify-center h-full text-base-content/20"><i class="fa-regular fa-file-pdf text-6xl"></i></div>';
                        } elseif ($is_docx) {
                             $preview_html = '<div class="docx-preview-container w-full h-full bg-white"></div>';
                        } elseif ($is_image) {
                             $preview_html = '<img src="' . $file_path . '" class="w-full h-full object-cover" />';
                        } else {
                             $preview_html = '<div class="flex items-center justify-center h-full text-base-content/20"><i class="fa-regular fa-file text-6xl"></i></div>';
                        }
                    ?>
                        <div class="card bg-base-100 shadow-sm hover:shadow-xl transition-all duration-300 cursor-pointer group rounded-xl border border-base-200 overflow-hidden" onclick="window.location.href='view.php?id=<?= $doc_id ?>'">
                            <!-- Preview Area -->
                            <figure class="relative h-48 bg-base-200/50 group-hover:bg-base-200 transition-colors">
                                <div class="w-full h-full" id="<?= $preview_id ?>">
                                    <?= $preview_html ?>
                                </div>
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors"></div>
                                <div class="badge badge-primary absolute bottom-2 right-2 shadow-sm font-mono text-xs" id="<?= $page_count_id ?>"><?= $page_count ?> trang</div>
                            </figure>
                            
                            <!-- Content -->
                            <div class="card-body p-4 gap-3">
                                <h3 class="card-title text-sm font-bold line-clamp-2 leading-snug min-h-[2.5em] group-hover:text-primary transition-colors">
                                    <?= htmlspecialchars($doc_name_short) ?>
                                </h3>
                                
                                <div class="flex items-center justify-between text-xs text-base-content/60 border-t border-base-100 pt-3 mt-1">
                                    <span class="flex items-center gap-1.5 bg-base-200/50 px-2 py-1 rounded max-w-[60%] truncate">
                                        <i class="fa-solid fa-tag text-[10px] text-primary"></i>
                                        <?= htmlspecialchars($doc_type) ?>
                                    </span>
                                    <div class="flex items-center gap-3">
                                         <div class="flex items-center gap-1 text-success font-bold" title="Lượt thích">
                                            <i class="fa-solid fa-thumbs-up"></i>
                                            <?= $likes ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scripts for specific file types if no thumbnail -->
                        <?php if (!$has_thumbnail && $is_pdf): ?>
                            <script>
                                // Simple PDF render fallback if needed, but thumbnails are preferred
                            </script>
                        <?php endif; ?>
                        
                        <?php if (!$has_thumbnail && $is_docx): ?>
                            <script>
                                (async function() {
                                    const container = document.querySelector('#<?= $preview_id ?> .docx-preview-container');
                                    if(!container) return;
                                    try {
                                        const response = await fetch('<?= $file_path ?>');
                                        const blob = await response.blob();
                                        await docx.renderAsync(blob, container, null, { 
                                            inWrapper: false, 
                                            ignoreWidth: false, 
                                            ignoreHeight: false 
                                        });
                                    } catch(e) { console.error('Docx fail', e); }
                                })();
                            </script>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center py-16 bg-base-100 rounded-3xl border-2 border-dashed border-base-200">
                    <div class="bg-base-200/50 w-20 h-20 rounded-full flex items-center justify-center mb-4">
                        <i class="fa-regular fa-folder-open text-4xl text-base-content/30"></i>
                    </div>
                    <h3 class="text-lg font-bold">Chưa có tài liệu nào</h3>
                    <p class="text-base-content/60 text-sm">Người dùng này chưa chia sẻ tài liệu công khai nào.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    </div> <!-- Close drawer-content -->
</div> <!-- Close drawer -->
