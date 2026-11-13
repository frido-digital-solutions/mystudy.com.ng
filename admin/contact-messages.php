<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('contact_messages')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

$page_title = 'Contact Messages';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired form token. Please reload the page and try again.']);
            exit;
        }
        set_flash_message('error', 'Invalid form token. Please try again.');
        header('Location: contact-messages.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $message_id = (int)$_POST['message_id'];
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
        $stmt->execute([$message_id]);
        set_flash_message('success', 'Message marked as read');
        header('Location: contact-messages.php');
        exit;
    }
    
    if ($action === 'delete') {
        $message_id = (int)$_POST['message_id'];
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        set_flash_message('success', 'Message deleted');
        header('Location: contact-messages.php');
        exit;
    }
    
    if ($action === 'bulk_delete') {
        $message_ids = array_values(array_filter(array_map('intval', (array)($_POST['message_ids'] ?? []))));
        if (!empty($message_ids)) {
            $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id IN ($placeholders)");
            $stmt->execute($message_ids);
            set_flash_message('success', count($message_ids) . ' messages deleted');
        }
        header('Location: contact-messages.php');
        exit;
    }
    
    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE is_read = 0");
        $stmt->execute();
        set_flash_message('success', 'All messages marked as read');
        header('Location: contact-messages.php');
        exit;
    }
    
    if ($action === 'reply') {
        $message_id = (int)$_POST['message_id'];
        $reply_message = trim($_POST['reply_message']);
        $subject = trim($_POST['subject']);
        $send_email = isset($_POST['send_email']);
        
        // Get original message details
        $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        $original_message = $stmt->fetch();
        
        if ($original_message && $reply_message) {
            // Save reply to database
            $stmt = $pdo->prepare("UPDATE contact_messages SET admin_reply = ?, replied_at = NOW(), is_read = 1 WHERE id = ?");
            $stmt->execute([$reply_message, $message_id]);
            
            // Send email if requested
            if ($send_email) {
                require_once __DIR__ . '/../includes/EmailService.php';
                require_once __DIR__ . '/../includes/ContactReplyTemplate.php';
                
                $emailService = EmailService::fromConfig();
                $emailContent = ContactReplyTemplate::generate([
                    'name' => $original_message['name'],
                    'original_message' => $original_message['message'],
                    'admin_reply' => $reply_message,
                    'site_name' => SITE_NAME
                ]);
                
                try {
                    $emailService->send(
                        $original_message['email'],
                        $original_message['name'],
                        $subject,
                        $emailContent['html'],
                        $emailContent['text']
                    );
                } catch (Exception $e) {
                    error_log('Failed to send reply email: ' . $e->getMessage());
                }
            }
            
            // Always check if user is registered and send in-app notification
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$original_message['email']]);
                $user_exists = $stmt->fetch();
                
                if ($user_exists) {
                    // Create site notification for registered user mapping to their dashboard
                    $short_reply = strlen($reply_message) > 60 ? substr($reply_message, 0, 57) . '...' : $reply_message;
                    $notification_message = "Admin replied to your message: \"" . $short_reply . "\"";
                    
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, 'contact_reply', 'Support Team Reply', ?, ?, NOW())");
                    $stmt->execute([$user_exists['id'], $notification_message, '#view-reply-' . $original_message['id']]);
                }
            } catch (Exception $e) {
                error_log('Failed to create notification: ' . $e->getMessage());
            }
            
            // Return JSON response for AJAX
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
                exit;
            }
            
            set_flash_message('success', 'Reply sent successfully');
            header('Location: contact-messages.php');
            exit;
        }
    }
}

// Handle AJAX View Request
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $messageId = (int)$_GET['id'];
    
    try {
        // Fetch message details
        $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($message) {
            // Mark as read if not already
            if (!$message['is_read']) {
                $updateStmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
                $updateStmt->execute([$messageId]);
                $message['is_read'] = 1; // Update for current response
            }
            echo json_encode($message);
        } else {
            echo json_encode(['error' => 'Message not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error occurred']);
    }
    exit;
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get messages
$total_messages = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$total_pages = ceil($total_messages / $per_page);

$stmt = $pdo->prepare("
    SELECT * FROM contact_messages 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$per_page, $offset]);
