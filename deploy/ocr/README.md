# Offline OCR setup (Tesseract for the Forms module)

The Forms module OCRs each scanned form so its text is searchable. It uses
[`tesseract.js`](https://github.com/naptha/tesseract.js) running **inside the Node
middleware**, so nothing leaves the LAN.

The middleware degrades gracefully: if `tesseract.js` or the language data is not
present, forms **still upload and track** — only the automatic text extraction is
skipped. So you can ship the module first and enable OCR when ready.

## One-time install (needs internet **once**, on the server)

From the middleware folder (where `server.js` lives — note it is inside
`node_modules/`, so run npm at the repo root that contains `package.json`):

```
npm install tesseract.js
```

## Offline language data (required for a disconnected LAN)

By default `tesseract.js` downloads `eng.traineddata` from a CDN the first time it
runs — which **fails on an offline network**. Bundle the language file locally:

1. Download `eng.traineddata` (fast/standard model) from
   https://github.com/tesseract-ocr/tessdata_fast/raw/main/eng.traineddata
2. Put it in a folder named `ocr-lang/` next to `package.json`:
   ```
   <repo>/ocr-lang/eng.traineddata
   ```
   (This is the default the middleware looks for. To use another path, set the
   `OCR_LANG_PATH` environment variable.)

The middleware calls Tesseract with `langPath = OCR_LANG_PATH` and `gzip: false`,
so an **uncompressed** `eng.traineddata` in that folder is loaded with no network.

## Environment variables (optional)

| Variable        | Default            | Purpose                                   |
|-----------------|--------------------|-------------------------------------------|
| `OCR_LANG`      | `eng`              | Tesseract language code                   |
| `OCR_LANG_PATH` | `<repo>/ocr-lang`  | Folder holding `<lang>.traineddata`       |

## Verify

Disconnect the server from the internet, restart the middleware, upload a form
with clear text, and open it in the dashboard — the OCR text panel should be
populated. Accuracy depends on scan quality (clean, high-contrast, upright scans
read best).

## Other languages

Add the matching `*.traineddata` (e.g. `msa.traineddata` for Malay) into
`ocr-lang/` and set `OCR_LANG` accordingly.
