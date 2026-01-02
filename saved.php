<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
}

require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/premium.php';

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$current_page = 'saved';

// Handle unsave
if(isset($_GET['unsave'])) {
    $doc_id = intval($_GET['unsave']);
    $VSD->query("DELETE FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='save'");
    header("Location: saved.php?msg=unsaved");
    exit;
}

// Fetch saved documents (AFTER unsave logic) - only approved documents
$all_saved_docs = $VSD->get_list("
    SELECT d.*, u.username, u.avatar FROM documents d 
    JOIN users u ON d.user_id = u.id 
    JOIN document_interactions di ON d.id = di.document_id
    WHERE di.user_id = $user_id AND di.type = 'save' AND (d.status = 'approved' OR d.user_id = $user_id)
    ORDER BY di.created_at DESC
");

$total_saved = count($all_saved_docs);
?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-6">
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'unsaved'): ?>
            <div class="alert alert-success mb-4">
                    <i class="fa-solid fa-circle-check fa-lg"></i>
                <span>Document removed from saved</span>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-bookmark text-primary text-3xl"></i>
                    Saved Documents
                </h1>
                <p class="text-base-content/70"><?= $total_saved ?> document<?= $total_saved != 1 ? 's' : '' ?> saved</p>
            </div>
            <!-- <a href="dashboard.php" class="btn btn-ghost">← Back to Dashboard</a> -->
        </div>

        <?php if($total_saved > 0): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-4">
                <?php
                foreach($all_saved_docs as $doc) {
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $thumbnail = $doc['thumbnail'] ?? null;
                    $total_pages = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : null;
                    $icon_svg = '';
                    if(in_array($ext, ['pdf', 'doc', 'docx'])) {
                        $icon_svg = '<i class="fa-solid fa-file-pdf fa-4x text-primary"></i>';
                    } elseif(in_array($ext, ['xls', 'xlsx', 'ppt', 'pptx'])) {
                        $icon_svg = '<i class="fa-solid fa-file-excel fa-4x text-primary"></i>';
                    } elseif($ext == 'txt') {
                        $icon_svg = '<i class="fa-solid fa-file-lines fa-4x text-primary"></i>';
                    } elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $icon_svg = '<i class="fa-solid fa-file-image fa-4x text-primary"></i>';
                    } elseif($ext == 'zip') {
                        $icon_svg = '<i class="fa-solid fa-file-zipper fa-4x text-primary"></i>';
                    } else {
                        $icon_svg = '<i class="fa-solid fa-file fa-4x text-primary"></i>';
                    }
                    echo '
                    <div class="card bg-base-100 shadow-md hover:shadow-xl transition-shadow">
                        <figure class="relative h-40 bg-primary/10 flex items-center justify-center">
                            ';
                    if ($thumbnail && file_exists('uploads/' . $thumbnail)) {
                        echo '<img src="uploads/' . htmlspecialchars($thumbnail) . '" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover;">';
                        if ($total_pages) {
                            echo '<div class="badge badge-primary absolute bottom-2 right-2">' . $total_pages . ' trang</div>';
                        }
                    } else {
                        echo $icon_svg;
                    }
                    echo '
                        </figure>
                        <div class="card-body p-4">
                            <h3 class="card-title text-sm line-clamp-2" title="' . htmlspecialchars($doc['original_name']) . '">' . htmlspecialchars(substr($doc['original_name'], 0, 25)) . '</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="avatar">
                                    <div class="w-5 h-5 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center">
                                        ' . (!empty($doc['avatar']) && file_exists('uploads/avatars/' . $doc['avatar']) 
                                            ? '<img src="uploads/avatars/' . $doc['avatar'] . '" alt="Avatar" />'
                                            : '<i class="fa-solid fa-user text-[8px] text-primary"></i>') . '
                                    </div>
                                </div>
                                <span class="text-[10px] text-base-content/60 font-medium">' . htmlspecialchars($doc['username']) . '</span>
                            </div>
                            <p class="text-[10px] text-base-content/40 mt-1">' . date('M d, Y', strtotime($doc['created_at'])) . '</p>';
                    if ($total_pages) {
                        echo '<p class="text-xs text-base-content/50"><i class="fa-solid fa-file-lines mr-1"></i>' . $total_pages . ' trang</p>';
                    }
                    echo '
                            <div class="card-actions justify-end mt-2">
                                <a href="view.php?id=' . $doc['id'] . '" class="btn btn-sm btn-primary">View</a>
                                <button class="btn btn-sm btn-error" onclick="vsdConfirm({title: \'Xác nhận bỏ lưu\', message: \'Bạn có chắc chắn muốn bỏ lưu tài liệu này?\', type: \'warning\', onConfirm: () => window.location.href=\'saved.php?unsave=' . $doc['id'] . '\'})">Unsave</button>
                            </div>
                        </div>
                    </div>
                    ';
                }
                ?>
            </div>
        <?php else: ?>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body text-center py-20">
                    <div class="flex justify-center mb-4">
                        <i class="fa-solid fa-bookmark text-primary/30 text-6xl"></i>
                    </div>
                    <h2 class="card-title justify-center text-2xl">No Saved Documents</h2>
                    <p class="text-base-content/70">You haven't saved any documents yet.<br>Start exploring and save your favorite documents!</p>
                    <div class="card-actions justify-center mt-6">
                        <a href="dashboard.php" class="btn btn-primary">Browse Documents</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
</div>

?>