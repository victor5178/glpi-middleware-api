# Offline OCR + PDF + signature pipeline (Forms module)

The Forms module reads each form so its text is searchable, and detects
signatures — all **inside the Node middleware**, so nothing leaves the LAN.

Pipeline per upload:
1. **Native/digital PDF** → text via `pdf-parse` (fast, exact); OCR skipped.
2. **Scanned PDF** → page 1 rasterised with `pdf-to-img`, then OCR.
3. **Image** → OCR directly with `tesseract.js`.
4. **Signature detection** → Tesseract word boxes locate an anchor phrase
   (e.g. "ITS Staff signature"); `sharp` crops the region above/next to it,
   thresholds it, and flags a signature when dark-pixel (ink) density > 5%.

Everything **degrades gracefully**: if a dependency or the language data is
missing, forms **still upload and track** — only OCR / signature detection is
skipped. So you can ship first and enable this when ready.

## 1. One-time install (needs internet **once**, on the server)

From the folder that holds `package.json` (the middleware repo root — note
`server.js` lives inside `node_modules/`):

```
# Pin the versions that match the code AND run on Node 18/20 LTS.
# Do NOT `npm install pdf-parse pdf-to-img sharp` without versions — that pulls
# pdf-parse@2 / pdf-to-img@6 which need Node >=20.19/22 and a different API.
npm install pdf-parse@1.1.1 pdf-to-img@4 sharp@0.33.5 tesseract.js@5
```

> **Node version:** these pins work on Node **18.17+ / 20.3+**. The code also
> supports pdf-parse v2's API, so if you prefer the newest libraries
> (`pdf-parse@2`, `pdf-to-img@6`), upgrade Node to **22 LTS** (or ≥20.19) first —
> otherwise you'll see `EBADENGINE` warnings and signature rasterisation can fail
> at runtime. Either path works; the pinned versions above are the low-risk one.

`sharp` and `pdf-to-img`'s backend download small **prebuilt binaries** at
install time (internet needed only here); at runtime nothing is fetched.

## 2. Offline language data (required for a disconnected LAN)

By default `tesseract.js` would fetch `eng.traineddata` from a CDN — which
**fails offline**. Bundle it locally:

1. Download `eng.traineddata` (fast model):
   https://github.com/tesseract-ocr/tessdata_fast/raw/main/eng.traineddata
2. Put it in **this folder** (`deploy/ocr/`), the default the middleware checks:
   ```
   <repo>/deploy/ocr/eng.traineddata
   ```
   (The legacy path `<repo>/ocr-lang/` is also accepted. Override with the
   `OCR_LANG_PATH` / `OCR_ASSETS_DIR` env var.)

The middleware loads Tesseract with **strictly local** paths — `langPath`,
`corePath`, `workerPath` — and `gzip:false`, so an **uncompressed**
`eng.traineddata` here is used with no network. `corePath`/`workerPath` default
to the installed `tesseract.js-core` / `tesseract.js` packages (already local in
Node); for a fully air-gapped box you may copy the core wasm + worker script
here and point the env vars at them.

## 3. Environment variables (optional)

| Variable             | Default                         | Purpose                                        |
|----------------------|---------------------------------|------------------------------------------------|
| `OCR_LANG`           | `eng`                           | Tesseract language code                        |
| `OCR_LANG_PATH` / `OCR_ASSETS_DIR` | `<repo>/deploy/ocr` | Folder holding `<lang>.traineddata`            |
| `OCR_CORE_PATH`      | installed `tesseract.js-core`   | Folder with the core WASM                      |
| `OCR_WORKER_PATH`    | installed node worker script    | Path to the Tesseract node worker              |
| `SIG_INK_THRESHOLD`  | `0.05`                          | Min dark-pixel ratio to call a signature       |
| `SIG_GREY_THRESHOLD` | `150`                           | Greyscale threshold (0–255) before counting ink|

## 4. Verify (offline)

Disconnect the server from the internet and restart the middleware. Startup logs
show the resolved local OCR paths (`🔤 OCR paths — lang:… core:… worker:local`).
Upload:
- a **text PDF** → detail shows `ocr_source = native_pdf` with text;
- a **scanned image/PDF** → `ocr_source = image_ocr` with text;
- a **signed Form 1** → the Signatures panel shows the ITS-Staff signer detected.

Accuracy depends on scan quality (clean, high-contrast, upright scans read best).
Signature detection is heuristic — it confirms ink near the label, not identity.

## 5. Other languages

Add the matching `*.traineddata` (e.g. `msa.traineddata` for Malay, so the
"Tandatangan" anchor reads better) into this folder and set `OCR_LANG`.
