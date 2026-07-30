/*
 * Photo input helper for the audit forms.
 *
 * - Compresses images in the browser before upload (downscale to a max edge +
 *   JPEG re-encode) so large phone photos don't trip server upload limits
 *   ("content too large" / HTTP 413). Serial numbers stay legible because the
 *   resolution stays high (default 2000 px long edge) at quality 0.7.
 * - Supports a separate "Take photo" input (capture=environment) that feeds the
 *   same list as the file picker.
 * - Renders removable, zoomable previews and keeps the real <input> in sync via
 *   a DataTransfer list, so what you see is exactly what gets submitted.
 */
(function (w) {
    function compress(file, maxEdge, quality, cb) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) { cb(file); return; }
        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            var W = img.naturalWidth, H = img.naturalHeight;
            if (!W || !H) { URL.revokeObjectURL(url); cb(file); return; }
            var scale = Math.min(1, maxEdge / Math.max(W, H));
            var cw = Math.round(W * scale), ch = Math.round(H * scale);
            var canvas = document.createElement('canvas');
            canvas.width = cw; canvas.height = ch;
            canvas.getContext('2d').drawImage(img, 0, 0, cw, ch);
            URL.revokeObjectURL(url);
            canvas.toBlob(function (blob) {
                // Keep whichever is smaller; never grow a file.
                if (blob && blob.size < file.size) {
                    var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    cb(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
                } else {
                    cb(file);
                }
            }, 'image/jpeg', quality);
        };
        img.onerror = function () { URL.revokeObjectURL(url); cb(file); };
        img.src = url;
    }

    function enhance(opts) {
        var input = opts.input, preview = opts.preview;
        var maxEdge = opts.maxEdge || 2000, quality = opts.quality || 0.7;
        if (!input || typeof DataTransfer === 'undefined') return;
        var dt = new DataTransfer();

        function sync() { input.files = dt.files; render(); }

        function addFiles(list) {
            var arr = Array.prototype.slice.call(list || []);
            if (!arr.length) return;
            var pending = arr.length;
            arr.forEach(function (f) {
                compress(f, maxEdge, quality, function (out) {
                    dt.items.add(out);
                    if (--pending === 0) sync();
                });
            });
        }

        function removeAt(i) {
            var next = new DataTransfer();
            for (var j = 0; j < dt.files.length; j++) if (j !== i) next.items.add(dt.files[j]);
            dt = next; sync();
        }

        function render() {
            if (!preview) return;
            preview.innerHTML = '';
            for (var i = 0; i < dt.files.length; i++) {
                (function (idx) {
                    var url = URL.createObjectURL(dt.files[idx]);
                    var cell = document.createElement('label'); cell.className = 'edit-photo';
                    var zoom = document.createElement('span'); zoom.className = 'zoom-wrap';
                    var im = document.createElement('img'); im.src = url;
                    im.onload = function () { URL.revokeObjectURL(url); };
                    zoom.appendChild(im);
                    var rm = document.createElement('span'); rm.className = 'edit-photo-remove';
                    rm.textContent = '✕ Remove';
                    rm.addEventListener('click', function (e) { e.preventDefault(); removeAt(idx); });
                    cell.appendChild(zoom); cell.appendChild(rm);
                    preview.appendChild(cell);
                })(i);
            }
        }

        input.addEventListener('change', function () {
            // On a native pick, input.files holds only the newly chosen files.
            addFiles(input.files);
        });

        if (opts.captureInput) {
            opts.captureInput.addEventListener('change', function () {
                addFiles(opts.captureInput.files);
                opts.captureInput.value = ''; // allow taking another photo
            });
        }
    }

    w.PhotoInput = { enhance: enhance, compress: compress };
})(window);
