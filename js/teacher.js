document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Initialize FullCalendar
    var calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: window.upcomingClasses || []
        });
        calendar.render();
    }

    // Handle tab changes
    const tabLinks = document.querySelectorAll('.nav.flex-column .nav-link');
    console.log('Found tab links:', tabLinks.length);
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Tab clicked:', this.getAttribute('href'));
            
            const target = this.getAttribute('href').substring(1);
            
            // Remove active class from all tabs and links
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('show', 'active'));
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Show target tab
            const targetTab = document.getElementById(target);
            if (targetTab) {
                targetTab.classList.add('show', 'active');
                
                // Load content if not overview tab
                if (target !== 'overview') {
                    loadTabContent(target);
                }
            }
        });
    });
});

function loadTabContent(tab) {
    console.log('Loading tab content:', tab);
    const targetTab = document.getElementById(tab);
    if (!targetTab) {
        console.error('Target tab not found:', tab);
        return;
    }
    
    // Show loading indicator
    targetTab.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Ачааллаж байна...</p>
        </div>
    `;
    
    // Load content
    fetch(`load_tab.php?tab=${tab}`)
        .then(response => {
            console.log('Tab content response:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            console.log('Received tab content');
            targetTab.innerHTML = html;
            
            // Initialize Bootstrap modals within the loaded content
            const modals = targetTab.querySelectorAll('.modal');
            modals.forEach(modalEl => {
                new bootstrap.Modal(modalEl);
            });
            
            // Initialize any other components that need to be initialized
            if (tab === 'messages') {
                initializeMessageHandlers();
            }
        })
        .catch(error => {
            console.error('Error loading tab content:', error);
            targetTab.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    Ачаалахад алдаа гарлаа. Дараа дахин оролдоно уу.
                </div>
            `;
        });
}

function initializeMessageHandlers() {
    console.log('Initializing message handlers');
    
    // Handle folder clicks
    document.querySelectorAll('.list-group-item[data-folder]').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Folder clicked:', this.dataset.folder);
            
            const folder = this.dataset.folder;
            
            // Update active state
            document.querySelectorAll('.list-group-item[data-folder]').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            // Show loading state
            const messageList = document.getElementById('messageList');
            messageList.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Ачааллаж байна...</span>
                    </div>
                    <p class="mt-2">Зурвасыг ачааллаж байна...</p>
                </div>
            `;
            
            // Load messages
            fetch(`get_messages.php?folder=${encodeURIComponent(folder)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(messages => {
                    if (!messages || messages.length === 0) {
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
                                       data-message-id="${escapeHtml(message.id)}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                ${!message.is_read ? '<span class="badge bg-primary me-2">Шинэ</span>' : ''}
                                                ${escapeHtml(message.subject)}
                                            </h6>
                                            <small>${new Date(message.created_at).toLocaleString()}</small>
                                        </div>
                                        <p class="mb-1">
                                            ${message.type === 'announcement' ? '<span class="badge bg-info me-2">Зарлал</span>' : ''}
                                            ${escapeHtml(message.content.substring(0, 100))}...
                                        </p>
                                        <small>
                                            ${message.type === 'message' 
                                                ? `${escapeHtml(message.sender_name)} → ${escapeHtml(message.recipient_name)}`
                                                : escapeHtml(message.course_name)}
                                        </small>
                                    </a>
                                `).join('')}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                    messageList.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Зурвас ачаалахад алдаа гарлаа. Дараа дахин оролдоно уу.
                        </div>
                    `;
                });
        });
    });
}

// Helper function to escape HTML
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
} 