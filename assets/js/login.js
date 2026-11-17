(function () {
    'use strict';

    function initSceneParallax() {
        var scene = document.getElementById('scene');

        if (!scene) {
            return;
        }

        var layers = Array.prototype.slice.call(scene.querySelectorAll('.scene-layer'));
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var finePointer = window.matchMedia('(pointer: fine)').matches;

        if (!layers.length || reduceMotion || !finePointer) {
            return;
        }

        var frame = null;
        var targetX = 0;
        var targetY = 0;

        function apply() {
            frame = null;

            layers.forEach(function (layer) {
                var depth = parseFloat(layer.getAttribute('data-depth')) || 0;
                layer.style.transform =
                    'translate3d(' + (-targetX * depth).toFixed(1) + 'px, ' +
                    (-targetY * depth).toFixed(1) + 'px, 0)';
            });
        }

        function schedule() {
            if (!frame) {
                frame = window.requestAnimationFrame(apply);
            }
        }

        scene.addEventListener('pointermove', function (event) {
            var rect = scene.getBoundingClientRect();

            targetX = (event.clientX - rect.left) / rect.width - 0.5;
            targetY = (event.clientY - rect.top) / rect.height - 0.5;
            schedule();
        });

        scene.addEventListener('pointerleave', function () {
            targetX = 0;
            targetY = 0;
            schedule();
        });
    }

    function initGoogleButton() {
        var button = document.getElementById('googleLogin');
        var note = document.getElementById('googleNote');

        if (!button || !note) {
            return;
        }

        var timer = null;

        button.addEventListener('click', function () {
            note.classList.add('is-visible');
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                note.classList.remove('is-visible');
            }, 4500);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSceneParallax();
        initGoogleButton();
    });
})();

function togglePassword(inputId, button) {
    var input = document.getElementById(inputId);

    if (!input || !button) {
        return;
    }

    var show = input.type === 'password';

    input.type = show ? 'text' : 'password';

    if (button.querySelector('.icon-eye, .icon-eye-off')) {
        button.classList.toggle('is-visible', show);
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
        button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
    } else {
        button.textContent = show ? '🙈' : '👁';
    }

    input.focus({ preventScroll: true });
}
