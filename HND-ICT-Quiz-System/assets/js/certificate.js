/* Scale the fixed-size A4 certificate (1123 x 794 px) to fit any screen. Printing ignores the scaling. */
(() => {
    const W = 1123, H = 794;
    const stage = document.getElementById('stage');
    const cert = document.getElementById('cert');
    if (!stage || !cert) return;

    function fit() {
        const avail = stage.clientWidth;
        const s = Math.min(1, avail / W);
        cert.style.transform = 'scale(' + s + ')';
        stage.style.height = (H * s) + 'px';
    }
    window.addEventListener('resize', fit);
    window.addEventListener('beforeprint', () => { cert.style.transform = 'none'; stage.style.height = H + 'px'; });
    window.addEventListener('afterprint', fit);
    fit();
})();
