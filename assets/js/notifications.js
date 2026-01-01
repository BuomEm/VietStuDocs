// Notification system logic
console.log('🔔 Notifications.js loaded v2');
const VAPID_PUBLIC_KEY = document.body.dataset.vapidKey;

function urlBase64ToUint8Array(base64String) {
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
        // Luôn đăng ký sw.js ở root để có scope toàn trang
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
        console.warn('Push messaging is not supported');
        return false;
    }

    try {
        const reg = await registerServiceWorker();
        if (!reg) return false;

        // Đảm bảo SW đã sẵn sàng
        await navigator.serviceWorker.ready;

        // Xóa đăng ký cũ nếu có để tránh lỗi push service error
        const oldSub = await reg.pushManager.getSubscription();
        if (oldSub) {
            await oldSub.unsubscribe();
        }

        const subscription = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        });

        await fetch('/API/save_subscription.php', {
            method: 'POST',
            body: JSON.stringify(subscription),
            headers: { 'Content-Type': 'application/json' }
        });

        console.log('✅ Push Subscribed Successfully');
        updateNotificationUI();
        return true;
    } catch (e) {
        if (e.name === 'AbortError') {
            console.error('❌ Brave/Chrome Push Service Error. Hãy kiểm tra brave://settings/privacy -> "Use Google services for push messaging"');
        } else {
            console.error('❌ Subscription error:', e);
        }
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
        updateNotificationUI();
        return true;
    } catch (e) {
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
        const reg = await navigator.serviceWorker.ready;
        const subscription = await reg.pushManager.getSubscription();
        if (subscription) {
            toggle.checked = true;
            if (statusEl) statusEl.innerText = 'Trạng thái: Đã bật';
        } else {
            toggle.checked = false;
            if (statusEl) statusEl.innerText = 'Đã cấp quyền nhưng chưa kích hoạt';
        }
    } else {
        toggle.checked = false;
        if (statusEl) statusEl.innerText = status === 'denied' ? 'Đã chặn (vào cài đặt trình duyệt để mở)' : 'Trạng thái: Chưa bật';
    }
}

function checkUnread() {
    fetch('/API/unread.php')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('notif-badge');
            const countEl = document.getElementById('notif-count');
            const list = document.getElementById('notif-list');
            if (badge && countEl) {
                if (data.count > 0) {
                    badge.classList.remove('hidden');
                    countEl.innerText = data.count;
                } else {
                    badge.classList.add('hidden');
                }
            }
            if (list && data.notifications) {
                list.innerHTML = data.notifications.length > 0
                    ? data.notifications.map(n => `<li class="${n.is_read == 0 ? 'bg-primary/5' : ''}"><a href="javascript:void(0)" onclick="markRead(${n.id})" class="flex flex-col items-start p-3"><span>${n.message}</span></a></li>`).join('')
                    : '<li class="p-4 text-center text-xs opacity-50">Không có thông báo mới</li>';
            }
        });
}

function markRead(id = null) {
    const url = id ? `/API/mark_read.php?id=${id}` : '/API/mark_read.php';
    fetch(url).then(r => r.json()).then(() => checkUnread());
}

if (document.body.dataset.loggedin === 'true') {
    registerServiceWorker().then(() => {
        if (Notification.permission === 'granted') subscribePush();
        updateNotificationUI();
    });
    checkUnread();
    setInterval(checkUnread, 30000);
    setInterval(() => fetch('/API/ping.php'), 60000);
}
