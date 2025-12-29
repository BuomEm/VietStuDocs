<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Pending Documents - Admin Panel";

// Handle approval/rejection
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $document_id = intval($_POST['document_id']);
    $action = $_POST['action'];
    
    if($action === 'approve') {
        $points = intval($_POST['points']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        
        if($points > 0) {
            approveDocument($document_id, $admin_id, $points, $notes);
            header("Location: pending-docs.php?status=approved");
            exit;
        }
    } elseif($action === 'reject') {
        $reason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? '');
        rejectDocument($document_id, $admin_id, $reason);
        header("Location: pending-docs.php?status=rejected");
        exit;
    }
}

// Handle viewing document details
$view_doc_id = isset($_GET['view']) ? intval($_GET['view']) : null;
$view_doc = null;

if($view_doc_id) {
    $view_doc = getDocumentForApproval($view_doc_id);
}

// Get all pending documents
$pending_docs = getPendingDocuments();

// Get unread notifications count
$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'pending';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 260px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
        }

        .admin-sidebar h2 {
            font-size: 20px;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 2px solid rgba(255,255,255,0.2);
            padding-bottom: 15px;
        }

        .admin-sidebar nav a {
            display: block;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            margin-bottom: 5px;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .admin-sidebar nav a:hover,
        .admin-sidebar nav a.active {
            background: rgba(255,255,255,0.2);
        }

        .admin-sidebar .logout {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 15px;
        }

        .admin-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .admin-header h1 {
            font-size: 24px;
        }

        .content-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .content-section h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Document Grid */
        .document-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .document-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .document-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .doc-thumbnail {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            position: relative;
        }

        .doc-info {
            padding: 15px;
            flex-grow: 1;
        }

        .doc-title {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-meta {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
        }

        .doc-actions {
            padding: 12px 15px;
            background: #f9f9f9;
            border-top: 1px solid #eee;
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }

        .action-btn-primary {
            background: #667eea;
            color: white;
        }

        .action-btn-primary:hover {
            background: #764ba2;
        }

        .action-btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .action-btn-secondary:hover {
            background: #e0e0e0;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .modal-header h2 {
            font-size: 20px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #4caf50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #da190b;
        }

        .btn-secondary {
            background: #999;
            color: white;
        }

        .btn-secondary:hover {
            background: #777;
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 200px;
            }

            .admin-content {
                margin-left: 200px;
            }

            .document-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Header -->
            <div class="admin-header">
                <h1>📋 Pending Documents for Review</h1>
            </div>

            <!-- Status Messages -->
            <?php if(isset($_GET['status']) && $_GET['status'] === 'approved'): ?>
                <div class="alert alert-success">✓ Document approved successfully!</div>
            <?php elseif(isset($_GET['status']) && $_GET['status'] === 'rejected'): ?>
                <div class="alert alert-success">✓ Document rejected successfully!</div>
            <?php endif; ?>

            <!-- Pending Documents -->
            <div class="content-section">
                <?php $pending_count = mysqli_num_rows($pending_docs); ?>
                
                <h2>Pending: <?= $pending_count ?> Document<?= $pending_count !== 1 ? 's' : '' ?></h2>

                <?php if($pending_count > 0): ?>
                    <div class="document-grid">
                        <?php while($doc = mysqli_fetch_assoc($pending_docs)): 
                            $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                            $icon_map = [
                                'pdf' => '📄', 'doc' => '📄', 'docx' => '📄',
                                'txt' => '📝', 'xlsx' => '📊', 'ppt' => '🎬',
                                'jpg' => '🖼️', 'png' => '🖼️', 'jpeg' => '🖼️',
                                'zip' => '🗂️', 'rar' => '🗂️'
                            ];
                            $icon = $icon_map[$ext] ?? '📁';
                        ?>
                            <div class="document-card">
                                <div class="doc-thumbnail"><?= $icon ?></div>
                                <div class="doc-info">
                                    <div class="doc-title" title="<?= htmlspecialchars($doc['original_name']) ?>">
                                        <?= htmlspecialchars(substr($doc['original_name'], 0, 40)) ?>
                                    </div>
                                    <div class="doc-meta">
                                        <div>👤 <?= htmlspecialchars($doc['username']) ?></div>
                                        <div>📅 <?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                                        <div style="margin-top: 8px;">
                                            <?php if($doc['description']): ?>
                                                <small style="color: #666;">📝 <?= htmlspecialchars(substr($doc['description'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="doc-actions">
                                    <button class="action-btn action-btn-primary" onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')">✓ Approve</button>
                                    <button class="action-btn action-btn-secondary" onclick="openRejectModal(<?= $doc['id'] ?>)">✗ Reject</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-message">✓ All documents have been reviewed!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✓ Approve Document</h2>
                <button class="close-btn" onclick="closeModal('approveModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="document_id" id="approve_doc_id">
                <input type="hidden" name="action" value="approve">

                <div class="form-group">
                    <label for="doc_title">Document</label>
                    <input type="text" id="doc_title" readonly style="background: #f5f5f5;">
                </div>

                <div class="form-group">
                    <label for="points">🎯 Points Value (How many points will buyers need to pay?)</label>
                    <input type="number" id="points" name="points" min="1" max="1000" value="50" required>
                    <small style="color: #999;">This is the maximum point value users can set for selling this document</small>
                </div>

                <div class="form-group">
                    <label for="notes">📝 Admin Notes</label>
                    <textarea id="notes" name="notes" placeholder="Add any notes about this document..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">✓ Approve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✗ Reject Document</h2>
                <button class="close-btn" onclick="closeModal('rejectModal')">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="document_id" id="reject_doc_id">
                <input type="hidden" name="action" value="reject">

                <div class="form-group">
                    <label for="reason">❌ Rejection Reason</label>
                    <textarea id="reason" name="rejection_reason" placeholder="Why are you rejecting this document?" required></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">✗ Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApproveModal(docId, docTitle) {
            document.getElementById('approve_doc_id').value = docId;
            document.getElementById('doc_title').value = docTitle;
            document.getElementById('approveModal').classList.add('show');
        }

        function openRejectModal(docId) {
            document.getElementById('reject_doc_id').value = docId;
            document.getElementById('rejectModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if(event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>
