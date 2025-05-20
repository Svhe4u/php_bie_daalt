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
                                    <a href="#" class="course-details-trigger" data-course-id="<?php echo $course['id']; ?>" title="Хичээлийн дэлгэрэнгүй">
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </a>
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
                                <?php if ($course['average_grade']): ?>
                                    <span class="badge bg-<?php echo $course['average_grade'] >= 60 ? 'success' : 'danger'; ?> course-details-trigger" data-course-id="<?php echo $course['id']; ?>" title="Хичээлийн дэлгэрэнгүй" style="cursor: pointer;">
                                        <?php echo number_format($course['average_grade'], 1); ?>
                                    </span>
                                <?php else: // Handle case where average_grade is null or 0 ?>
                                    <span class="text-muted">Дүн байхгүй</span>
                                <?php endif; ?>
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

<!-- View Students Modal (for Classes tab) -->
<?php foreach ($courses as $course): ?>
<div class="modal fade" id="studentsModal<?php echo $course['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Сурагчид: <?php echo htmlspecialchars($course['name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="studentsList<?php echo $course['id']; ?>">
                    <!-- Students for this course will be loaded here via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Ачааллаж байна...</span>
                        </div>
                        <p class="mt-2">Сурагчдын жагсаалтыг ачааллаж байна...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Хаах</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Course Details Modal -->
<div class="modal fade" id="courseDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Хичээлийн дэлгэрэнгүй</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Нэр:</strong> <span id="detail-course-name"></span></p>
                <p><strong>Багш:</strong> <span id="detail-teacher-name"></span></p>
                <p><strong>Бүртгэлтэй сурагч:</strong> <span id="detail-enrolled-students"></span></p>
                <p><strong>Нийт даалгавар:</strong> <span id="detail-total-assignments"></span></p>
                <p><strong>Нийт материал:</strong> <span id="detail-total-materials"></span></p>
                <p><strong>Нийт үнэлгээ:</strong> <span id="detail-total-evaluations"></span></p>
                <p><strong>Дундаж үнэлгээ:</strong> <span id="detail-average-score"></span></p>
                <p><strong>Дүн гарсан сурагч:</strong> <span id="detail-graded-count"></span></p>
                <p><strong>Дундаж дүн:</strong> <span id="detail-average-grade"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Хаах</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle showing the students modal and loading data via AJAX
    $(document).on('shown.bs.modal', '[id^="studentsModal"]', function () {
        var modalId = $(this).attr('id');
        var courseId = modalId.replace('studentsModal', '');
        var studentsListDiv = $(this).find('[id^="studentsList"]');
        
        console.log('Students Modal shown for Course ID:', courseId);
        
        // Load students for this course via AJAX
        studentsListDiv.html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ачааллаж байна...</span></div><p class="mt-2">Сурагчдын жагсаалтыг ачааллаж байна...</p></div>');

        $.ajax({
            url: 'get_students_by_course.php', // We will create this file
            type: 'GET',
            data: { course_id: courseId },
            success: function(response) {
                console.log('get_students_by_course.php response:', response);
                if (response.error) {
                    studentsListDiv.html('<div class="alert alert-danger">' + response.error + '</div>');
                } else {
                    var html = '';
                    if (response.length > 0) {
                        html += '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Нэр</th><th>И-мэйл</th></tr></thead><tbody>';
                        response.forEach(function(student) {
                            html += `
                                <tr>
                                    <td>${student.name}</td>
                                    <td>${student.email}</td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table></div>';
                        studentsListDiv.html(html);
                    } else {
                        studentsListDiv.html('<div class="text-center text-muted py-4">Энэ хичээлд бүртгэлтэй сурагч байхгүй байна.</div>');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('get_students_by_course.php AJAX error:', { status: status, error: error, response: xhr.responseText });
                studentsListDiv.html('<div class="alert alert-danger">Сурагчдын мэдээлэл ачаалахад алдаа гарлаа.</div>');
            }
        });
    });

    // Handle clicking on course name or average grade to show details modal
    $(document).on('click', '.course-details-trigger', function() {
        var courseId = $(this).data('course-id');
        console.log('Course details trigger clicked for Course ID:', courseId);

        // Find the corresponding course data from the PHP array
        // This assumes the $courses PHP array is available in the script context
        var course = <?php echo json_encode($courses); ?>.find(c => c.id == courseId);

        if (course) {
            // Populate the modal with course details
            $('#detail-course-name').text(course.name);
            // Note: Teacher name is not available in the $courses array here. 
            // You might need to fetch it separately if needed.
            $('#detail-teacher-name').text('N/A'); // Placeholder
            $('#detail-enrolled-students').text(course.enrolled_students);
            $('#detail-total-assignments').text(course.total_assignments);
            $('#detail_materials').text(course.total_materials);
            // Assuming these fields are now available from the updated query
            $('#detail-total-evaluations').text(course.total_evaluations ?? 'N/A');
            $('#detail-average-score').text(course.average_score ? parseFloat(course.average_score).toFixed(1) : 'N/A');
            $('#detail-graded-count').text(course.graded_count ?? 'N/A');
            $('#detail-average-grade').text(course.average_grade ? parseFloat(course.average_grade).toFixed(1) : 'N/A');

            // Show the modal
            var courseDetailsModal = new bootstrap.Modal(document.getElementById('courseDetailsModal'));
            courseDetailsModal.show();
        } else {
            console.error('Course data not found for ID:', courseId);
        }
    });
});

</script> 