# Test Endpoints Script for glpi-middleware-api
# PowerShell 5.1 (Windows PowerShell)
# Usage: Open PowerShell, cd to project root and run: .\scripts\test_endpoints.ps1

$base = 'http://localhost:3003'

function Test-Get($uri) {
    Write-Host "\n=== GET $uri ===" -ForegroundColor Cyan
    try {
        $res = Invoke-RestMethod -Method Get -Uri $uri -ErrorAction Stop
        $json = $res | ConvertTo-Json -Depth 6
        Write-Host $json
    } catch {
        Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "Starting endpoint tests against $base" -ForegroundColor Green

# 1) Test scanned-items for an example audit id (replace 123 with a real id)
$auditId = 123
Test-Get "$base/api/audit/$auditId/scanned-items?limit=5&offset=0"

# 2) Test audit data (filtered by audit_id)
Test-Get "$base/api/audit/data?audit_id=$auditId&limit=5&offset=0"

# 3) Test retrieving a single asset by assetTag (replace ASSETTAG123)
$assetTag = 'ASSETTAG123'
Test-Get "$base/api/assets/$assetTag"

# 4) Example upload test (requires a test image at ./test.jpg)
#    Windows PowerShell 5.1 doesn't support -Form easily; prefer using curl.exe or Postman for multipart uploads.
$uploadFile = Join-Path (Get-Location) 'test.jpg'
if (Test-Path $uploadFile) {
    $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
    if ($curl) {
        Write-Host "\n=== Uploading $uploadFile using curl.exe (example) ===" -ForegroundColor Cyan
        # Example: uploads file file as form field 'image' and includes audit_result_id form field
        & curl.exe -v -X POST -F "image=@$uploadFile" -F "audit_result_id=1" "$base/api/audit/upload-image"
    } else {
        Write-Host "\nNo curl.exe detected. Use Postman or place curl.exe in PATH to run upload test." -ForegroundColor Yellow
        Write-Host "Example curl command:" -ForegroundColor Yellow
        Write-Host "curl -X POST -F \"image=@$uploadFile\" -F \"audit_result_id=1\" $base/api/audit/upload-image"
    }
} else {
    Write-Host "\nNo test image found at $uploadFile. Place a test image named 'test.jpg' in the project root to try the upload test." -ForegroundColor Yellow
}

Write-Host "\nEndpoint tests completed." -ForegroundColor Green