$messages = $stmt->fetchAll();

// Get unread count
$unread_count = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

<style>
/* Modern Premium Styling for Contact Messages */
.message-card {
    transition: all 0.2s ease;
    border-radius: 12px;
    border: 1px solid rgba(0,0,0,0.05);
}
.message-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.025);
    border-color: rgba(16, 185, 129, 0.2);
}
.message-card.unread {
    border-left: 4px solid var(--admin-primary);
    background-color: #f8fafc;
}
.message-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--admin-primary), #059669);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.2rem;
}
.message-meta {
    font-size: 0.85rem;
    color: #64748b;
}
.message-subject {
    font-weight: 600;
    color: #1e293b;
    font-size: 1.05rem;
}
.message-snippet {
    color: #475569;
    background: #f1f5f9;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.95rem;
    border-left: 3px solid #cbd5e1;
    word-break: break-word; /* Ensure long words don't overflow on mobile */
}
@media (max-width: 768px) {
    .message-avatar {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    .message-card .d-flex.align-items-start {
        flex-direction: column;
        gap: 0.5rem !important;
    }
    .action-btn-group {
        width: 100%;
        justify-content: space-between;
        margin-top: 0.5rem;
    }
    .action-btn-group .btn {
        flex: 1;
    }
}
.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.action-btn:hover {
    transform: scale(1.1);
}
.status-badge {
    padding: 0.35rem 0.8rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.badge-new { background: #fee2e2; color: #dc2626; }
.badge-read { background: #e2e8f0; color: #475569; }
.badge-replied { background: #d1fae5; color: #059669; }
</style>

<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
      
      <!-- Premium Page Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="h3 mb-1 text-gray-800 fw-bold">
            <i class="fas fa-inbox text-primary me-2"></i>Inbox
          </h2>
          <p class="text-muted mb-0">Manage user inquiries and support requests</p>
        </div>
        <div class="d-flex align-items-center gap-3">
          <?php if ($unread_count > 0): ?>
            <div class="d-flex align-items-center bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-bold">
               <span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true" style="width: 0.75rem; height: 0.75rem;"></span>
               <?php echo $unread_count; ?> New
            </div>
          <?php endif; ?>
          
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="markAllRead()" <?php echo empty($messages) ? 'disabled' : ''; ?>>
            <i class="fas fa-check-double me-1"></i> Mark All Read
          </button>
          
          <button type="button" class="btn btn-danger rounded-pill px-4 shadow-sm" onclick="bulkDelete()" id="bulkDeleteBtn" style="display: none;">
            <i class="fas fa-trash-alt me-1"></i> Delete Selected
          </button>
        </div>
      </div>

      <?php echo display_flash_message(); ?>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="selectAll" style="transform: scale(1.2);">
            <label class="form-check-label fw-bold ms-2" for="selectAll">Select All</label>
          </div>
          <div class="text-muted small fw-600">Showing <?php echo count($messages); ?> of <?php echo $total_messages; ?></div>
        </div>
        
        <div class="card-body p-0">
          <?php if (empty($messages)): ?>
            <div class="text-center py-5 my-5">
              <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 100px; height: 100px;">
                <i class="fas fa-envelope-open-text fa-3x text-muted opacity-50"></i>
              </div>
              <h4 class="fw-bold text-gray-800">Inbox Zero!</h4>
              <p class="text-muted">You have caught up with all your messages.</p>
            </div>
          <?php else: ?>
            <form id="messagesForm" method="post">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="action" value="bulk_delete">
              
              <div class="list-group list-group-flush">
                <?php foreach ($messages as $message): 
                    $isNew = !$message['is_read'];
                    $hasReplied = !empty($message['admin_reply']);
                    $initials = strtoupper(substr($message['name'], 0, 1));
                    if (strpos($message['name'], ' ') !== false) {
                        $parts = explode(' ', $message['name']);
                        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
                    }
                ?>
                  <div class="list-group-item list-group-item-action p-4 border-bottom message-card <?php echo $isNew ? 'unread' : ''; ?> mb-1">
                    <div class="d-flex align-items-start gap-4">
                      
                      <!-- Checkbox & Avatar -->
                      <div class="d-flex align-items-center gap-3 mt-2">
                        <input type="checkbox" name="message_ids[]" value="<?php echo $message['id']; ?>" class="form-check-input message-checkbox" style="transform: scale(1.2);">
                        <div class="message-avatar shadow-sm"><?php echo $initials; ?></div>
                      </div>
                      
                      <!-- Message Content -->
                      <div class="flex-grow-1" style="min-width: 0;">
                          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start mb-1">
                          <div class="mb-2 mb-md-0">
                            <h5 class="mb-0 fw-bold <?php echo $isNew ? 'text-dark' : 'text-secondary'; ?>"><?php echo htmlspecialchars($message['name']); ?></h5>
                            <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>" class="text-muted text-decoration-none small" style="word-break: break-all;">
                                <i class="fas fa-envelope me-1 opacity-75"></i><?php echo htmlspecialchars($message['email']); ?>
                            </a>
                          </div>
                          
                          <div class="text-md-end">
                              <div class="mb-2">
                                <?php if ($hasReplied): ?>
                                  <span class="status-badge badge-replied"><i class="fas fa-reply-all me-1"></i>Replied</span>
                                <?php elseif ($isNew): ?>
                                  <span class="status-badge badge-new"><i class="fas fa-star me-1"></i>New</span>
                                <?php else: ?>
                                  <span class="status-badge badge-read"><i class="fas fa-check me-1"></i>Read</span>
                                <?php endif; ?>
                              </div>
                              <span class="message-meta d-block mt-1"><i class="far fa-clock me-1"></i><?php echo date('M j, Y • g:i A', strtotime($message['created_at'])); ?></span>
                          </div>
                        </div>
                        
                        <div class="mt-2">
                            <div class="message-subject mb-2">
                                <?php echo htmlspecialchars($message['subject'] ?: 'No Subject Specified'); ?>
                            </div>
                            <div class="message-snippet text-truncate mb-3" style="max-height: 48px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; white-space: normal;">
                                <?php echo htmlspecialchars($message['message']); ?>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 action-btn-group flex-wrap">
                            <button type="button" class="btn btn-sm btn-light text-primary fw-bold px-3 py-2 rounded-pill shadow-sm border border-primary border-opacity-25" onclick="viewMessage(<?php echo $message['id']; ?>)">
                              <i class="fas fa-expand-arrows-alt me-1"></i> View
                            </button>
                            
                            <?php if ($isNew): ?>
                              <button type="button" class="btn btn-sm btn-outline-secondary py-2 rounded-pill px-3" title="Mark as read" onclick="submitMessageAction('mark_read', <?php echo (int)$message['id']; ?>)">
                                <i class="fas fa-check me-1"></i> Read
                              </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-sm btn-outline-danger py-2 rounded-pill px-3 ms-md-auto" title="Delete" onclick="submitMessageAction('delete', <?php echo (int)$message['id']; ?>)">
                              <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </form>
          <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="card-footer bg-white border-top p-4 d-flex justify-content-center">
            <nav>
              <ul class="pagination mb-0 shadow-sm">
                <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link px-3" href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left me-1"></i>Prev</a>
                  </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                  <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                  </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link px-3" href="?page=<?php echo $page + 1; ?>">Next<i class="fas fa-chevron-right ms-1"></i></a>
                  </li>
                <?php endif; ?>
              </ul>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<!-- Beautiful Reading Modal -->
<div class="modal fade" id="messageModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-light border-bottom p-4">
        <h5 class="modal-title fw-bold text-gray-800"><i class="fas fa-envelope-open-text me-2 text-primary"></i> Message Thread</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" id="messageContent">
        <!-- Message content loaded via AJAX -->
      </div>
      <div class="modal-footer bg-light p-3 border-top">
        <button type="button" class="btn btn-light border shadow-sm rounded-pill px-4" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- One top-level form drives every single-message action and "Mark All Read".
     A second <form> nested inside #messagesForm is dropped by browsers, which is
     what made "Delete Selected" wipe just one message. -->
<form method="post" id="messageActionForm" class="d-none">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <input type="hidden" name="action" id="messageActionValue" value="">
  <input type="hidden" name="message_id" id="messageActionId" value="">
</form>

<script>
// Select all functionality
const selectAllCheckbox = document.getElementById('selectAll');
if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.message-checkbox');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        updateBulkDeleteBtn();
    });
}

document.querySelectorAll('.message-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkDeleteBtn);
});

function updateBulkDeleteBtn() {
    const checkedBoxes = document.querySelectorAll('.message-checkbox:checked');
    const bulkBtn = document.getElementById('bulkDeleteBtn');
    if (bulkBtn) {
        bulkBtn.style.display = checkedBoxes.length > 0 ? 'inline-flex' : 'none';
    }
}

function bulkDelete() {
    const checked = document.querySelectorAll('.message-checkbox:checked');
    if (checked.length === 0) {
        alert('Tick at least one message first.');
        return;
    }
    if (confirm('Delete ' + checked.length + ' selected message(s)? This cannot be undone.')) {
        document.getElementById('messagesForm').submit();
    }
}

// A <form> nested inside #messagesForm is ignored by browsers, so single-message
// actions and "Mark All Read" post through the standalone form above the script.
function submitMessageAction(action, messageId) {
    const form = document.getElementById('messageActionForm');
    if (!form) return;
    if (action === 'delete' && !confirm('Delete this message permanently?')) return;
    if (action === 'mark_all_read' && !confirm('Mark every message as read?')) return;
    document.getElementById('messageActionValue').value = action;
    document.getElementById('messageActionId').value = messageId || '';
    form.submit();
}

function markAllRead() {
    submitMessageAction('mark_all_read');
}

// Helper to escape output for JS template literal
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return String(unsafe)
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

function showModalSafe(modalId) {
    try {
        let el = document.getElementById(modalId);
        if (!el) return;
        if (typeof window.bootstrap !== 'undefined') {
            let m = bootstrap.Modal.getInstance(el);
            if (!m) m = new bootstrap.Modal(el);
            m.show();
        } else if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#' + modalId).modal('show');
        } else {
            console.error("No UI framework found to open modal");
        }
    } catch(e) {
        console.error("Modal show error:", e);
    }
}

