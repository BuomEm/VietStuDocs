// Notification system logic
console.log('🔔 Notifications.js loaded v9');

// Initial states
let lastNotifCount = 0;
let favicon = null;
const notifSound = new Audio('/assets/sound/noti.mp3');

function playNotificationSound() {
    notifSound.play().catch(e => console.warn('Sound play blocked:', e));
}

function getVapidKey() {
    return document.body.dataset.vapidKey;
}

function urlBase64ToUint8Array(base64String) {
    if (!base64String) return new Uint8Array(0);
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = atob(base64);
    return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    try {
        const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        console.log('Service Worker Registered correctly at root');
        return reg;
    } catch (err) {
        console.error('Service Worker Registration failed:', err);
        return null;
    }
}

async function subscribePush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return false;
    }
    try {
        const reg = await registerServiceWorker();
        if (!reg) return false;
        await navigator.serviceWorker.ready;

        const oldSub = await reg.pushManager.getSubscription();
        if (oldSub) await oldSub.unsubscribe();

        const vapidKey = getVapidKey();
        if (!vapidKey) return false;

        const subscription = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey)
        });

        await fetch('/API/save_subscription.php', {
            method: 'POST',
            body: JSON.stringify(subscription),
            headers: { 'Content-Type': 'application/json' }
        });

        console.log('✅ Push Subscribed Successfully');
        await updateNotificationUI();
        return true;
    } catch (e) {
        console.error('❌ Subscription error:', e);
        await updateNotificationUI();
        return false;
    }
}

async function unsubscribePush() {
    try {
        const reg = await navigator.serviceWorker.ready;
        const subscription = await reg.pushManager.getSubscription();
        if (subscription) {
            await fetch('/API/unsubscribe.php', {
                method: 'POST',
                body: JSON.stringify({ endpoint: subscription.endpoint }),
                headers: { 'Content-Type': 'application/json' }
            });
            await subscription.unsubscribe();
        }
        console.log('Push Unsubscribed');
        await updateNotificationUI();
        return true;
    } catch (e) {
        await updateNotificationUI();
        return false;
    }
}

function checkNotificationStatus() {
    if (!('Notification' in window)) return 'unsupported';
    return Notification.permission;
}

async function updateNotificationUI() {
    const toggle = document.getElementById('btn-toggle-push');
    const statusEl = document.getElementById('push-status');
    if (!toggle) return;

    const status = checkNotificationStatus();
    if (status === 'granted') {
        let isSubscribed = false;
        try {
            if ('serviceWorker' in navigator) {
                const reg = await Promise.race([
                    navigator.serviceWorker.ready,
                    new Promise((_, reject) => setTimeout(() => reject(new Error('SW timeout')), 2000))
                ]);
                const subscription = await reg.pushManager.getSubscription();
                isSubscribed = !!subscription;
            }
        } catch (e) {
            console.warn('Push check failed/timed out', e);
        }

        if (isSubscribed) {
            toggle.checked = true;
            if (statusEl) statusEl.innerText = 'Trạng thái: Đã bật';
        } else {
            toggle.checked = false;
            if (statusEl) statusEl.innerText = 'Chưa kích hoạt đẩy';
        }
    } else {
        toggle.checked = false;
        if (statusEl) {
            if (status === 'denied') {
                statusEl.innerText = 'Trạng thái: Đã chặn';
            } else if (status === 'unsupported') {
                statusEl.innerText = 'Không hỗ trợ';
            } else {
                statusEl.innerText = 'Trạng thái: Chưa bật';
            }
        }
    }
}

function checkUnread() {
    fetch('/API/unread.php')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('notif-badge');
            const countEl = document.getElementById('notif-count');
            const list = document.getElementById('notif-list');
            const count = parseInt(data.count) || 0;

            if (badge) {
                if (count > 0) {
                    badge.classList.remove('hidden');
                    badge.innerText = count > 9 ? '9+' : count;
                } else {
                    badge.classList.add('hidden');
                }
            }
            if (countEl) countEl.innerText = count;
            if (favicon) favicon.badge(count);
            if (count > lastNotifCount) playNotificationSound();
            lastNotifCount = count;

            if (list && data.notifications) {
                list.innerHTML = data.notifications.length > 0
                    ? data.notifications.map(n => `
                            <li>
                                <a href="${n.url}" onclick="handleNotificationClick(event, ${n.id}, '${n.url}')" class="flex flex-col items-start p-3 hover:bg-base-200 transition-colors ${n.is_read == 0 ? 'bg-primary/5' : ''}">
                                    <span class="font-bold text-xs text-primary line-clamp-1">${n.title}</span>
                                    <span class="text-sm opacity-90 line-clamp-2">${n.message}</span>
                                    <span class="text-[9px] opacity-40 mt-1">${n.time}</span>
                                </a>
                            </li>`).join('')
                    : '<li class="p-4 text-center text-xs opacity-50">Không có thông báo mới</li>';
            }
        }).catch(err => console.warn('Fetch unread failed'));
}

function handleNotificationClick(event, id, url) {
    if (url === '#' || !url) return; // Allow default behavior for non-links
    
    // Attempt to mark read in background
    markRead(id);
    // Navigation will happen naturally via href
}

function markRead(id = null) {
    const url = id ? `/API/mark_read.php?id=${id}` : '/API/mark_read.php';
    // Use keepalive to ensure request completes even if page unloads
    fetch(url, { keepalive: true }).then(r => r.json()).then(() => checkUnread()).catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof Favico !== 'undefined') {
        favicon = new Favico({ animation: 'popFade', bgColor: '#dc2626', textColor: '#fff' });
    }

    if (document.body.dataset.loggedin === 'true') {
        registerServiceWorker().then(async (reg) => {
            if (reg && Notification.permission === 'granted') {
                try {
                    const sub = await reg.pushManager.getSubscription();
                    if (!sub) await subscribePush();
                } catch (e) { console.warn('Auto-subscribe check failed', e); }
            }
            await updateNotificationUI();
        }).catch(e => {
            console.error('SW Init failed', e);
            updateNotificationUI();
        });
        checkUnread();
        setInterval(checkUnread, 15000);
        setInterval(() => fetch('/API/ping.php').catch(() => {}), 60000);
    }
});
