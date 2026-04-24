  </main><!-- /content -->
  <!-- Footer credit -->
  <footer style="text-align:center;padding:16px 20px;background:linear-gradient(135deg,#0f172a,#1e293b);margin-top:auto">
    <span style="font-size:.78rem;font-weight:600;background:linear-gradient(90deg,#60a5fa,#a78bfa,#f472b6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">
      ⚡ Developed by EthioOps Software QA &amp; DevOps Consulting Services
    </span>
    <span style="color:#475569;font-size:.72rem;margin-left:8px">&copy; 2026</span>
  </footer>
</div><!-- /main-wrapper -->

<!-- ═══════════════════════════════════════════════════════
     FLOATING CHAT WIDGET
     ═══════════════════════════════════════════════════════ -->
<div id="chatWidget">
  <!-- Toggle button -->
  <button id="chatToggleBtn" onclick="toggleChat()" title="Messages">
    <i class="fas fa-comments"></i>
    <span id="chatUnreadBadge" style="display:none"></span>
  </button>

  <!-- Chat panel -->
  <div id="chatPanel" style="display:none">
    <div id="chatHeader">
      <div id="chatHeaderInfo">
        <div id="chatHeaderAvatar"></div>
        <div>
          <div id="chatHeaderName">Messages</div>
          <div id="chatHeaderSub" style="font-size:.72rem;opacity:.7">Select a contact</div>
        </div>
      </div>
      <div style="display:flex;gap:6px">
        <button onclick="showContactList()" id="backBtn" style="display:none;background:none;border:none;color:#fff;cursor:pointer;font-size:1rem" title="Back"><i class="fas fa-arrow-left"></i></button>
        <button onclick="toggleChat()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:1rem"><i class="fas fa-times"></i></button>
      </div>
    </div>

    <!-- Contact list -->
    <div id="contactList">
      <!-- Tabs: Recent / All -->
      <div style="display:flex;border-bottom:1px solid #f0f0f0;background:#fafafa">
        <button onclick="switchTab('recent')" id="tabRecent" style="flex:1;padding:9px;border:none;background:none;font-size:.78rem;font-weight:700;color:#4361ee;border-bottom:2px solid #4361ee;cursor:pointer">Recent</button>
        <button onclick="switchTab('all')" id="tabAll" style="flex:1;padding:9px;border:none;background:none;font-size:.78rem;font-weight:600;color:#aaa;border-bottom:2px solid transparent;cursor:pointer">All Contacts</button>
      </div>
      <!-- Search -->
      <div style="padding:8px 12px;border-bottom:1px solid #f0f0f0">
        <input id="contactSearch" type="text" placeholder="🔍 Search contacts..." oninput="filterContacts(this.value)"
          style="width:100%;border:1.5px solid #e0e0e0;border-radius:20px;padding:6px 12px;font-size:.82rem;outline:none;box-sizing:border-box">
      </div>
      <div id="contactItems"></div>
    </div>

    <!-- Message area -->
    <div id="messageArea" style="display:none;flex-direction:column;height:100%">
      <div id="messageList"></div>
      <div id="messageInput">
        <input type="text" id="msgInput" placeholder="Type a message..." autocomplete="off">
        <button onclick="sendMessage()" id="sendBtn"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>
</div>

<style>
#chatWidget { position:fixed; bottom:24px; right:24px; z-index:9999; font-family:'Segoe UI',sans-serif; }

#chatToggleBtn {
  width:56px; height:56px; border-radius:50%; border:none; cursor:pointer;
  background:linear-gradient(135deg,#4361ee,#7209b7);
  color:#fff; font-size:1.4rem; box-shadow:0 4px 20px rgba(67,97,238,.5);
  display:flex; align-items:center; justify-content:center;
  transition:transform .2s,box-shadow .2s; position:relative;
}
#chatToggleBtn:hover { transform:scale(1.1); box-shadow:0 6px 28px rgba(67,97,238,.6); }
#chatUnreadBadge {
  position:absolute; top:-4px; right:-4px; background:#e63946; color:#fff;
  border-radius:50%; width:20px; height:20px; font-size:.68rem; font-weight:700;
  display:flex; align-items:center; justify-content:center; border:2px solid #fff;
}

#chatPanel {
  position:absolute; bottom:68px; right:0;
  width:340px; height:520px; background:#fff; border-radius:16px;
  box-shadow:0 12px 48px rgba(0,0,0,.2); overflow:hidden;
  display:flex; flex-direction:column;
}

#chatHeader {
  background:linear-gradient(135deg,#4361ee,#7209b7);
  padding:14px 16px; display:flex; align-items:center; justify-content:space-between;
  color:#fff; flex-shrink:0;
}
#chatHeaderInfo { display:flex; align-items:center; gap:10px; }
#chatHeaderAvatar {
  width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,.25);
  display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem;
}
#chatHeaderName { font-weight:700; font-size:.95rem; }

