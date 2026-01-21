<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
$activeChatId = (int)($_GET['chat'] ?? 0);

ob_start();
?>
<div class="messaging-layout">
    <div class="contacts-sidebar">
        <div class="sidebar-header">
            <h3>Messages</h3>
            <button class="btn btn-icon" title="New Chat" onclick="toggleSearch()"><span class="material-symbols-outlined">person_add</span></button>
        </div>

        <div id="search-container" class="search-container" style="display:none;">
            <input type="text" id="user-search" placeholder="Search people..." onkeyup="searchUsers(this.value)">
            <div id="search-results" class="search-results"></div>
        </div>

        <div class="contacts-list" id="contacts-list">
            <?php
            $contacts = MessageService::getContacts((int)$u['user_id']);
            if (empty($contacts)): ?>
                <div class="empty-state">No conversations yet.</div>
            <?php else: ?>
                <?php foreach ($contacts as $c): ?>
                    <div class="contact-item <?= $activeChatId === (int)$c['user_id'] ? 'active' : '' ?>" onclick="loadChat(<?= $c['user_id'] ?>, '<?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>')">
                        <img class="contact-avatar-img" src="<?= FileService::getAvatarUrl((int)$c['user_id']) ?>"
                            onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            alt="">
                        <div class="contact-avatar" style="display:none;">
                            <?= substr($c['first_name'], 0, 1) . substr($c['last_name'], 0, 1) ?>
                        </div>
                        <div class="contact-info">
                            <div class="contact-name"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                            <div class="contact-last-msg"><?= htmlspecialchars($c['last_message'] ?? '') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-main">
        <div id="chat-header" class="chat-header" style="display:<?= $activeChatId ? 'flex' : 'none' ?>;">
            <div id="active-contact-name" class="active-contact-name">Select a conversation</div>
        </div>

        <div class="chat-history" id="chat-history">
            <?php if (!$activeChatId): ?>
                <div class="chat-welcome">
                    <span class="material-symbols-outlined">forum</span>
                    <h3>Your Messages</h3>
                    <p>Select a contact to start chatting or search for someone new.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="chat-composer" class="chat-composer" style="display:<?= $activeChatId ? 'flex' : 'none' ?>;">
            <form id="message-form" style="width:100%; display:flex; gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                <input type="hidden" name="receiver_id" id="receiver_id" value="<?= $activeChatId ?>">
                <input type="hidden" name="action" value="send">
                <input type="text" name="message" id="message-input" placeholder="Type a message..." autocomplete="off">
                <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined">send</span></button>
            </form>
        </div>
    </div>
</div>

