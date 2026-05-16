/**
 * doom.js — Self-contained browser wrapper for doom.wasm (wasm-fizzbuzz/wasm-doom)
 * The WAD data is embedded inside doom.wasm — no separate WAD file needed.
 * Usage: DoomGame.start(canvasElement, wasmPath)
 */
(function(global) {
    function mapDoomKey(keyCode) {
        switch (keyCode) {
            case 8:   return 127; // Backspace
            case 17:  return 157; // Ctrl
            case 18:  return 184; // Alt
            case 37:  return 172; // Left
            case 38:  return 173; // Up
            case 39:  return 174; // Right
            case 40:  return 175; // Down
            default:
                if (keyCode >= 65 && keyCode <= 90) return keyCode + 32; // A-Z -> a-z
                if (keyCode >= 112 && keyCode <= 123) return keyCode + 75; // F1-F12
                return keyCode;
        }
    }

    var DoomGame = {
        _started: false,
        _instance: null,
        _keydownHandler: null,
        _keyupHandler: null,

        start: function(canvas, wasmPath) {
            if (this._started) return;
            this._started = true;

            var W = 640, H = 400;
            canvas.width  = W;
            canvas.height = H;
            canvas.style.imageRendering = 'pixelated';

            var ctx = canvas.getContext('2d');
            var memory = new WebAssembly.Memory({ initial: 108 });
            var self = this;

            // Show loading overlay
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, W, H);
            ctx.fillStyle = '#c0392b';
            ctx.font = 'bold 28px monospace';
            ctx.textAlign = 'center';
            ctx.fillText('DOOM', W/2, H/2 - 20);
            ctx.fillStyle = '#888';
            ctx.font = '14px monospace';
            ctx.fillText('Loading...', W/2, H/2 + 10);

            var imports = {
                js: {
                    js_console_log: function() {},
                    js_stdout:       function() {},
                    js_stderr:       function() {},
                    js_draw_screen: function(ptr) {
                        var pixels = new Uint8ClampedArray(memory.buffer, ptr, W * H * 4);
                        var img = new ImageData(new Uint8ClampedArray(pixels), W, H);
                        ctx.putImageData(img, 0, 0);
                    },
                    js_milliseconds_since_start: function() {
                        return performance.now();
                    }
                },
                env: { memory: memory }
            };

            WebAssembly.instantiateStreaming(fetch(wasmPath), imports)
                .then(function(result) {
                    var inst = result.instance;
                    self._instance = inst;

                    // Bind keyboard
                    self._keydownHandler = function(e) {
                        inst.exports.add_browser_event(0, mapDoomKey(e.keyCode));
                        // Don't prevent default for F-keys that browser needs
                        if ([37,38,39,40,32,17,18].indexOf(e.keyCode) !== -1) {
                            e.preventDefault();
                        }
                    };
                    self._keyupHandler = function(e) {
                        inst.exports.add_browser_event(1, mapDoomKey(e.keyCode));
                    };

                    // Focus the canvas to capture keys; also listen on document
                    canvas.setAttribute('tabindex', '0');
                    canvas.focus();
                    canvas.addEventListener('click', function() { canvas.focus(); });
                    canvas.addEventListener('keydown', self._keydownHandler);
                    canvas.addEventListener('keyup',   self._keyupHandler);
                    document.addEventListener('keydown', self._keydownHandler);
                    document.addEventListener('keyup',   self._keyupHandler);

                    // Start game
                    inst.exports.main();

                    // Run loop
                    (function loop() {
                        inst.exports.doom_loop_step();
                        requestAnimationFrame(loop);
                    })();
                })
                .catch(function(err) {
                    ctx.fillStyle = '#000';
                    ctx.fillRect(0, 0, W, H);
                    ctx.fillStyle = '#ff4444';
                    ctx.font = '13px monospace';
                    ctx.textAlign = 'center';
                    ctx.fillText('Failed to load DOOM: ' + err.message, W/2, H/2);
                });
        },

        stop: function() {
            if (this._keydownHandler) {
                document.removeEventListener('keydown', this._keydownHandler);
                document.removeEventListener('keyup',   this._keyupHandler);
            }
            this._started  = false;
            this._instance = null;
        }
    };

    global.DoomGame = DoomGame;
})(window);
