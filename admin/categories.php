<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/categories.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Categories Management - Admin Panel";

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['create_category'])) {
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $description = trim($_POST['description'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if(!empty($name) && !empty($type)) {
            if(createCategory($name, $type, $description, $sort_order)) {
                header("Location: categories.php?msg=created");
                exit;
            }
        }
    }
    
    if(isset($_POST['update_category'])) {
        $id = intval($_POST['category_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if($id > 0 && !empty($name)) {
            if(updateCategory($id, $name, $description, $sort_order, $is_active)) {
                header("Location: categories.php?msg=updated");
                exit;
            }
        }
    }
    
    if(isset($_POST['delete_category'])) {
        $id = intval($_POST['category_id']);
        if($id > 0) {
            if(deleteCategory($id)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete']);
            }
            exit;
        }
    }
    
    if(isset($_POST['toggle_status'])) {
        $id = intval($_POST['category_id']);
        if($id > 0) {
            if(toggleCategoryStatus($id)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
        }
    }
}

// Get filter
$filter_type = $_GET['type'] ?? 'all';

// Get categories
if($filter_type === 'all') {
    $categories_grouped = getAllCategoriesGrouped(false);
} else {
    $categories_grouped = [$filter_type => getCategoriesByType($filter_type, false)];
}

// Get unread notifications count
$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'categories';
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
            color: #333;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #764ba2;
        }

        .btn-secondary {
            background: #999;
            color: white;
        }

        .btn-secondary:hover {
            background: #777;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn-success:hover {
            background: #388e3c;
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

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-bar select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .category-type-section {
            margin-bottom: 40px;
        }

        .category-type-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-type-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 0 0 8px 8px;
        }

        .category-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .category-card.inactive {
            opacity: 0.5;
            border-left-color: #999;
        }

        .category-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 8px;
            color: #333;
        }

        .category-meta {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
        }

        .category-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-active {
            background: #4caf50;
            color: white;
        }

        .badge-inactive {
            background: #999;
            color: white;
        }

        .badge-count {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 200px;
            }

            .admin-content {
                margin-left: 200px;
            }

            .categories-grid {
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
                <h1>📚 Categories Management</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openCreateModal()">➕ Add Category</button>
                </div>
            </div>

            <!-- Status Messages -->
            <?php if(isset($_GET['msg'])): ?>
                <?php if($_GET['msg'] === 'created'): ?>
                    <div class="alert alert-success">✓ Category created successfully!</div>
                <?php elseif($_GET['msg'] === 'updated'): ?>
                    <div class="alert alert-success">✓ Category updated successfully!</div>
                <?php elseif($_GET['msg'] === 'deleted'): ?>
                    <div class="alert alert-success">✓ Category deleted successfully!</div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="content-section">
                <form method="GET" class="filter-bar">
                    <label>Filter by type:</label>
                    <select name="type" onchange="this.form.submit()">
                        <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="field" <?= $filter_type === 'field' ? 'selected' : '' ?>>Lĩnh vực</option>
                        <option value="subject" <?= $filter_type === 'subject' ? 'selected' : '' ?>>Môn học</option>
                        <option value="level" <?= $filter_type === 'level' ? 'selected' : '' ?>>Cấp học</option>
                        <option value="curriculum" <?= $filter_type === 'curriculum' ? 'selected' : '' ?>>Chương trình</option>
                        <option value="doc_type" <?= $filter_type === 'doc_type' ? 'selected' : '' ?>>Loại tài liệu</option>
                    </select>
                </form>
            </div>

            <!-- Categories Display -->
            <?php foreach($categories_grouped as $type => $categories): ?>
                <?php if(empty($categories)) continue; ?>
                
                <div class="category-type-section">
                    <div class="category-type-header">
                        <h3>
                            <?= getCategoryTypeLabel($type) ?>
                            <span class="badge badge-count"><?= count($categories) ?></span>
                        </h3>
                    </div>
                    
                    <div class="categories-grid">
                        <?php foreach($categories as $cat): ?>
                            <div class="category-card <?= $cat['is_active'] ? '' : 'inactive' ?>">
                                <div class="category-name">
                                    <?= htmlspecialchars($cat['name']) ?>
                                    <span class="badge <?= $cat['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <div class="category-meta">
                                    Order: <?= $cat['sort_order'] ?> | 
                                    Docs: <?= getDocumentCountByCategory($cat['id']) ?>
                                </div>
                                <?php if(!empty($cat['description'])): ?>
                                    <div style="font-size: 12px; color: #666; margin: 8px 0;">
                                        <?= htmlspecialchars($cat['description']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="category-actions">
                                    <button class="btn btn-primary btn-small" onclick="openEditModal(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-secondary btn-small" onclick="toggleStatus(<?= $cat['id'] ?>)">
                                        🔄 Toggle
                                    </button>
                                    <button class="btn btn-danger btn-small" onclick="deleteCategory(<?= $cat['id'] ?>)">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal" id="categoryModal">
        <div class="modal-content">
            <div class="modal-header" id="modalTitle">Add Category</div>
            <form method="POST" id="categoryForm">
                <input type="hidden" name="category_id" id="category_id">
                
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="name" id="name" required>
                </div>
                
                <div class="form-group">
                    <label>Type *</label>
                    <select name="type" id="type" required>
                        <option value="field">Lĩnh vực</option>
                        <option value="subject">Môn học</option>
                        <option value="level">Cấp học</option>
                        <option value="curriculum">Chương trình</option>
                        <option value="doc_type">Loại tài liệu</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="0">
                </div>
                
                <div class="form-group" id="activeGroup" style="display: none;">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" checked>
                        Active
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Create</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add Category';
            document.getElementById('categoryForm').reset();
            document.getElementById('category_id').value = '';
            document.getElementById('activeGroup').style.display = 'none';
            document.getElementById('type').disabled = false;
            
            const form = document.getElementById('categoryForm');
            form.querySelector('#submitBtn').textContent = 'Create';
            form.querySelector('[name="create_category"]')?.remove();
            form.querySelector('[name="update_category"]')?.remove();
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'create_category';
            input.value = '1';
            form.appendChild(input);
            
            document.getElementById('categoryModal').classList.add('active');
        }

        function openEditModal(category) {
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('category_id').value = category.id;
            document.getElementById('name').value = category.name;
            document.getElementById('type').value = category.type;
            document.getElementById('type').disabled = true;
            document.getElementById('description').value = category.description || '';
            document.getElementById('sort_order').value = category.sort_order;
            document.getElementById('is_active').checked = category.is_active == 1;
            document.getElementById('activeGroup').style.display = 'block';
            
            const form = document.getElementById('categoryForm');
            form.querySelector('#submitBtn').textContent = 'Update';
            form.querySelector('[name="create_category"]')?.remove();
            form.querySelector('[name="update_category"]')?.remove();
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'update_category';
            input.value = '1';
            form.appendChild(input);
            
            document.getElementById('categoryModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('categoryModal').classList.remove('active');
        }

        function deleteCategory(id) {
            if(!confirm('Are you sure you want to delete this category? All associations with documents will be removed.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('delete_category', '1');
            formData.append('category_id', id);
            
            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Failed to delete category');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting category');
            });
        }

        function toggleStatus(id) {
            const formData = new FormData();
            formData.append('toggle_status', '1');
            formData.append('category_id', id);
            
            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Failed to toggle status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error toggling status');
            });
        }

        // Close modal when clicking outside
        document.getElementById('categoryModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>
