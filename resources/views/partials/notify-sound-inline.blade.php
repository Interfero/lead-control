<script>
(function () {
    var SOUND_URLS = ['/crm/sounds/order-notify.mp3', '/sounds/order-notify.mp3'];
    var chime = null;
    var chimeUrl = '';
    var audioCtx = null;

    function pickUrl(custom) {
        return custom || SOUND_URLS[0];
    }

    function ensureChime(url) {
        url = pickUrl(url);
        if (chime && chimeUrl === url) {
            return chime;
        }
        chimeUrl = url;
        chime = new Audio(url);
        chime.preload = 'auto';
        return chime;
    }

    function beep() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) {
                return false;
            }
            if (!audioCtx) {
                audioCtx = new Ctx();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.35, audioCtx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.45);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.46);
            return true;
        } catch (e) {
            return false;
        }
    }

    window.lcPlayNotifySound = function (customUrl) {
        var audio = ensureChime(customUrl);
        try {
            audio.pause();
            audio.currentTime = 0;
        } catch (e) {}
        return audio.play().then(function () {
            return 'mp3';
        }).catch(function () {
            return beep() ? 'beep' : false;
        });
    };

    window.lcEnableSystemNotify = function () {
        if (window.Notification && Notification.requestPermission) {
            return Notification.requestPermission();
        }
        return Promise.resolve('unsupported');
    };
})();
</script>