<style>
    .messaging-layout {
        display: flex;
        height: calc(100vh - 200px);
        background: var(--surface);
        border-radius: 12px;
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .contacts-sidebar {
        width: 300px;
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        background: var(--surface-hover);
    }

    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-header h3 {
        margin: 0;
        font-size: 18px;
    }

    .contacts-list {
        flex: 1;
        overflow-y: auto;
    }

    .contact-item {
        padding: 15px 20px;
        display: flex;
        gap: 12px;
        cursor: pointer;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: background 0.2s;
    }

    .contact-item:hover {
        background: var(--border);
    }

    .contact-item.active {
        background: var(--border);
        border-left: 4px solid var(--primary);
    }

    .contact-avatar-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .contact-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        flex-shrink: 0;
    }

    .contact-info {
        overflow: hidden;
    }

    .contact-name {
        font-weight: 600;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .contact-last-msg {
        font-size: 12px;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 2px;
    }

    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: var(--surface);
    }

    .chat-header {
        padding: 15px 25px;
        border-bottom: 1px solid var(--border);
        align-items: center;
    }

    .active-contact-name {
        font-weight: 700;
        font-size: 16px;
    }

    .chat-history {
        flex: 1;
        overflow-y: auto;
        padding: 25px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .chat-welcome {
        margin: auto;
        text-align: center;
        color: var(--text-secondary);
    }

    .chat-welcome .material-symbols-outlined {
        font-size: 64px;
        opacity: 0.3;
        margin-bottom: 10px;
    }

    .chat-composer {
        padding: 20px;
        border-top: 1px solid var(--border);
    }

    .chat-composer input {
        flex: 1;
        padding: 12px 18px;
        border-radius: 25px;
        border: 1px solid var(--border);
        background: var(--surface-hover);
    }

    .msg-bubble {
        max-width: 70%;
        padding: 12px 16px;
        border-radius: 18px;
        font-size: 14px;
        line-height: 1.4;
        position: relative;
    }

    .msg-sent {
        align-self: flex-end;
        background: var(--primary);
        color: white;
        border-bottom-right-radius: 4px;
    }

    .msg-received {
        align-self: flex-start;
        background: var(--surface-hover);
        color: var(--text-primary);
        border-bottom-left-radius: 4px;
        border: 1px solid var(--border);
    }

    .msg-time {
        font-size: 10px;
        margin-top: 5px;
        opacity: 0.7;
        display: block;
    }

    .search-container {
        padding: 10px 20px;
        border-bottom: 1px solid var(--border);
    }

    .search-container input {
        width: 100%;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid var(--border);
        font-size: 13px;
    }

    .search-results {
        max-height: 200px;
        overflow-y: auto;
        margin-top: 10px;
    }

    .search-result-item {
        padding: 8px;
        cursor: pointer;
        border-radius: 4px;
        font-size: 13px;
    }

    .search-result-item:hover {
        background: var(--border);
    }
</style>

<script>
    let currentChatId = <?= $activeChatId ?>;
    const myUserId = <?= (int)$u['user_id'] ?>;
    const getBase = () => document.documentElement.dataset.baseUrl || '';
    let searchTimeout = null;
    let lastMessageId = 0;
    let isAtBottom = true;

    const chatHistory = document.getElementById('chat-history');
    chatHistory.addEventListener('scroll', () => {
        isAtBottom = chatHistory.scrollHeight - chatHistory.scrollTop <= chatHistory.clientHeight + 50;
    });

    function toggleSearch() {
        const s = document.getElementById('search-container');
        s.style.display = s.style.display === 'none' ? 'block' : 'none';
        if (s.style.display === 'block') document.getElementById('user-search').focus();
    }

    function searchUsers(q) {
        clearTimeout(searchTimeout);
        if (q.length < 2) {
            document.getElementById('search-results').innerHTML = '';
            return;
        }
        searchTimeout = setTimeout(() => {
            fetch(getBase() + '/controllers/message-handler.php?action=search_users&q=' + encodeURIComponent(q), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(json => {
                    if (json.ok) {
                        let html = '';
                        (json.users || []).forEach(u => {
                            html += `<div class="search-result-item" onclick="startChat(${u.user_id}, '${u.first_name} ${u.last_name}')" style="display:flex; align-items:center; gap:10px;">
                                <img src="${getBase()}/controllers/avatar.php?uid=${u.user_id}&t=${Date.now()}" 
                                     onerror="this.src='${getBase()}/assets/images/default-avatar.png'"
                                     style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                <span>${u.first_name} ${u.last_name} (${u.role})</span>
                            </div>`;
                        });
                        document.getElementById('search-results').innerHTML = html || '<div style="padding:10px; font-size:12px; color:var(--text-secondary)">No users found</div>';
                    }
                });
        }, 300);
    }

    function startChat(id, name) {
        document.getElementById('search-container').style.display = 'none';
        loadChat(id, name);
    }

    function loadChat(id, name) {
        currentChatId = id;
        lastMessageId = 0;
        document.getElementById('receiver_id').value = id;
        document.getElementById('active-contact-name').innerText = name;
        document.getElementById('chat-header').style.display = 'flex';
        document.getElementById('chat-composer').style.display = 'flex';

        // Dynamic sidebar update: if contact isn't in sidebar, add it
        const contactsList = document.getElementById('contacts-list');
        let contactItem = Array.from(document.querySelectorAll('.contact-item')).find(item => {
            const onclickValue = item.getAttribute('onclick');
            return onclickValue && onclickValue.includes(`loadChat(${id},`);
        });

        if (!contactItem) {
            // Remove empty state if present
            const emptyState = contactsList.querySelector('.empty-state');
            if (emptyState) emptyState.remove();

            contactItem = document.createElement('div');
            contactItem.className = 'contact-item';
            contactItem.setAttribute('onclick', `loadChat(${id}, '${name.replace(/'/g, "\\'")}')`);

            const initials = name.split(' ').map(n => n[0]).join('').toUpperCase();

            contactItem.innerHTML = `
                <img class="contact-avatar-img" src="${getBase()}/controllers/avatar.php?uid=${id}&t=${Date.now()}" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                     alt="">
                <div class="contact-avatar" style="display:none;">
                    ${initials}
                </div>
                <div class="contact-info">
                    <div class="contact-name">${name}</div>
                    <div class="contact-last-msg"></div>
                </div>
            `;
            contactsList.prepend(contactItem);
        }

        document.querySelectorAll('.contact-item').forEach(item => {
            item.classList.remove('active');
        });
        contactItem.classList.add('active');

        fetch(getBase() + '/controllers/message-handler.php?action=fetch_history&with=' + id, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(json => {
                if (json.ok) {
                    chatHistory.innerHTML = '';
                    renderMessages(json.history);
                    scrollToBottom();
                }
            });
    }

    function renderMessages(messages) {
        if (!messages || messages.length === 0) return;

        messages.forEach(m => {
            // Only update lastMessageId for real server-side IDs (which are typically smaller than Date.now() values)
            // Or better: only if it's not a temporary message
            if (m.message_id && !m.is_temp && m.message_id > lastMessageId) lastMessageId = m.message_id;

            const isSent = parseInt(m.sender_id) === myUserId;
            const div = document.createElement('div');
            div.className = `msg-bubble ${isSent ? 'msg-sent' : 'msg-received'}`;

            const msgTime = new Date(m.created_at).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });

            div.innerHTML = `
                ${m.message_body.replace(/\n/g, '<br>')}
                <span class="msg-time">${msgTime}</span>
            `;
            chatHistory.appendChild(div);
        });

        if (isAtBottom) scrollToBottom();
    }

    function scrollToBottom() {
        chatHistory.scrollTop = chatHistory.scrollHeight;
    }

    document.getElementById('message-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const input = document.getElementById('message-input');
        const body = input.value.trim();
        if (!body || !currentChatId) return;

        // Immediately append the message locally
        const tempMsg = {
            message_id: Date.now(), // Temporary ID
            is_temp: true,
            sender_id: myUserId,
            message_body: body,
            created_at: new Date().toISOString()
        };
        renderMessages([tempMsg]);
        scrollToBottom();

        const fd = new FormData(this);
        input.value = '';
        fetch(getBase() + '/controllers/message-handler.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: fd
            })
            .then(res => res.json())
            .then(json => {
                if (json.ok && json.message_id) {
                    // Update lastMessageId to the real server ID
                    lastMessageId = Math.max(lastMessageId, json.message_id);
                }
            });
    });

    function checkNewMessages() {
        if (!currentChatId) return;
        fetch(`${getBase()}/controllers/message-handler.php?action=fetch_new&with=${currentChatId}&last_id=${lastMessageId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(json => {
                if (json.ok && json.messages.length > 0) {
                    renderMessages(json.messages);
                }
            });
    }

    setInterval(checkNewMessages, 3000);

    if (currentChatId) {
        const activeItem = document.querySelector(`.contact-item[onclick*="loadChat(${currentChatId},"]`);
        if (activeItem) {
            const name = activeItem.querySelector('.contact-name').innerText;
            loadChat(currentChatId, name);
        }
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, $u['role'], 'messages', [
    'title' => 'Messages',
    'content' => $content,
]);
