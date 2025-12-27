<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
}

require_once 'config/db.php';
require_once 'config/auth.php';
require_once 'config/premium.php';

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$current_page = 'saved';

// Handle unsave
if(isset($_GET['unsave'])) {
    $doc_id = intval($_GET['unsave']);
    mysqli_query($conn, "DELETE FROM document_interactions WHERE document_id=$doc_id AND user_id=$user_id AND type='save'");
    header("Location: saved.php?msg=unsaved");
    exit;
}

// Fetch saved documents (AFTER unsave logic) - only approved documents
$saved_docs = mysqli_query($conn, "
    SELECT d.*, u.username FROM documents d 
    JOIN users u ON d.user_id = u.id 
    JOIN document_interactions di ON d.id = di.document_id
    WHERE di.user_id = $user_id AND di.type = 'save' AND (d.status = 'approved' OR d.user_id = $user_id)
    ORDER BY di.created_at DESC
");

$total_saved = mysqli_num_rows($saved_docs);
?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-6">
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'unsaved'): ?>
            <div class="alert alert-success mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Document removed from saved</span>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    Saved Documents
                </h1>
                <p class="text-base-content/70"><?= $total_saved ?> document<?= $total_saved != 1 ? 's' : '' ?> saved</p>
            </div>
            <a href="dashboard.php" class="btn btn-ghost">← Back to Dashboard</a>
        </div>

        <?php if($total_saved > 0): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 gap-4">
                <?php
                while($doc = mysqli_fetch_assoc($saved_docs)) {
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $thumbnail = $doc['thumbnail'] ?? null;
                    $total_pages = isset($doc['total_pages']) && $doc['total_pages'] > 0 ? $doc['total_pages'] : null;
                    $icon_svg = '';
                    if(in_array($ext, ['pdf', 'doc', 'docx'])) {
                        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-primary"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-3-8h.01M4 6a2 2 0 012-2h8l4 4v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" /></svg>';
                    } elseif(in_array($ext, ['xls', 'xlsx', 'ppt', 'pptx'])) {
                        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-primary"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0-1 3m-1-3h1.5m-1.5 0h-1.5m1.5 0v-3m0 0h-1.5" /></svg>';
                    } elseif($ext == 'txt') {
                        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-primary"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-3-8h.01M4 6a2 2 0 012-2h8l4 4v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" /></svg>';
                    } elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-primary"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>';
                    } elseif($ext == 'zip') {
                        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-primary"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>';
                    } else {
                        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-primary"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>';
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
                            <p class="text-xs text-base-content/70">by ' . htmlspecialchars($doc['username']) . '</p>
                            <p class="text-xs text-base-content/50">' . date('M d, Y', strtotime($doc['created_at'])) . '</p>';
                    if ($total_pages) {
                        echo '<p class="text-xs text-base-content/50">📄 ' . $total_pages . ' trang</p>';
                    }
                    echo '
                            <div class="card-actions justify-end mt-2">
                                <a href="view.php?id=' . $doc['id'] . '" class="btn btn-sm btn-primary">View</a>
                                <button class="btn btn-sm btn-error" onclick="if(confirm(\'Remove from saved?\')) window.location.href=\'saved.php?unsave=' . $doc['id'] . '\'">Unsave</button>
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
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-primary/50">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                        </svg>
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

<?php 
mysqli_close($conn);
?>