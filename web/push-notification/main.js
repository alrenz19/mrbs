document.addEventListener('DOMContentLoaded', async () => {
    const subscribeBtn = document.getElementById('subscribe');

    if (!subscribeBtn) {
        console.warn("[Main] Subscribe button not found!");
        return; // Exit if button is missing
    }

    const email = subscribeBtn.getAttribute('data-email') || '';
    let subscribed = false;

    // --- Service Worker registration ---
    let swRegistration;
    if ('serviceWorker' in navigator) {
        try {
            swRegistration = await navigator.serviceWorker.register('/mrbs/web/sw.js');

            console.log("[Main] ServiceWorker registered:", swRegistration);
        } catch (err) {
            console.error("[Main] ServiceWorker registration failed:", err);
            return;
        }
    } else {
        console.error("[Main] ServiceWorker not supported in this browser.");
        return;
    }

    // --- Check existing subscription ---
    try {

        const existingSub = await swRegistration.pushManager.getSubscription();
        if (existingSub) {
            subscribed = true;
            subscribeBtn.classList.add('subscribed');
            subscribeBtn.querySelector('.toggle-text').textContent = "Unsubscribe";
        }
    } catch (err) {
        console.error("[Main] Error checking existing subscription:", err);
    }

    // --- Subscribe/Unsubscribe click handler ---
    subscribeBtn.addEventListener('click', async () => {
        console.log("[Main] Subscribe button clicked!");

        try {
            const registration = await navigator.serviceWorker.ready;
            console.log("[Main] Subscribe button clicked!2");

            if (!subscribed) {
                // Subscribe
                const publicKeyText = await fetch('push-notification/vapid/public_key.txt')
                    .then(res => res.text());
                const applicationServerKey = urlBase64ToUint8Array(publicKeyText);

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey
                });

                const payload = { subscription, email };
                const result = await fetch('push-notification/server/save_subscription.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(res => res.json());

                if (result.success) {
                    subscribed = true;
                    subscribeBtn.classList.add('subscribed');
                    subscribeBtn.querySelector('.toggle-text').textContent = "Unsubscribe";
                    console.log("[Main] Subscribed successfully");
                } else {
                    console.error("[Main] Subscription failed:", result.error);
                }

            } else {
                // Unsubscribe
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) await subscription.unsubscribe();

                await fetch('push-notification/server/remove_subscription.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });

                subscribed = false;
                subscribeBtn.classList.remove('subscribed');
                subscribeBtn.querySelector('.toggle-text').textContent = "Subscribe";
                console.log("[Main] Unsubscribed successfully");
            }

        } catch (err) {
            console.error("[Main] Subscription error:", err);
        }
    });

    // --- Helper function ---
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        return new Uint8Array([...rawData].map(c => c.charCodeAt(0)));
    }
});
