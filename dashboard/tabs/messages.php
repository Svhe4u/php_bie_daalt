<?php
// Messages tab content
?>
<div class="dashboard-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Зурвас</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
            <i class="bi bi-envelope-plus"></i> Шинэ зурвас
        </button>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="list-group">
                <a href="#" class="list-group-item list-group-item-action active" data-folder="inbox">
                    <i class="bi bi-inbox"></i> Ирсэн зурвас
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="#" class="list-group-item list-group-item-action" data-folder="sent">
                    <i class="bi bi-send"></i> Илгээсэн зурвас
                </a>
                <a href="#" class="list-group-item list-group-item-action" data-folder="announcements">
                    <i class="bi bi-megaphone"></i> Зарлал
                </a>
            </div>
        </div>
        <div class="col-md-8">
            <div id="messageList">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-4">
                        <div class="text-muted">
                            <i class="bi bi-envelope-x fs-4 d-block mb-2"></i>
                            Зурвас байхгүй байна
                        </div>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($messages as $message): ?>
                            <a href="#" class="list-group-item list-group-item-action message-item <?php echo $message['is_read'] ? '' : 'unread'; ?>" 
                               data-message-id="<?php echo $message['id']; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php if (!$message['is_read']): ?>
                                            <span class="badge bg-primary me-2">Шинэ</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($message['subject']); ?>
                                    </h6>
                                    <small><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></small>
                                </div>
                                <p class="mb-1">
                                    <?php if ($message['type'] == 'announcement'): ?>
                                        <span class="badge bg-info me-2">Зарлал</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars(substr($message['content'], 0, 100)) . '...'; ?>
                                </p>
                                <small>
                                    <?php if ($message['type'] == 'message'): ?>
                                        <?php echo $message['sender_name']; ?> →
                                        <?php echo $message['recipient_name']; ?>
                                    <?php else: ?>
                                        <?php echo $message['course_name']; ?>
                                    <?php endif; ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="modal fade" id="newMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Шинэ зурвас</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="send_message.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="message_type" class="form-label">Төрөл</label>
                        <select class="form-select" id="message_type" name="type" required>
                            <option value="message">Зурвас</option>
                            <option value="announcement">Зарлал</option>
                        </select>
                    </div>
                    <div class="mb-3" id="recipientGroup">
                        <label for="recipient_id" class="form-label">Хүлээн авагч</label>
                        <select class="form-select" id="recipient_id" name="recipient_id">
                            <option value="">Сонгох...</option>
                            <?php
                            $stmt = $conn->prepare("
                                SELECT u.id, u.name, u.role
                                FROM users u
                                WHERE u.id != ?
                                ORDER BY u.role, u.name
                            ");
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            foreach ($users as $user):
                            ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['name']); ?> 
                                    (<?php echo $user['role'] == 'teacher' ? 'Багш' : 'Сурагч'; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="courseGroup" style="display: none;">
                        <label for="course_id" class="form-label">Хичээл</label>
                        <select class="form-select" id="course_id" name="course_id">
                            <option value="">Сонгох...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label">Гарчиг</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">Агуулга</label>
                        <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
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

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageSubject"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <small class="text-muted" id="messageInfo"></small>
                </div>
                <div id="messageContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Хаах</button>
                <button type="button" class="btn btn-primary" id="replyButton">
                    <i class="bi bi-reply"></i> Хариулах
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle recipient/course selection based on message type
document.getElementById('message_type').addEventListener('change', function() {
    const recipientGroup = document.getElementById('recipientGroup');
    const courseGroup = document.getElementById('courseGroup');
    const recipientSelect = document.getElementById('recipient_id');
    const courseSelect = document.getElementById('course_id');

    if (this.value === 'announcement') {
        recipientGroup.style.display = 'none';
        courseGroup.style.display = 'block';
        recipientSelect.removeAttribute('required');
        courseSelect.setAttribute('required', 'required');
    } else {
        recipientGroup.style.display = 'block';
        courseGroup.style.display = 'none';
        recipientSelect.setAttribute('required', 'required');
        courseSelect.removeAttribute('required');
    }
});

// Load messages for different folders
document.querySelectorAll('.list-group-item[data-folder]').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        const folder = this.dataset.folder;
        
        // Update active state
        document.querySelectorAll('.list-group-item[data-folder]').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        
        // Load messages
        fetch(`get_messages.php?folder=${folder}`)
            .then(response => response.json())
            .then(messages => {
                const messageList = document.getElementById('messageList');
                if (messages.length === 0) {
                    messageList.innerHTML = `
                        <div class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-envelope-x fs-4 d-block mb-2"></i>
                                Зурвас байхгүй байна
                            </div>
                        </div>
                    `;
                } else {
                    messageList.innerHTML = `
                        <div class="list-group">
                            ${messages.map(message => `
                                <a href="#" class="list-group-item list-group-item-action message-item ${message.is_read ? '' : 'unread'}" 
                                   data-message-id="${message.id}">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            ${!message.is_read ? '<span class="badge bg-primary me-2">Шинэ</span>' : ''}
                                            ${message.subject}
                                        </h6>
                                        <small>${new Date(message.created_at).toLocaleString()}</small>
                                    </div>
                                    <p class="mb-1">
                                        ${message.type === 'announcement' ? '<span class="badge bg-info me-2">Зарлал</span>' : ''}
                                        ${message.content.substring(0, 100)}...
                                    </p>
                                    <small>
                                        ${message.type === 'message' 
                                            ? `${message.sender_name} → ${message.recipient_name}`
                                            : message.course_name}
                                    </small>
                                </a>
                            `).join('')}
                        </div>
                    `;
                }
            });
    });
});

// View message
document.addEventListener('click', function(e) {
    if (e.target.closest('.message-item')) {
        e.preventDefault();
        const messageId = e.target.closest('.message-item').dataset.messageId;
        
        fetch(`get_message.php?id=${messageId}`)
            .then(response => response.json())
            .then(message => {
                document.getElementById('messageSubject').textContent = message.subject;
                document.getElementById('messageInfo').textContent = `
                    ${message.type === 'message' 
                        ? `${message.sender_name} → ${message.recipient_name}`
                        : message.course_name} | 
                    ${new Date(message.created_at).toLocaleString()}
                `;
                document.getElementById('messageContent').textContent = message.content;
                
                const viewMessageModal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
                viewMessageModal.show();
                
                // Mark as read
                if (!message.is_read) {
                    fetch(`mark_message_read.php?id=${messageId}`);
                }
            });
    }
});

// Reply to message
document.getElementById('replyButton').addEventListener('click', function() {
    const message = {
        subject: document.getElementById('messageSubject').textContent,
        sender: document.getElementById('messageInfo').textContent.split(' → ')[0].trim()
    };
    
    const newMessageModal = new bootstrap.Modal(document.getElementById('newMessageModal'));
    document.getElementById('message_type').value = 'message';
    document.getElementById('recipient_id').value = message.sender;
    document.getElementById('subject').value = `Re: ${message.subject}`;
    
    bootstrap.Modal.getInstance(document.getElementById('viewMessageModal')).hide();
    newMessageModal.show();
});
</script>

<style>
.message-item.unread {
    background-color: #f8f9fa;
    font-weight: 500;
}

.message-item:hover {
    background-color: #e9ecef;
}

#messageContent {
    white-space: pre-wrap;
}
</style> 