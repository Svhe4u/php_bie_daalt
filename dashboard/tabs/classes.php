<?php
// Classes tab content
?>
<div class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Миний хичээлүүд</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="bi bi-plus-circle"></i> Шинэ хичээл
        </button>
    </div>

    <?php if (empty($courses)): ?>
        <div class="text-center py-4">
            <div class="text-muted">
                <i class="bi bi-book fs-4 d-block mb-2"></i>
                Хичээл байхгүй байна
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Хичээл</th>
                        <th>Сурагч</th>
                        <th>Даалгавар</th>
                        <th>Материал</th>
                        <th>Үйлдэл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-book text-primary me-2"></i>
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <i class="bi bi-people me-1"></i>
                                    <?php echo $course['enrolled_students']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo $course['total_assignments']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-warning">
                                    <?php echo $course['total_materials']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="course.php?id=<?php echo $course['id']; ?>" 
                                       class="btn btn-primary btn-sm" title="Харах">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-info btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#studentsModal<?php echo $course['id']; ?>"
                                            title="Сурагчид">
                                        <i class="bi bi-people"></i>
                                    </button>
                                    <a href="../materials/list.php?course_id=<?php echo $course['id']; ?>" 
                                       class="btn btn-success btn-sm" title="Материал">
                                        <i class="bi bi-file-earmark"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal<?php echo $course['id']; ?>"
                                            title="Засах">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Шинэ хичээл</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="add_course.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Хичээлийн нэр</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Тайлбар</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                    <button type="submit" class="btn btn-primary">Нэмэх</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<?php foreach ($courses as $course): ?>
<div class="modal fade" id="editModal<?php echo $course['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Хичээл засах</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="edit_course.php" method="POST">
                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name<?php echo $course['id']; ?>" class="form-label">Хичээлийн нэр</label>
                        <input type="text" class="form-control" id="name<?php echo $course['id']; ?>" 
                               name="name" value="<?php echo htmlspecialchars($course['name']); ?>" required>
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
<?php endforeach; ?> 