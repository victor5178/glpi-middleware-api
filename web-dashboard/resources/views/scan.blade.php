@extends('layouts.app')

@section('title', 'Scan asset QR')

@section('content')

    <div class="page-head">
        <h1 class="page-title">Scan asset QR</h1>
    </div>

    <p style="color:var(--muted);margin:0 0 16px;">
        Point your phone camera at the asset's QR code. When it's recognised the
        serial number is read automatically and the manual entry form opens
        pre-filled — same as scanning on the Android app.
    </p>

    <div id="scan-unsupported" class="alert alert-warn" style="display:none;"></div>

    <div class="card">
        <div class="card-body">
            <div class="scanner">
                <video id="scan-video" playsinline muted></video>
                <div class="scanner-frame"></div>
            </div>

            <div id="scan-status" class="scan-status">Starting camera…</div>

            <div class="scan-actions">
                <button type="button" id="scan-start" class="btn btn-primary" style="display:none;">Start camera</button>
                <a href="{{ route('manual.create') }}" class="btn btn-ghost">Enter manually instead</a>
            </div>

            {{-- Manual fallback: type/paste the code if the camera can't be used. --}}
            <form method="get" action="{{ route('manual.create') }}" class="filter" style="gap:10px;margin-top:18px;">
                <div class="field" style="flex:1;">
                    <label for="q">Or type the serial / asset id</label>
                    <input class="input" id="q" name="q" placeholder="e.g. SN-ABC-999" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-ghost">Look up</button>
            </form>
        </div>
    </div>

    <style>
        .scanner {
            position: relative; width: 100%; max-width: 460px; margin: 0 auto;
            aspect-ratio: 3 / 4; background: #0e1116; border-radius: var(--radius);
            overflow: hidden;
        }
        .scanner video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .scanner-frame {
            position: absolute; inset: 16%; border: 3px solid rgba(255,255,255,.85);
            border-radius: 14px; box-shadow: 0 0 0 100vmax rgba(0,0,0,.25);
        }
        .scan-status { text-align: center; margin: 14px 0 4px; color: var(--muted); font-size: .9rem; }
        .scan-status.ok { color: var(--success); font-weight: 700; }
        .scan-actions { display: flex; gap: 10px; justify-content: center; margin-top: 10px; flex-wrap: wrap; }
    </style>

    <script>
    (function () {
        var ITEM_TYPES = ['Computer', 'Monitor', 'Peripheral', 'Printer'];
        // Value after a serial/id label: SN, S/N, Serial, Serial No/Number, ID.
        var LABELED = /(?:S\/?N|serial(?:\s*(?:no|number))?|id)\s*[:=]\s*([A-Za-z0-9._/\-]+)/i;

        // Mirror of the Android QrParser: pull a serial/id from the payload.
        function parseIdentifier(text) {
            if (!text) return null;
            text = text.trim();
            var m = LABELED.exec(text);
            if (m && m[1]) return m[1];
            // Bare single token (no whitespace) → treat the whole thing as serial.
            if (!/\s/.test(text)) return text;
            return null;
        }

        var manualUrl = @json(route('manual.create'));
        var statusEl = document.getElementById('scan-status');
        var unsupportedEl = document.getElementById('scan-unsupported');
        var startBtn = document.getElementById('scan-start');
        var video = document.getElementById('scan-video');
        var stream = null, detector = null, scanning = false, done = false;

        function fail(msg) {
            unsupportedEl.style.display = 'block';
            unsupportedEl.textContent = msg;
            statusEl.textContent = 'Camera scanning unavailable — use the box below.';
        }

        function goTo(identifier) {
            if (done) return;
            done = true;
            statusEl.textContent = 'Found: ' + identifier + ' — opening…';
            statusEl.classList.add('ok');
            stop();
            var url = manualUrl + (manualUrl.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(identifier);
            window.location.href = url;
        }

        function stop() {
            scanning = false;
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        }

        function loop() {
            if (!scanning || done) return;
            detector.detect(video).then(function (codes) {
                for (var i = 0; i < codes.length; i++) {
                    var id = parseIdentifier(codes[i].rawValue);
                    if (id) { goTo(id); return; }
                }
                requestAnimationFrame(loop);
            }).catch(function () {
                requestAnimationFrame(loop);
            });
        }

        async function start() {
            startBtn.style.display = 'none';
            statusEl.textContent = 'Starting camera…';
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } }, audio: false
                });
                video.srcObject = stream;
                await video.play();
                scanning = true;
                statusEl.textContent = 'Scanning… center the QR code in the frame.';
                loop();
            } catch (e) {
                fail('Could not access the camera: ' + (e && e.message ? e.message : e) +
                     '. Grant camera permission and reload, or type the code below.');
                startBtn.style.display = 'inline-flex';
                startBtn.textContent = 'Try camera again';
            }
        }

        // Preconditions: secure context (HTTPS or localhost), camera API, and the
        // native BarcodeDetector (Chrome/Edge on Android). Otherwise, manual box.
        if (!window.isSecureContext) {
            fail('The camera only works over HTTPS (or on localhost). Open this page via https:// to scan, or type the code below.');
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            fail('This browser has no camera access. Type the code below instead.');
            return;
        }
        if (!('BarcodeDetector' in window)) {
            fail('This browser cannot decode QR codes (needs Chrome/Edge on Android). Type the code below, or use the Android app.');
            return;
        }

        BarcodeDetector.getSupportedFormats().then(function (formats) {
            var fmt = formats.indexOf('qr_code') !== -1 ? ['qr_code'] : formats;
            detector = new BarcodeDetector({ formats: fmt });
            startBtn.style.display = 'inline-flex';
            startBtn.textContent = 'Start camera';
            startBtn.addEventListener('click', start);
            start(); // auto-start; the button is the retry path
        }).catch(function (e) {
            fail('QR decoding is not available: ' + (e && e.message ? e.message : e));
        });
    })();
    </script>

@endsection