function hideModalSafe(modalId) {
    try {
        let el = document.getElementById(modalId);
        if (!el) return;
        if (typeof window.bootstrap !== 'undefined') {
            let m = bootstrap.Modal.getInstance(el);
            if (m) m.hide();
        } else if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#' + modalId).modal('hide');
        }
    } catch(e) {
        console.error("Modal hide error:", e);
    }
}

window.viewMessage = function(messageId) {
    const contentDiv = document.getElementById('messageContent');
    if (!contentDiv) return;
    
    contentDiv.innerHTML = `
      <div class="text-center py-5">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
        <div class="mt-3 text-muted fw-bold">Loading secure message thread...</div>
      </div>
    `;
    
    showModalSafe('messageModal');

    fetch('contact-messages.php?action=view&id=' + messageId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) throw new Error("Network response was not ok");
        return response.json();
    })
    .then(data => {
        if (data.error) {
            contentDiv.innerHTML = `<div class="alert alert-danger m-4 border-0 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i>${escapeHtml(data.error)}</div>`;
            return;
        }

        const dateStr = data.created_at ? new Date(data.created_at).toLocaleString([], { dateStyle: 'long', timeStyle: 'short' }) : '';
        const initial = data.name && data.name.length > 0 ? escapeHtml(data.name.charAt(0).toUpperCase()) : '?';
        const hasReply = !!(data.admin_reply && String(data.admin_reply).trim() !== '');
        
        let safeSubj = data.subject ? escapeHtml(data.subject) : '';
        const siteName = '<?php echo addslashes(SITE_NAME); ?>';
        let replySubject = safeSubj ? `Re: ${safeSubj}` : `Reply from ${siteName}`;

        let html = `
            <div class="p-4 border-bottom bg-white">
                <h4 class="fw-bold text-gray-800 mb-4">${safeSubj || '(No Subject)'}</h4>
                
                <div class="d-flex align-items-sm-center flex-column flex-sm-row gap-3">
                    <div class="message-avatar shadow-sm flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.5rem;">${initial}</div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">${escapeHtml(data.name)}</h6>
                            <span class="text-muted small"><i class="far fa-clock me-1"></i>${dateStr}</span>
                        </div>
                        <a href="mailto:${escapeHtml(data.email)}" class="text-decoration-none small text-muted">
                            ${escapeHtml(data.email)}
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-light" style="font-size: 1.05rem; line-height: 1.7; color: #334155; white-space: pre-wrap;">${escapeHtml(data.message)}</div>
        `;
            
        if (hasReply) {
            let repliedDateStr = data.replied_at ? new Date(data.replied_at).toLocaleString([], { dateStyle: 'long', timeStyle: 'short' }) : '';
            html += `
                <div class="p-4 bg-white border-top border-4 border-top-success mt-1">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="message-avatar bg-success text-white shadow-sm flex-shrink-0" style="background: var(--admin-primary); width: 45px; height: 45px;"><i class="fas fa-headset"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold text-success">Admin Support Team</h6>
                            <span class="text-muted small"><i class="far fa-clock me-1"></i>${repliedDateStr}</span>
                        </div>
                    </div>
                    <div class="bg-success bg-opacity-10 p-4 rounded-3 text-dark" style="white-space: pre-wrap; line-height: 1.6;">${escapeHtml(data.admin_reply)}</div>
                </div>
            `;
        } else {
            html += `
                <div class="p-4 border-top bg-white">
                    <h5 class="fw-bold mb-3"><i class="fas fa-reply-all me-2 text-primary"></i>Compose Reply</h5>
                    <form id="inlineReplyForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="message_id" value="${data.id}">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">Email Subject</label>
                            <input type="text" name="subject" class="form-control" value="${replySubject}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">Your Message</label>
                            <textarea name="reply_message" class="form-control" rows="5" required placeholder="Type your response here..." style="resize: vertical;"></textarea>
                        </div>
                        
                        <div class="bg-light p-3 rounded-3 border mb-3">
                             <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                                 <input class="form-check-input mt-0" type="checkbox" name="send_email" id="inlineSendEmail" value="1" checked style="transform: scale(1.3); margin-top:0;">
                                 <label class="form-check-label fw-600 text-dark" for="inlineSendEmail" style="cursor: pointer;">
                                   Email a copy to the user
                                 </label>
                             </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary shadow-sm rounded-pill px-5 fw-bold" id="submitInlineReplyBtn">
                                <i class="fas fa-paper-plane me-2"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            `;
        }

        contentDiv.innerHTML = html;
        
        // Attach event listener if the form was injected
        const replyForm = document.getElementById('inlineReplyForm');
        if (replyForm) {
            replyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = document.getElementById('submitInlineReplyBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Sending Securely...';
                submitBtn.disabled = true;
                
                const formData = new FormData(this);
                
                fetch('contact-messages.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Server error');
                    return response.json();
                })
                .then(submitData => {
                    if (submitData.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (submitData.message || 'Unknown error.'));
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Submit Error:', error);
                    alert('Connection error while sending reply.');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        }
    })
    .catch(err => {
        console.error("Fetch error:", err);
        contentDiv.innerHTML = `<div class="alert alert-danger m-4 border-0"><i class="fas fa-exclamation-triangle me-2"></i>Error loading secure message thread.</div>`;
    });
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

