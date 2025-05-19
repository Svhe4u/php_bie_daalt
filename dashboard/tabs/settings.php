<?php
// Settings tab content
?>
<div class="dashboard-section">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if ($user['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                 alt="Profile" class="rounded-circle img-fluid" style="width: 150px;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 150px; height: 150px; font-size: 4rem;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#changePhotoModal">
                        <i class="bi bi-camera"></i> Зураг солих
                    </button>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Хэрэглэгчийн тохиргоо</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action active" data-bs-toggle="list" data-target="profile">
                        <i class="bi bi-person"></i> Хувийн мэдээлэл
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="list" data-target="security">
                        <i class="bi bi-shield-lock"></i> Нууц үг
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="list" data-target="notifications">
                        <i class="bi bi-bell"></i> Мэдэгдэл
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="list" data-target="preferences">
                        <i class="bi bi-gear"></i> Тохиргоо
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="tab-content">
                <!-- Profile Settings -->
                <div class="tab-pane fade show active" id="profile">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Хувийн мэдээлэл</h5>
                        </div>
                        <div class="card-body">
                            <form action="update_profile.php" method="POST">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Нэр</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">И-мэйл</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Утас</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Товч танилцуулга</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Хадгалах</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="tab-pane fade" id="security">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Нууц үг солих</h5>
                        </div>
                        <div class="card-body">
                            <form action="change_password.php" method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Одоогийн нууц үг</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Шинэ нууц үг</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Шинэ нууц үг давтах</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Нууц үг солих</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="tab-pane fade" id="notifications">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Мэдэгдлийн тохиргоо</h5>
                        </div>
                        <div class="card-body">
                            <form action="update_notifications.php" method="POST">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                               <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">И-мэйл мэдэгдэл</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="assignment_notifications" name="assignment_notifications" 
                                               <?php echo $settings['assignment_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="assignment_notifications">Даалгаврын мэдэгдэл</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="message_notifications" name="message_notifications" 
                                               <?php echo $settings['message_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="message_notifications">Зурвасын мэдэгдэл</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Хадгалах</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Preferences Settings -->
                <div class="tab-pane fade" id="preferences">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Тохиргоо</h5>
                        </div>
                        <div class="card-body">
                            <form action="update_preferences.php" method="POST">
                                <div class="mb-3">
                                    <label for="language" class="form-label">Хэл</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="mn" <?php echo $settings['language'] == 'mn' ? 'selected' : ''; ?>>Монгол</option>
                                        <option value="en" <?php echo $settings['language'] == 'en' ? 'selected' : ''; ?>>English</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Цагийн бүс</label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <option value="Asia/Ulaanbaatar" <?php echo $settings['timezone'] == 'Asia/Ulaanbaatar' ? 'selected' : ''; ?>>
                                            Улаанбаатар (UTC+8)
                                        </option>
                                        <option value="UTC" <?php echo $settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>
                                            UTC
                                        </option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="date_format" class="form-label">Огнооны формат</label>
                                    <select class="form-select" id="date_format" name="date_format">
                                        <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>
                                            YYYY-MM-DD
                                        </option>
                                        <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>
                                            DD/MM/YYYY
                                        </option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Хадгалах</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Photo Modal -->
<div class="modal fade" id="changePhotoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Профайл зураг солих</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_photo.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Зураг сонгох</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" required>
                        <div class="form-text">
                            Зөвшөөрөгдсөн формат: JPG, PNG, GIF. Хамгийн их хэмжээ: 2MB
                        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabLinks = document.querySelectorAll('.list-group-item[data-bs-toggle="list"]');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links and panes
            tabLinks.forEach(l => l.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('show', 'active'));
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Show corresponding tab pane
            const targetId = this.getAttribute('data-target');
            const targetPane = document.getElementById(targetId);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }
        });
    });

    // Password validation
    const passwordForm = document.querySelector('form[action="change_password.php"]');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Шинэ нууц үг таарахгүй байна.');
            }
        });
    }

    // Profile image validation
    const profileImageInput = document.getElementById('profile_image');
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function() {
            const file = this.files[0];
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 2 * 1024 * 1024; // 2MB
            
            if (file) {
                if (!allowedTypes.includes(file.type)) {
                    alert('Зөвшөөрөгдөөгүй файлын төрөл байна. Зөвшөөрөгдсөн төрлүүд: JPG, PNG, GIF');
                    this.value = '';
                } else if (file.size > maxSize) {
                    alert('Файлын хэмжээ хэт их байна. Хамгийн их хэмжээ: 2MB');
                    this.value = '';
                }
            }
        });
    }

    // Notification settings form handling
    const notificationForm = document.querySelector('form[action="update_notifications.php"]');
    if (notificationForm) {
        notificationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('update_notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Мэдэгдлийн тохиргоо амжилттай шинэчлэгдлээ.');
                } else {
                    alert('Алдаа гарлаа: ' + data.message);
                }
            })
            .catch(error => {
                alert('Алдаа гарлаа. Дараа дахин оролдоно уу.');
            });
        });
    }

    // Preferences form handling
    const preferencesForm = document.querySelector('form[action="update_preferences.php"]');
    if (preferencesForm) {
        preferencesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('update_preferences.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Тохиргоо амжилттай шинэчлэгдлээ.');
                } else {
                    alert('Алдаа гарлаа: ' + data.message);
                }
            })
            .catch(error => {
                alert('Алдаа гарлаа. Дараа дахин оролдоно уу.');
            });
        });
    }
});
</script>

<style>
.profile-image {
    width: 150px;
    height: 150px;
    object-fit: cover;
}

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.tab-pane {
    padding: 1rem 0;
}

.list-group-item.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.list-group-item {
    cursor: pointer;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}
</style> 