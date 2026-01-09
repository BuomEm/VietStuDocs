<?php
// Giao diện Dashboard (đã được tách khỏi logic)
?>
<?php include D_ROOT . '/resources/views/partials/head.php'; ?>
<?php include D_ROOT . '/resources/views/partials/sidebar.php'; ?>

<!-- PDF.js Library for document previews -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<!-- DOCX Preview Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.4/dist/docx-preview.min.js"></script>

<style>
    /* CSS rút gọn */
    .document-card-vsd { border-radius: 2.5rem; border: 1px solid #eee; overflow: hidden; transition: all 0.5s; }
</style>

<div class="drawer-content flex flex-col">
    <?php include D_ROOT . '/resources/views/partials/navbar.php'; ?>
    
    <main class="flex-1 p-4 lg:p-8">
        <!-- Content Dashboard -->
        <h1 class="text-4xl font-extrabold mb-8"><?= $is_logged_in ? 'Bảng Điều Khiển' : 'Khám Phá Tài Liệu' ?></h1>
        
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
            <?php foreach($public_docs as $doc): ?>
                <div class="document-card-vsd bg-base-100 group">
                    <div class="aspect-[3/4] bg-base-200 relative overflow-hidden">
                        <img src="/uploads/<?= $doc['thumbnail'] ?>" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <a href="/view?id=<?= $doc['id'] ?>" class="btn btn-primary btn-circle"><i class="fa-solid fa-eye"></i></a>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold line-clamp-1"><?= htmlspecialchars($doc['original_name']) ?></h3>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
    
    <?php include D_ROOT . '/resources/views/partials/footer.php'; ?>
</div>
