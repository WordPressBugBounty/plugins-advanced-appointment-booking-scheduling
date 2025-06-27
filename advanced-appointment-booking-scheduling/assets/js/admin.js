document.addEventListener('DOMContentLoaded', function () {
    const url = new URL(window.location.href);

    // Remove edit_staff
    if (url.searchParams.has('edit_staff')) {
        url.searchParams.delete('edit_staff');
    }

    if (url.searchParams.get('tab') === 'staff') {
        url.searchParams.delete('tab');
    }

    if (url.searchParams.get('tab') === 'services') {
        url.searchParams.delete('tab');
    }

    // Update URL
    window.history.replaceState({}, document.title, url.toString());
});

