self.addEventListener('push', function (event) {
    console.log('[Service Worker] Push Received.');
    let data = { title: 'Thông báo mới', body: 'Bạn có tin nhắn mới từ DocShare' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: '/assets/img/logo.png',
        badge: '/assets/img/badge.png',
        data: {
            url: data.url || '/dashboard.php'
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data.url)
    );
});
