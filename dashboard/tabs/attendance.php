<?php
// Attendance tab content
?>
<!-- Add required CSS and JS dependencies -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

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
                            <td><?php echo htmlspecialchars($record['note'] ?? ''); ?></td>
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
                                                          name="notes" rows="3"><?php echo htmlspecialchars($record['note'] ?? ''); ?></textarea>
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
$(document).ready(function() {
    // Initialize date range picker
    $('#report_date_range').daterangepicker({
        locale: {
            format: 'YYYY-MM-DD',
            applyLabel: 'Сонгох',
            cancelLabel: 'Болих',
            fromLabel: 'Эхлэх',
            toLabel: 'Дуусах',
            customRangeLabel: 'Өөр',
            daysOfWeek: ['Ня', 'Да', 'Мя', 'Лх', 'Пү', 'Ба', 'Бя'],
            monthNames: ['1-р сар', '2-р сар', '3-р сар', '4-р сар', '5-р сар', '6-р сар', '7-р сар', '8-р сар', '9-р сар', '10-р сар', '11-р сар', '12-р сар'],
            firstDay: 1
        },
        startDate: moment().subtract(30, 'days'),
        endDate: moment(),
        ranges: {
            'Энэ 7 хоног': [moment().subtract(6, 'days'), moment()],
            'Энэ сар': [moment().startOf('month'), moment().endOf('month')],
            'Өнгөрсөн сар': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    });

    // Handle course selection for taking attendance
    $(document).on('change', '#course_id', function() {
        console.log('Course select change event fired.');
        var courseId = $(this).val();
        console.log('Selected Course ID:', courseId);
        
        if (courseId) {
            $('#studentsList').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
            
            // Log the URL we're calling
            var url = 'get_students.php?course_id=' + courseId;
            console.log('Calling URL:', url);
            
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('get_students.php response:', response);
                    if (response.error) {
                        $('#studentsList').html('<div class="alert alert-danger">' + response.error + '</div>');
                    } else {
                        var html = '';
                        if (response.length > 0) {
                            response.forEach(function(student) {
                                html += `
                                <tr>
                                    <td>${student.name}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <input type="radio" class="btn-check" name="status[${student.id}]" id="present_${student.id}" value="present" checked>
                                            <label class="btn btn-outline-success" for="present_${student.id}">Ирсэн</label>
                                            
                                            <input type="radio" class="btn-check" name="status[${student.id}]" id="absent_${student.id}" value="absent">
                                            <label class="btn btn-outline-danger" for="absent_${student.id}">Тасалсан</label>
                                            
                                            <input type="radio" class="btn-check" name="status[${student.id}]" id="late_${student.id}" value="late">
                                            <label class="btn btn-outline-warning" for="late_${student.id}">Хоцорсон</label>
                                            
                                            <input type="radio" class="btn-check" name="status[${student.id}]" id="excused_${student.id}" value="excused">
                                            <label class="btn btn-outline-info" for="excused_${student.id}">Зөвшөөрөлтэй</label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="notes[${student.id}]" placeholder="Тайлбар...">
                                    </td>
                                </tr>`;
                            });
                            $('#studentsList').html(html);
                        } else {
                            $('#studentsList').html('<tr><td colspan="3" class="text-center">Энэ хичээлд бүртгэлтэй сурагч байхгүй байна.</td></tr>');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('get_students.php AJAX error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    $('#studentsList').html('<div class="alert alert-danger">Сурагчдын мэдээлэл ачаалахад алдаа гарлаа. Дэлгэрэнгүй: ' + error + '</div>');
                }
            });
        } else {
            $('#studentsList').html('');
        }
    });

    // Handle attendance form submission
    $(document).on('submit', '#takeAttendanceModal form', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'take_attendance.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.error) {
                    alert(response.error);
                } else {
                    alert('Ирцийн бүртгэл амжилттай хадгалагдлаа.');
                    $('#takeAttendanceModal').modal('hide');
                    location.reload();
                }
            },
            error: function() {
                alert('Ирцийн бүртгэл хадгалахад алдаа гарлаа.');
            }
        });
    });

    // Handle report generation
    $(document).on('change', '#report_course_id', function() {
        var courseId = $(this).val();
        var dateRange = $('#report_date_range').val();
        
        if (courseId && dateRange) {
            $('#reportContent').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
            
            $.ajax({
                url: 'get_attendance_report.php',
                type: 'GET',
                data: {
                    course_id: courseId,
                    date_range: dateRange
                },
                success: function(response) {
                    if (response.error) {
                        $('#reportContent').html('<div class="alert alert-danger">' + response.error + '</div>');
                    } else {
                        var html = '<div class="table-responsive"><table class="table table-striped">';
                        html += '<thead><tr><th>Сурагч</th><th>Ирсэн</th><th>Тасалсан</th><th>Хоцорсон</th><th>Зөвшөөрөлтэй</th><th>Нийт</th><th>Ирцийн хувь</th></tr></thead><tbody>';
                        
                        response.forEach(function(row) {
                            var rateClass = row.attendance_rate >= 80 ? 'success' : 
                                          row.attendance_rate >= 60 ? 'warning' : 'danger';
                            
                            html += `<tr>
                                <td>${row.student_name}</td>
                                <td>${row.present}</td>
                                <td>${row.absent}</td>
                                <td>${row.late}</td>
                                <td>${row.excused}</td>
                                <td>${row.total_classes}</td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-${rateClass}" role="progressbar" 
                                             style="width: ${row.attendance_rate}%" 
                                             aria-valuenow="${row.attendance_rate}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            ${row.attendance_rate}%
                                        </div>
                                    </div>
                                </td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table></div>';
                        $('#reportContent').html(html);
                    }
                },
                error: function() {
                    $('#reportContent').html('<div class="alert alert-danger">Тайлан үүсгэхэд алдаа гарлаа.</div>');
                }
            });
        }
    });

    // Handle export report
    $(document).on('click', '#exportReport', function() {
        var courseId = $('#report_course_id').val();
        var dateRange = $('#report_date_range').val();
        
        if (!courseId || !dateRange) {
            alert('Хичээл болон огноо сонгоно уу.');
            return;
        }
        
        window.location.href = 'export_attendance_report.php?course_id=' + courseId + '&date_range=' + encodeURIComponent(dateRange);
    });

    // Handle edit attendance
    $('.edit-attendance').click(function() {
        var attendanceId = $(this).data('id');
        var status = $(this).data('status');
        var notes = $(this).data('notes');
        
        $('#edit_attendance_id').val(attendanceId);
        $(`#edit_status_${status}`).prop('checked', true);
        $('#edit_notes').val(notes);
    });

    // Handle edit form submission
    $('#edit_attendance_form').submit(function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'edit_attendance.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.error) {
                    alert(response.error);
                } else {
                    alert('Ирцийн бүртгэл амжилттай шинэчлэгдлээ.');
                    $('#editAttendanceModal').modal('hide');
                    location.reload();
                }
            },
            error: function() {
                alert('Ирцийн бүртгэл шинэчлэхэд алдаа гарлаа.');
            }
        });
    });

    // Handle delete attendance
    $('.delete-attendance').click(function() {
        if (confirm('Энэ ирцийн бүртгэлийг устгахдаа итгэлтэй байна уу?')) {
            var attendanceId = $(this).data('id');
            
            $.ajax({
                url: 'delete_attendance.php',
                type: 'POST',
                data: { attendance_id: attendanceId },
                success: function(response) {
                    if (response.error) {
                        alert(response.error);
                    } else {
                        alert('Ирцийн бүртгэл амжилттай устгагдлаа.');
                        location.reload();
                    }
                },
                error: function() {
                    alert('Ирцийн бүртгэл устгахад алдаа гарлаа.');
                }
            });
        }
    });
});
</script> 