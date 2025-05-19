<?php
// Resources tab content
?>
<div class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Хичээлийн материал</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
            <i class="bi bi-upload"></i> Материал оруулах
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control" id="searchInput" placeholder="Хайх...">
            </div>
        </div>
        <div class="col-md-6">
            <select class="form-select" id="courseFilter">
                <option value="">Бүх хичээл</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>">
                        <?php echo htmlspecialchars($course['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (empty($materials)): ?>
        <div class="text-center py-4">
            <div class="text-muted">
                <i class="bi bi-folder-x fs-4 d-block mb-2"></i>
                Материал байхгүй байна
            </div>
        </div>
    <?php else: ?>
        <div class="row" id="materialsGrid">
            <?php foreach ($materials as $material): ?>
                <div class="col-md-4 mb-4 material-item" 
                     data-course-id="<?php echo $material['course_id']; ?>"
                     data-title="<?php echo htmlspecialchars($material['title']); ?>"
                     data-description="<?php echo htmlspecialchars($material['description']); ?>">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($material['title']); ?>
                                </h5>
                                <div class="dropdown">
                                    <button class="btn btn-link text-dark p-0" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="download_material.php?id=<?php echo $material['id']; ?>">
                                                <i class="bi bi-download"></i> Татаж авах
                                            </a>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editMaterialModal<?php echo $material['id']; ?>">
                                                <i class="bi bi-pencil"></i> Засах
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteMaterialModal<?php echo $material['id']; ?>">
                                                <i class="bi bi-trash"></i> Устгах
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <h6 class="card-subtitle mb-2 text-muted">
                                <?php echo htmlspecialchars($material['course_name']); ?>
                            </h6>
                            <p class="card-text">
                                <?php echo htmlspecialchars(substr($material['description'], 0, 100)) . '...'; ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?php echo date('Y-m-d', strtotime($material['uploaded_at'])); ?>
                                </small>
                                <span class="badge bg-primary">
                                    <?php echo strtoupper(pathinfo($material['file_name'], PATHINFO_EXTENSION)); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Material Modal -->
                <div class="modal fade" id="editMaterialModal<?php echo $material['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Материал засах</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="edit_material.php" method="POST">
                                <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="title<?php echo $material['id']; ?>" class="form-label">Гарчиг</label>
                                        <input type="text" class="form-control" id="title<?php echo $material['id']; ?>" 
                                               name="title" value="<?php echo htmlspecialchars($material['title']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description<?php echo $material['id']; ?>" class="form-label">Тайлбар</label>
                                        <textarea class="form-control" id="description<?php echo $material['id']; ?>" 
                                                  name="description" rows="3"><?php echo htmlspecialchars($material['description']); ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                                    <button type="submit" class="btn btn-primary">Хадгалах</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Material Modal -->
                <div class="modal fade" id="deleteMaterialModal<?php echo $material['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Материал устгах</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Та дараах материалыг устгахдаа итгэлтэй байна уу?</p>
                                <p><strong><?php echo htmlspecialchars($material['title']); ?></strong></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                                <form action="delete_material.php" method="POST" class="d-inline">
                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Устгах</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Material Modal -->
<div class="modal fade" id="uploadMaterialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Материал оруулах</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="upload_material.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Хичээл</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Сонгох...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="title" class="form-label">Гарчиг</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Тайлбар</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="file" class="form-label">Файл</label>
                        <input type="file" class="form-control" id="file" name="file" required>
                        <div class="form-text">
                            Зөвшөөрөгдсөн файлын төрөл: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, ZIP, RAR
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                    <button type="submit" class="btn btn-primary">Оруулах</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search and filter functionality
const searchInput = document.getElementById('searchInput');
const courseFilter = document.getElementById('courseFilter');
const materialsGrid = document.getElementById('materialsGrid');

function filterMaterials() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedCourse = courseFilter.value;
    
    document.querySelectorAll('.material-item').forEach(item => {
        const title = item.dataset.title.toLowerCase();
        const description = item.dataset.description.toLowerCase();
        const courseId = item.dataset.courseId;
        
        const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
        const matchesCourse = !selectedCourse || courseId === selectedCourse;
        
        item.style.display = matchesSearch && matchesCourse ? 'block' : 'none';
    });
}

searchInput.addEventListener('input', filterMaterials);
courseFilter.addEventListener('change', filterMaterials);

// File type validation
document.getElementById('file').addEventListener('change', function() {
    const file = this.files[0];
    const allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-rar-compressed'
    ];
    
    if (file && !allowedTypes.includes(file.type)) {
        alert('Зөвшөөрөгдөөгүй файлын төрөл байна. Зөвшөөрөгдсөн төрлүүд: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, ZIP, RAR');
        this.value = '';
    }
});
</script>

<style>
.material-item .card {
    transition: transform 0.2s;
}

.material-item .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.material-item .dropdown-menu {
    min-width: 200px;
}

.material-item .dropdown-item {
    padding: 0.5rem 1rem;
}

.material-item .dropdown-item i {
    margin-right: 0.5rem;
}
</style> 