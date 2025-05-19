<?php
// Attendance tab content
?>
<div class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Ирцийн бүртгэл</h4>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#takeAttendanceModal">
                <i class="bi bi-calendar-check"></i> Ирцийн бүртгэл хийх
            </button>
            <button type="button" class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#viewReportModal">
                <i class="bi bi-file-earmark-text"></i> Тайлан харах
            </button>
        </div>
    </div>

    <?php if (empty($attendance_records)): ?>
        <div class="text-center py-4">
            <div class="text-muted">
                <i class="bi bi-calendar-x fs-4 d-block mb-2"></i>
                Ирцийн бүртгэл байхгүй байна
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Огноо</th>
                        <th>Хичээл</th>
                        <th>Сурагч</th>
                        <th>Төлөв</th>
                        <th>Тайлбар</th>
                        <th>Үйлдэл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($record['date'])); ?></td>
                            <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                            <td>
                                <?php
                                $status_class = [
                                    'present' => 'success',
                                    'absent' => 'danger',
                                    'late' => 'warning',
                                    'excused' => 'info'
                                ][$record['status']];
                                $status_text = [
                                    'present' => 'Ирсэн',
                                    'absent' => 'Тасалсан',
                                    'late' => 'Хоцорсон',
                                    'excused' => 'Зөвшөөрөлтэй'
                                ][$record['status']];
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($record['notes']); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" 
                                            class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editAttendanceModal<?php echo $record['id']; ?>"
                                            title="Засах">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteAttendanceModal<?php echo $record['id']; ?>"
                                            title="Устгах">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Attendance Modal -->
                        <div class="modal fade" id="editAttendanceModal<?php echo $record['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Ирцийн бүртгэл засах</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="edit_attendance.php" method="POST">
                                        <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="status<?php echo $record['id']; ?>" class="form-label">Төлөв</label>
                                                <select class="form-select" id="status<?php echo $record['id']; ?>" name="status" required>
                                                    <option value="present" <?php echo $record['status'] == 'present' ? 'selected' : ''; ?>>Ирсэн</option>
                                                    <option value="absent" <?php echo $record['status'] == 'absent' ? 'selected' : ''; ?>>Тасалсан</option>
                                                    <option value="late" <?php echo $record['status'] == 'late' ? 'selected' : ''; ?>>Хоцорсон</option>
                                                    <option value="excused" <?php echo $record['status'] == 'excused' ? 'selected' : ''; ?>>Зөвшөөрөлтэй</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="notes<?php echo $record['id']; ?>" class="form-label">Тайлбар</label>
                                                <textarea class="form-control" id="notes<?php echo $record['id']; ?>" 
                                                          name="notes" rows="3"><?php echo htmlspecialchars($record['notes']); ?></textarea>
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

                        <!-- Delete Attendance Modal -->
                        <div class="modal fade" id="deleteAttendanceModal<?php echo $record['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Ирцийн бүртгэл устгах</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Та дараах ирцийн бүртгэлийг устгахдаа итгэлтэй байна уу?</p>
                                        <p>
                                            <strong>
                                                <?php echo htmlspecialchars($record['student_name']); ?> - 
                                                <?php echo date('Y-m-d', strtotime($record['date'])); ?>
                                            </strong>
                                        </p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                                        <form action="delete_attendance.php" method="POST" class="d-inline">
                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
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

<!-- Take Attendance Modal -->
<div class="modal fade" id="takeAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ирцийн бүртгэл хийх</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="take_attendance.php" method="POST">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <label for="date" class="form-label">Огноо</label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Сурагч</th>
                                    <th>Төлөв</th>
                                    <th>Тайлбар</th>
                                </tr>
                            </thead>
                            <tbody id="studentsList">
                                <!-- Students will be loaded here via AJAX -->
                            </tbody>
                        </table>
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

<!-- View Report Modal -->
<div class="modal fade" id="viewReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ирцийн тайлан</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="report_course_id" class="form-label">Хичээл</label>
                        <select class="form-select" id="report_course_id" required>
                            <option value="">Сонгох...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="report_date_range" class="form-label">Огнооны хүрээ</label>
                        <input type="text" class="form-control" id="report_date_range">
                    </div>
                </div>
                <div id="reportContent">
                    <!-- Report will be loaded here via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Хаах</button>
                <button type="button" class="btn btn-primary" id="exportReport">
                    <i class="bi bi-download"></i> Татаж авах
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Load students when course is selected
document.getElementById('course_id').addEventListener('change', function() {
    const courseId = this.value;
    const tbody = document.getElementById('studentsList');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center">Сурагчдыг ачаалж байна...</td></tr>'; // Loading indicator

    if (courseId) {
        fetch(`get_students.php?course_id=${courseId}`)
            .then(response => {
                if (!response.ok) {
                    // Log non-200 responses
                    console.error('HTTP error!', response.status, response.statusText);
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Сурагчдыг ачаалахад алдаа гарлаа.</td></tr>';
                    return Promise.reject('HTTP error'); // Propagate error
                }
                return response.json();
            })
            .then(students => {
                console.log('Fetched students:', students);
                if (students.length > 0) {
                    tbody.innerHTML = students.map(student => `
                        <tr>
                            <td>${student.name}</td>
                            <td>
                                <select class="form-select" name="status[${student.id}]" required>
                                    <option value="present">Ирсэн</option>
                                    <option value="absent">Тасалсан</option>
                                    <option value="late">Хоцорсон</option>
                                    <option value="excused">Зөвшөөрөлтэй</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control" name="notes[${student.id}]">
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center">Энэ хичээлд бүртгэлтэй сурагч байхгүй байна.</td></tr>';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                 if (error !== 'HTTP error') { // Avoid double error message for HTTP errors
                     tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Сурагчдыг ачаалахад алдаа гарлаа.</td></tr>';
                 }
            });
    } else {
         tbody.innerHTML = '<tr><td colspan="3" class="text-center">Хичээл сонгоно уу.</td></tr>';
    }
});

// Initialize date range picker
document.getElementById('report_date_range').addEventListener('change', function() {
    const courseId = document.getElementById('report_course_id').value;
    const dateRange = this.value;
    if (courseId && dateRange) {
        fetch(`get_attendance_report.php?course_id=${courseId}&date_range=${dateRange}`)
            .then(response => response.json())
            .then(report => {
                const content = document.getElementById('reportContent');
                content.innerHTML = `
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Сурагч</th>
                                    <th>Ирсэн</th>
                                    <th>Тасалсан</th>
                                    <th>Хоцорсон</th>
                                    <th>Зөвшөөрөлтэй</th>
                                    <th>Ирцийн хувь</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${report.map(row => `
                                    <tr>
                                        <td>${row.student_name}</td>
                                        <td>${row.present}</td>
                                        <td>${row.absent}</td>
                                        <td>${row.late}</td>
                                        <td>${row.excused}</td>
                                        <td>${row.attendance_rate}%</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            });
    }
});

// Export report
document.getElementById('exportReport').addEventListener('click', function() {
    const courseId = document.getElementById('report_course_id').value;
    const dateRange = document.getElementById('report_date_range').value;
    if (courseId && dateRange) {
        window.location.href = `export_attendance_report.php?course_id=${courseId}&date_range=${dateRange}`;
    }
});
</script> 