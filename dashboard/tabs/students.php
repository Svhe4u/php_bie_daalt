<?php
// Students tab content
?>
<div class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Сурагчид</h4>
        <div class="input-group" style="width: 300px;">
            <input type="text" class="form-control" id="studentSearch" placeholder="Хайх...">
            <button class="btn btn-outline-secondary" type="button">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>

    <?php if (empty($students)): ?>
        <div class="text-center py-4">
            <div class="text-muted">
                <i class="bi bi-people fs-4 d-block mb-2"></i>
                Сурагч байхгүй байна
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Сурагч</th>
                        <th>И-мэйл</th>
                        <th>Хичээл</th>
                        <th>Дундаж дүн</th>
                        <th>Үйлдэл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person text-primary me-2"></i>
                                    <?php echo htmlspecialchars($student['name']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo $student['total_courses']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($student['average_grade']): ?>
                                    <span class="badge bg-<?php echo $student['average_grade'] >= 60 ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($student['average_grade'], 1); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Дүн байхгүй</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="student.php?id=<?php echo $student['id']; ?>" 
                                       class="btn btn-primary btn-sm" title="Харах">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#gradeModal<?php echo $student['id']; ?>"
                                            title="Дүн оруулах">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-info btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#messageModal<?php echo $student['id']; ?>"
                                            title="Мессэж илгээх">
                                        <i class="bi bi-envelope"></i>
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

<!-- Grade Modals -->
<?php foreach ($students as $student): ?>
<div class="modal fade" id="gradeModal<?php echo $student['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Дүн оруулах: <?php echo htmlspecialchars($student['name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_grade.php" method="POST">
                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="grade_course_id<?php echo $student['id']; ?>" class="form-label">Хичээл</label>
                        <select class="form-select" id="grade_course_id<?php echo $student['id']; ?>" name="course_id" required>
                            <option value="">Сонгох...</option>
                            <?php
                            $stmt = $conn->prepare("
                                SELECT c.id, c.name
                                FROM courses c
                                JOIN course_enrollments ce ON c.id = ce.course_id
                                WHERE ce.student_id = ? AND c.teacher_id = ?
                            ");
                            $stmt->bind_param("ii", $student['id'], $_SESSION['user_id']);
                            $stmt->execute();
                            $student_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            foreach ($student_courses as $course):
                            ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="grade<?php echo $student['id']; ?>" class="form-label">Дүн</label>
                        <input type="number" class="form-control" id="grade<?php echo $student['id']; ?>" name="grade" 
                               min="0" max="100" step="0.1" required>
                    </div>
                    <div class="mb-3">
                        <label for="feedback<?php echo $student['id']; ?>" class="form-label">Санал хүсэлт</label>
                        <textarea class="form-control" id="feedback<?php echo $student['id']; ?>" name="feedback" rows="3"></textarea>
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

<!-- Message Modal -->
<div class="modal fade" id="messageModal<?php echo $student['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Мессэж илгээх: <?php echo htmlspecialchars($student['name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="send_message.php" method="POST">
                <input type="hidden" name="receiver_id" value="<?php echo $student['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="message_course_id<?php echo $student['id']; ?>" class="form-label">Хичээл</label>
                        <select class="form-select" id="message_course_id<?php echo $student['id']; ?>" name="course_id">
                            <option value="">Сонгох...</option>
                            <?php foreach ($student_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="subject<?php echo $student['id']; ?>" class="form-label">Гарчиг</label>
                        <input type="text" class="form-control" id="subject<?php echo $student['id']; ?>" name="subject">
                    </div>
                    <div class="mb-3">
                        <label for="content<?php echo $student['id']; ?>" class="form-label">Мессэж</label>
                        <textarea class="form-control" id="content<?php echo $student['id']; ?>" name="content" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                    <button type="submit" class="btn btn-primary">Илгээх</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.getElementById('studentSearch').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase().trim();
    const table = this.closest('.dashboard-section').querySelector('table');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        if (cells.length === 0) continue;
        
        const name = cells[0].textContent.toLowerCase().trim();
        const email = cells[1].textContent.toLowerCase().trim();
        
        if (name.includes(searchText) || email.includes(searchText)) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
});

// Initialize all modals
document.addEventListener('DOMContentLoaded', function() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        new bootstrap.Modal(modal);
    });
});
</script> 