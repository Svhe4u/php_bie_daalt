<?php
// Assignments tab content
?>
<div class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Даалгаврууд</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
            <i class="bi bi-plus-circle"></i> Шинэ даалгавар
        </button>
    </div>

    <?php if (empty($assignments)): ?>
        <div class="text-center py-4">
            <div class="text-muted">
                <i class="bi bi-file-text fs-4 d-block mb-2"></i>
                Даалгавар байхгүй байна
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Гарчиг</th>
                        <th>Хичээл</th>
                        <th>Дуусах огноо</th>
                        <th>Илгээсэн</th>
                        <th>Хүлээгдэж буй</th>
                        <th>Үйлдэл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-text text-primary me-2"></i>
                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
                            <td>
                                <?php
                                $due_date = strtotime($assignment['due_date']);
                                $now = time();
                                $days_left = ceil(($due_date - $now) / (60 * 60 * 24));
                                $badge_class = $days_left < 0 ? 'danger' : ($days_left <= 3 ? 'warning' : 'success');
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>">
                                    <?php echo date('Y-m-d', $due_date); ?>
                                    (<?php echo $days_left; ?> хоног)
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo $assignment['total_submissions']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($assignment['pending_submissions'] > 0): ?>
                                    <span class="badge bg-warning">
                                        <?php echo $assignment['pending_submissions']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">Бүгд шалгагдсан</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="assignment.php?id=<?php echo $assignment['id']; ?>" 
                                       class="btn btn-primary btn-sm" title="Харах">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editAssignmentModal<?php echo $assignment['id']; ?>"
                                            title="Засах">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteAssignmentModal<?php echo $assignment['id']; ?>"
                                            title="Устгах">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Assignment Modal -->
                        <div class="modal fade" id="editAssignmentModal<?php echo $assignment['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Даалгавар засах</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="edit_assignment.php" method="POST">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="title<?php echo $assignment['id']; ?>" class="form-label">Гарчиг</label>
                                                <input type="text" class="form-control" id="title<?php echo $assignment['id']; ?>" 
                                                       name="title" value="<?php echo htmlspecialchars($assignment['title']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="description<?php echo $assignment['id']; ?>" class="form-label">Тайлбар</label>
                                                <textarea class="form-control" id="description<?php echo $assignment['id']; ?>" 
                                                          name="description" rows="3" required><?php echo htmlspecialchars($assignment['description']); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label for="due_date<?php echo $assignment['id']; ?>" class="form-label">Дуусах огноо</label>
                                                <input type="datetime-local" class="form-control" id="due_date<?php echo $assignment['id']; ?>" 
                                                       name="due_date" value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>" required>
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

                        <!-- Delete Assignment Modal -->
                        <div class="modal fade" id="deleteAssignmentModal<?php echo $assignment['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Даалгавар устгах</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Та дараах даалгаврыг устгахдаа итгэлтэй байна уу?</p>
                                        <p><strong><?php echo htmlspecialchars($assignment['title']); ?></strong></p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                                        <form action="delete_assignment.php" method="POST" class="d-inline">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Устгах</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Assignment Modal -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Шинэ даалгавар</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="add_assignment.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Хичээл</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Сонгох...</option>
                            <?php
                            $stmt = $conn->prepare("
                                SELECT id, name
                                FROM courses
                                WHERE teacher_id = ?
                                ORDER BY name
                            ");
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            foreach ($courses as $course):
                            ?>
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
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Дуусах огноо</label>
                        <input type="datetime-local" class="form-control" id="due_date" name="due_date" required>
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