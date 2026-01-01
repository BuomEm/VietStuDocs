// Notification system logic
const VAPID_PUBLIC_KEY = 'BBsE_KxcQN94F9I4WGHeH9SFTYJSCGpFcmmG3eGE1Zz8o0sP8xvnt6bnPdWCAcLyw90PeuwbW_4JslPIrEbletw';

function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => {
                console.log('SW Registered');
                subscribeUserToPush(reg);
            })
            .catch(err => console.error('SW Registration failed', err));
    }
}

function subscribeUserToPush(reg) {
    if ('PushManager' in window) {
        reg.pushManager.getSubscription()
            .then(sub => {
                if (sub === null) {
                    // Create new subscription
                    reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                    }).then(newSub => {
                        saveSubscription(newSub);
                    });
                }
            });
    }
}

function saveSubscription(sub) {
    fetch('/api/save_subscription.php', {
        method: 'POST',
        body: JSON.stringify(sub),
        headers: { 'Content-Type': 'application/json' }
    });
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Polling & Ping
function startNotificationLoop() {
    // Ping every 30s
    setInterval(() => {
        fetch('/api/ping.php');
    }, 30000);

    // Initial check
    checkUnread();

    // Poll every 5s
    setInterval(checkUnread, 5000);
}

function checkUnread() {
    fetch('/api/unread.php')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('notif-badge');
            const countEl = document.getElementById('notif-count');
            const list = document.getElementById('notif-list');

            if (data.count > 0) {
                badge.classList.remove('hidden');
                countEl.innerText = data.count;
                document.title = `(${data.count}) Tin mới | DocShare`;
            } else {
                badge.classList.add('hidden');
                document.title = 'DocShare';
            }

            if (data.notifications && data.notifications.length > 0) {
                list.innerHTML = data.notifications.map(n => `
                    <li class="${n.is_read == 0 ? 'bg-primary/5' : ''}">
                        <a href="javascript:void(0)" onclick="markRead(${n.id})" class="flex flex-col items-start p-3 hover:bg-base-200">
                            <span class="text-sm font-medium ${n.is_read == 0 ? 'text-primary' : ''}">${n.message}</span>
                            <span class="text-[10px] opacity-50">${n.time}</span>
                        </a>
                    </li>
                `).join('');
            } else {
                list.innerHTML = '<li class="p-4 text-center text-xs opacity-50">Không có thông báo mới</li>';
            }
        });
}

function markRead(id = null) {
    const url = id ? `/api/mark_read.php?id=${id}` : '/api/mark_read.php';
    fetch(url).then(r => r.json()).then(() => checkUnread());
}

// Initialize if logged in
if (document.body.dataset.loggedin === 'true') {
    registerServiceWorker();
    startNotificationLoop();
}
