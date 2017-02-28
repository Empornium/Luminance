window.onload = function () {
    document.getElementById('cinfo').value = [
        window.screen.width,
        window.screen.height,
        window.screen.colorDepth,
        new Date().getTimezoneOffset()
    ].join("|");
}
