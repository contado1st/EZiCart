(() => {
    document.querySelectorAll('[data-dispatch-location]').forEach((form) => {
        const button = form.querySelector('[data-share-location]');
        const feedback = form.querySelector('[data-location-feedback]');
        button.addEventListener('click', () => {
            if (!navigator.geolocation) { feedback.textContent = 'Location access is unavailable. Use a supported browser over HTTPS or localhost.'; return; }
            button.disabled = true;
            feedback.textContent = 'Requesting a fresh device position…';
            navigator.geolocation.getCurrentPosition((position) => {
                if (!Number.isFinite(position.coords.latitude) || !Number.isFinite(position.coords.longitude) || !Number.isFinite(position.coords.accuracy) || position.coords.accuracy < 0 || position.coords.accuracy > 250 || !Number.isFinite(position.timestamp)) {
                    feedback.textContent = 'The location is not accurate enough (250 m maximum). Move to a clearer location and try again.';
                    button.disabled = false;
                    return;
                }
                form.elements.latitude.value = position.coords.latitude;
                form.elements.longitude.value = position.coords.longitude;
                form.elements.accuracy_meters.value = position.coords.accuracy;
                form.elements.captured_at.value = new Date(position.timestamp).toISOString();
                feedback.textContent = 'Saving this position…';
                form.requestSubmit();
            }, () => {
                feedback.textContent = 'Location was not shared. Allow location permission and retry, or ask the hub to assign work manually.';
                button.disabled = false;
            }, { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 });
        });
    });
})();