#contactList { flex:1; overflow-y:auto; }
.contact-item {
  display:flex; align-items:center; gap:12px; padding:12px 16px;
  cursor:pointer; border-bottom:1px solid #f5f5f5; transition:background .15s;
}
.contact-item:hover { background:#f5f7ff; }
.contact-avatar {
  width:40px; height:40px; border-radius:50%; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-weight:700; font-size:.85rem; color:#fff;
}
.contact-name { font-weight:600; font-size:.88rem; color:#1a1a2e; }
.contact-role { font-size:.72rem; color:#aaa; text-transform:capitalize; }
.contact-unread { background:#e63946; color:#fff; border-radius:10px; padding:1px 7px; font-size:.68rem; font-weight:700; margin-left:auto; }

#messageArea { display:flex; flex-direction:column; flex:1; overflow:hidden; }
#messageList { flex:1; overflow-y:auto; padding:12px; display:flex; flex-direction:column; gap:8px; }
.msg-bubble { max-width:80%; padding:9px 13px; border-radius:14px; font-size:.85rem; line-height:1.4; word-break:break-word; }
.msg-mine { background:linear-gradient(135deg,#4361ee,#7209b7); color:#fff; align-self:flex-end; border-bottom-right-radius:4px; }
.msg-other { background:#f0f2f8; color:#333; align-self:flex-start; border-bottom-left-radius:4px; }
.msg-sender { font-size:.7rem; color:#aaa; margin-bottom:2px; }
.msg-time { font-size:.65rem; opacity:.6; margin-top:3px; text-align:right; }

#messageInput {
  display:flex; gap:8px; padding:10px 12px;
  border-top:1px solid #f0f0f0; background:#fff; flex-shrink:0;
}
#msgInput {
  flex:1; border:1.5px solid #e0e0e0; border-radius:20px;
  padding:8px 14px; font-size:.88rem; outline:none;
  transition:border-color .2s;
}
#msgInput:focus { border-color:#4361ee; }
#sendBtn {
  width:36px; height:36px; border-radius:50%; border:none; cursor:pointer;
  background:linear-gradient(135deg,#4361ee,#7209b7); color:#fff; font-size:.9rem;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
  transition:transform .15s;
}
#sendBtn:hover { transform:scale(1.1); }

.role-admin    { background:linear-gradient(135deg,#e63946,#c1121f); }
.role-teacher  { background:linear-gradient(135deg,#7209b7,#560bad); }
.role-student  { background:linear-gradient(135deg,#4361ee,#3a0ca3); }
.role-group    { background:linear-gradient(135deg,#2dc653,#1a7a32); }
.role-class    { background:linear-gradient(135deg,#f4a261,#e76f51); }
</style>

<script>
const CHAT_BASE = '<?= BASE_URL ?>/api/chat.php';
const CHAT_UID  = <?= $_SESSION['user']['id'] ?? 0 ?>;
const CHAT_NAME = '<?= addslashes($_SESSION['user']['name'] ?? '') ?>';
let currentContact = null;
let pollInterval = null;
let lastMsgId = 0;
let allContacts = [];
let currentTab = 'recent';

function toggleChat() {
  const panel = document.getElementById('chatPanel');
  const open = panel.style.display !== 'none';
  panel.style.display = open ? 'none' : 'flex';
  panel.style.flexDirection = 'column';
  if (!open) { loadContacts(); pollUnread(); }
}

function switchTab(tab) {
  currentTab = tab;
  document.getElementById('tabRecent').style.color = tab==='recent' ? '#4361ee' : '#aaa';
  document.getElementById('tabRecent').style.borderBottomColor = tab==='recent' ? '#4361ee' : 'transparent';
  document.getElementById('tabAll').style.color = tab==='all' ? '#4361ee' : '#aaa';
  document.getElementById('tabAll').style.borderBottomColor = tab==='all' ? '#4361ee' : 'transparent';
  document.getElementById('contactSearch').value = '';
  renderContacts(allContacts);
}

function filterContacts(q) {
  const filtered = q ? allContacts.filter(c => c.name.toLowerCase().includes(q.toLowerCase())) : allContacts;
  renderContacts(filtered, q ? 'all' : currentTab);
}

function renderContacts(contacts, tab) {
  tab = tab || currentTab;
  const el = document.getElementById('contactItems');
  el.innerHTML = '';

  let list = contacts;
  if (tab === 'recent') {
    // Show only contacts we've sent messages to (stored in localStorage)
    const sent = JSON.parse(localStorage.getItem('chat_sent_to') || '[]');
    list = contacts.filter(c => sent.includes(String(c.id)));
    if (list.length === 0) {
      el.innerHTML = '<div style="padding:24px;text-align:center;color:#aaa;font-size:.82rem"><i class="fas fa-clock" style="display:block;font-size:1.5rem;margin-bottom:8px;opacity:.3"></i>No recent chats.<br><small>Switch to All Contacts to start.</small></div>';
      return;
    }
  }

  if (!list || list.length === 0) {
    el.innerHTML = '<div style="padding:24px;text-align:center;color:#aaa;font-size:.82rem">No contacts found</div>';
    return;
  }

  const roleOrder = {admin:0, teacher:1, librarian:2, group:3, class:4, student:5};
  list.sort((a,b) => (roleOrder[a.role]||9) - (roleOrder[b.role]||9));
  list.forEach(c => {
    const div = document.createElement('div');
    div.className = 'contact-item';
    div.onclick = () => openChat(c);
    div.innerHTML = `
      <div class="contact-avatar role-${c.role}">${c.avatar}</div>
      <div style="flex:1;min-width:0">
        <div class="contact-name">${escHtml(c.name)}</div>
        <div class="contact-role">${c.role === 'class' ? '📚 Class Group' : c.role === 'group' ? '👥 Group' : c.role}</div>
      </div>`;
    el.appendChild(div);
  });
}

function loadContacts() {
  const el = document.getElementById('contactItems');
  el.innerHTML = '<div style="padding:20px;text-align:center;color:#aaa;font-size:.82rem"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  fetch(CHAT_BASE + '?action=contacts')
    .then(r => r.text())
    .then(text => {
      const contacts = JSON.parse(text);
      allContacts = contacts;
      renderContacts(contacts);
    })
    .catch(err => {
      console.error('Chat contacts error:', err);
      el.innerHTML = '<div style="padding:20px;text-align:center;color:#e63946;font-size:.82rem"><i class="fas fa-exclamation-circle"></i> Failed to load contacts</div>';
    });
}

function openChat(contact) {
  currentContact = contact;
  document.getElementById('contactList').style.display = 'none';
  document.getElementById('messageArea').style.display = 'flex';
  document.getElementById('backBtn').style.display = 'block';
  document.getElementById('chatHeaderName').textContent = contact.name;
  document.getElementById('chatHeaderSub').textContent = contact.role === 'class' ? 'Class Group' : contact.role === 'group' ? 'Group Channel' : contact.role;
  document.getElementById('chatHeaderAvatar').textContent = contact.avatar;
  loadMessages();
  if (pollInterval) clearInterval(pollInterval);
  pollInterval = setInterval(loadMessages, 4000);
  document.getElementById('msgInput').focus();
}

function showContactList() {
  currentContact = null;
  document.getElementById('contactList').style.display = 'block';
  document.getElementById('messageArea').style.display = 'none';
  document.getElementById('backBtn').style.display = 'none';
  document.getElementById('chatHeaderName').textContent = 'Messages';
  document.getElementById('chatHeaderSub').textContent = 'Select a contact';
  document.getElementById('chatHeaderAvatar').textContent = '💬';
  if (pollInterval) clearInterval(pollInterval);
}

function loadMessages() {
  if (!currentContact) return;
  fetch(CHAT_BASE + '?action=get_messages&with=' + encodeURIComponent(currentContact.id))
    .then(r => r.json())
    .then(msgs => {
      const list = document.getElementById('messageList');
      const wasAtBottom = list.scrollHeight - list.scrollTop <= list.clientHeight + 40;
      list.innerHTML = '';
      if (!msgs.length) {
        list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;font-size:.85rem"><i class="fas fa-comments" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i>No messages yet. Say hello!</div>';
        return;
      }
      msgs.forEach(m => {
        const wrap = document.createElement('div');
        wrap.style.display = 'flex';
        wrap.style.flexDirection = 'column';
        wrap.style.alignItems = m.is_mine ? 'flex-end' : 'flex-start';
        if (!m.is_mine) wrap.innerHTML += `<div class="msg-sender">${escHtml(m.sender_name)}</div>`;
        wrap.innerHTML += `<div class="msg-bubble ${m.is_mine?'msg-mine':'msg-other'}">${escHtml(m.body)}<div class="msg-time">${formatTime(m.created_at)}</div></div>`;
        list.appendChild(wrap);
      });
      if (wasAtBottom) list.scrollTop = list.scrollHeight;
    });
}

function sendMessage() {
  const input = document.getElementById('msgInput');
  const body = input.value.trim();
  if (!body || !currentContact) return;
  input.value = '';
  // Track sent contacts for "Recent" tab
  const sent = JSON.parse(localStorage.getItem('chat_sent_to') || '[]');
  if (!sent.includes(String(currentContact.id))) {
    sent.unshift(String(currentContact.id));
    localStorage.setItem('chat_sent_to', JSON.stringify(sent.slice(0, 20)));
  }
  const fd = new FormData();
  fd.append('action','send'); fd.append('body',body); fd.append('to',currentContact.id);
  fetch(CHAT_BASE, {method:'POST',body:fd})
    .then(r => r.json())
    .then(() => loadMessages());
}

function pollUnread() {
  fetch(CHAT_BASE + '?action=unread')
    .then(r => r.json())
    .then(d => {
      const badge = document.getElementById('chatUnreadBadge');
      if (d.count > 0) { badge.textContent = d.count; badge.style.display='flex'; }
      else badge.style.display='none';
    });
  setTimeout(pollUnread, 15000);
}

document.getElementById('msgInput')?.addEventListener('keydown', e => { if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });

function escHtml(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
function formatTime(ts) {
  const d = new Date(ts.replace(' ','T'));
  return d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
}

// Poll unread on load
pollUnread();
</script>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
