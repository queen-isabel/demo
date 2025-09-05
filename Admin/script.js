document.addEventListener('DOMContentLoaded', () => {
    // Automatic logout after 30 mins of inactivity
    let timeout;
    function resetInactivityTimer() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            window.location.replace("index");
        }, 30 * 60 * 1000);
    }

    ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, resetInactivityTimer);
    });

    resetInactivityTimer();
});