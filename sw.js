self.addEventListener('push', e => {
    const data = e.data ? e.data.json() : {};
    const title = data.title || 'Bạn có thông báo mới';
    const options = {
        body: data.body || 'Nhấn để xem chi tiết',
        icon: '/assets/img/logo.png', // Fallback icon path
        badge: '/assets/img/badge.png',
        data: {
            url: data.url || '/dashboard.php'
        }
    };

    e.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    e.waitUntil(
        clients.openWindow(e.notification.data.url)
    );
});
