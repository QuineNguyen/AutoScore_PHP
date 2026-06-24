<?php
require_once __DIR__ . '/../models/config.php';
require_once __DIR__ . '/../managers/DocumentManager.php';

Config::initDatabase();

$documentManager = new DocumentManager();
$documents = $documentManager->getDocuments(1, 100);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tài liệu - AutoScore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #333;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.8;
            font-size: 1.1em;
        }
        
        .nav {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav a {
            color: #667eea;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav a:hover {
            color: #764ba2;
        }
        
        .content {
            padding: 30px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .section-title h2 {
            font-size: 1.5em;
        }
        
        /* Upload Section */
        .upload-section {
            background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
            border: 2px dashed #ffc107;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.3s;
        }
        
        .upload-section:hover {
            border-color: #ff9800;
            background: linear-gradient(135deg, #fff3cd 0%, #fff 100%);
        }
        
        .upload-section.dragover {
            border-color: #ff9800;
            background: #fff3cd;
        }
        
        .upload-icon {
            font-size: 4em;
            margin-bottom: 15px;
        }
        
        .upload-section h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .upload-section p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-upload {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #333;
        }
        
        .btn-upload:hover {
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
        }
        
        .btn-sync {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }
        
        .btn-sync:hover {
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-sync-all {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        
        .btn-sync-all:hover {
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.4);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }
        
        .upload-status {
            margin-top: 15px;
            padding: 10px 15px;
            border-radius: 5px;
            display: none;
        }
        
        .upload-status.show {
            display: block;
        }
        
        .upload-status.uploading {
            background: #fff3cd;
            color: #856404;
        }
        
        .upload-status.success {
            background: #d4edda;
            color: #155724;
        }
        
        .upload-status.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Document List */
        .doc-list-section {
            margin-top: 30px;
        }
        
        .doc-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .doc-list {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .doc-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            transition: background 0.2s;
        }
        
        .doc-item:last-child {
            border-bottom: none;
        }
        
        .doc-item:hover {
            background: #f8f9fa;
        }
        
        .doc-item input[type="checkbox"] {
            margin-right: 15px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .doc-info {
            flex: 1;
        }
        
        .doc-info strong {
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        
        .doc-info small {
            color: #666;
        }
        
        .doc-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
            margin-right: 10px;
        }
        
        .doc-type.pdf {
            background: #fee;
            color: #c33;
        }
        
        .doc-type.txt {
            background: #e8f4fd;
            color: #0066cc;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.2s;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 4em;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Sync Status */
        .sync-status {
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            display: none;
        }
        
        .sync-status.show {
            display: block;
        }
        
        .sync-status.loading {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        
        .sync-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #28a745;
        }
        
        .sync-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            border-top: 1px solid #dee2e6;
        }
        
        .select-all-wrapper {
            padding: 10px 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .select-all-wrapper label {
            cursor: pointer;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Quản lý Tài liệu</h1>
            <p>Upload và đồng bộ tài liệu tham khảo vào Vector Database</p>
        </div>
        
        <div class="nav">
            <a href="index.php">← Trang chủ</a>
            <a href="create_question.php">Tạo câu hỏi</a>
            <a href="history.php">Lịch sử</a>
        </div>
        
        <div class="content">
            <!-- Upload Section -->
            <div class="upload-section" id="uploadZone">
                <h3>Upload tài liệu mới</h3>
                <p>Kéo thả file vào đây hoặc click để chọn file<br>
                <small>Hỗ trợ: PDF, TXT (tối đa 10MB)</small></p>
                
                <div class="file-input-wrapper">
                    <button class="btn btn-upload">Chọn file</button>
                    <input type="file" id="fileInput" accept=".pdf,.txt">
                </div>
                
                <div id="uploadStatus" class="upload-status"></div>
            </div>
            
            <!-- Document List Section -->
            <div class="doc-list-section">
                <div class="section-title">
                    <h2>Danh sách tài liệu</h2>
                    <span style="color: #666;">(<?= count($documents) ?> tài liệu)</span>
                </div>
                
                <div class="doc-actions">
                    <button class="btn btn-sync" onclick="syncSelectedDocuments()" id="syncSelectedBtn">
                        Sync tài liệu đã chọn
                    </button>
                    <button class="btn btn-sync-all" onclick="syncAllDocuments()" id="syncAllBtn">
                        Sync TẤT CẢ vào Vector DB
                    </button>
                </div>
                
                <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <h3>Chưa có tài liệu nào</h3>
                        <p>Upload tài liệu đầu tiên của bạn ở phần trên</p>
                    </div>
                <?php else: ?>
                    <div class="doc-list">
                        <div class="select-all-wrapper">
                            <label>
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                Chọn tất cả
                            </label>
                        </div>
                        <?php foreach ($documents as $doc): ?>
                            <div class="doc-item" data-doc-id="<?= $doc['id'] ?>">
                                <input type="checkbox" class="doc-checkbox" value="<?= $doc['id'] ?>">
                                <div class="doc-info">
                                    <strong><?= htmlspecialchars($doc['title']) ?></strong>
                                    <small>
                                        <span class="doc-type <?= $doc['file_type'] ?>"><?= strtoupper($doc['file_type']) ?></span>
                                        Ngày tạo: <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?>
                                    </small>
                                </div>
                                <button class="delete-btn" onclick="deleteDocument(<?= $doc['id'] ?>)">🗑️ Xóa</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div id="syncStatus" class="sync-status"></div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 AutoScore System | Vector Database: MySQL</p>
        </div>
    </div>
    
    <script>
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const uploadStatus = document.getElementById('uploadStatus');
        
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                uploadFile(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                uploadFile(e.target.files[0]);
            }
        });
        
        function uploadFile(file) {
            const allowedTypes = ['application/pdf', 'text/plain'];
            if (!allowedTypes.includes(file.type)) {
                showUploadStatus('error', 'Chỉ chấp nhận file PDF hoặc TXT');
                return;
            }
            
            if (file.size > 10 * 1024 * 1024) {
                showUploadStatus('error', 'File quá lớn (tối đa 10MB)');
                return;
            }
            
            showUploadStatus('uploading', `Đang upload "${file.name}"...`);
            
            const formData = new FormData();
            formData.append('document_file', file);
            formData.append('action', 'upload');
            
            fetch('../ajax/upload_document_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showUploadStatus('success', `Upload thành công: ${data.title}`);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showUploadStatus('error', `Lỗi: ${data.error}`);
                }
            })
            .catch(error => {
                showUploadStatus('error', `Lỗi kết nối: ${error.message}`);
            });
        }
        
        function showUploadStatus(type, message) {
            uploadStatus.className = `upload-status show ${type}`;
            uploadStatus.textContent = message;
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.doc-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
        
        function syncSelectedDocuments() {
            const checkboxes = document.querySelectorAll('.doc-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Vui lòng chọn ít nhất một tài liệu để sync!');
                return;
            }
            
            const documentIds = Array.from(checkboxes).map(cb => cb.value);
            performSync(documentIds, `Đang sync ${documentIds.length} tài liệu...`);
        }
        
        function syncAllDocuments() {
            if (!confirm('Bạn có chắc muốn sync TẤT CẢ tài liệu vào Vector DB?\nĐiều này sẽ xóa dữ liệu cũ và thay thế bằng dữ liệu mới.')) {
                return;
            }
            performSync(null, 'Đang sync TẤT CẢ tài liệu...');
        }
        
        function performSync(documentIds, loadingMessage) {
            const syncStatus = document.getElementById('syncStatus');
            const syncSelectedBtn = document.getElementById('syncSelectedBtn');
            const syncAllBtn = document.getElementById('syncAllBtn');
            
            syncSelectedBtn.disabled = true;
            syncAllBtn.disabled = true;
            syncSelectedBtn.style.opacity = '0.6';
            syncAllBtn.style.opacity = '0.6';
            
            syncStatus.className = 'sync-status show loading';
            syncStatus.innerHTML = `<span class="spinner"></span> ${loadingMessage}`;
            
            const formData = new FormData();
            if (documentIds && documentIds.length > 0) {
                documentIds.forEach(id => formData.append('document_ids[]', id));
            }
            
            fetch('../ajax/sync_documents_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = `Sync thành công! (${data.synced_count || 0} tài liệu, ${data.chunks_ingested || 0} chunks)`;
                    if (data.timing) {
                        message += `<br><small>Thời gian: ${data.timing}</small>`;
                    }
                    syncStatus.className = 'sync-status show success';
                    syncStatus.innerHTML = message;
                } else {
                    syncStatus.className = 'sync-status show error';
                    syncStatus.innerHTML = `Lỗi: ${data.error || 'Không xác định'}`;
                }
            })
            .catch(error => {
                syncStatus.className = 'sync-status show error';
                syncStatus.innerHTML = `Lỗi kết nối: ${error.message}`;
            })
            .finally(() => {
                syncSelectedBtn.disabled = false;
                syncAllBtn.disabled = false;
                syncSelectedBtn.style.opacity = '1';
                syncAllBtn.style.opacity = '1';
            });
        }
        
        function deleteDocument(documentId) {
            if (!confirm('Bạn có chắc muốn xóa tài liệu này?')) {
                return;
            }
            
            const docItem = document.querySelector(`.doc-item[data-doc-id="${documentId}"]`);
            const deleteBtn = docItem?.querySelector('.delete-btn');
            
            if (deleteBtn) {
                deleteBtn.disabled = true;
                deleteBtn.textContent = 'Đang xóa...';
            }
            
            const formData = new FormData();
            formData.append('document_id', documentId);
            
            fetch('../ajax/delete_document_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (docItem) {
                        docItem.style.transition = 'all 0.3s';
                        docItem.style.opacity = '0';
                        docItem.style.transform = 'translateX(-20px)';
                        setTimeout(() => {
                            docItem.remove();
                            const docList = document.querySelector('.doc-list');
                            if (docList && docList.querySelectorAll('.doc-item').length === 0) {
                                window.location.reload();
                            }
                        }, 300);
                    }
                } else {
                    alert('Lỗi xóa: ' + data.error);
                    if (deleteBtn) {
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = '🗑️ Xóa';
                    }
                }
            })
            .catch(error => {
                alert('Lỗi: ' + error.message);
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = '🗑️ Xóa';
                }
            });
        }
    </script>
</body>
</html>
