<#
  watch-invoices.ps1 - standalone alternative to the n8n workflow.

  Watches a folder for new supplier invoice files (PDF/JPG/PNG), extracts them
  with Gemini, matches + queues them into Simple Accounting's Invoice Review
  page via POST /api/v1/purchase/lines/review, then moves the file to \queued.

  Usage:
      powershell -ExecutionPolicy Bypass -File .\watch-invoices.ps1

  Fill in the config block below (or set them as real environment variables
  and delete the defaults) before running.
#>

# ---------------------------------------------------------------- config ---
$ApiBase   = if ($env:API_BASE)     { $env:API_BASE }     else { 'http://localhost:8080/api/v1' }
$ApiToken  = if ($env:SA_API_TOKEN) { $env:SA_API_TOKEN }  else { 'PASTE_YOUR_API_TOKEN_HERE' }
$GeminiKey = if ($env:GEMINI_KEY)   { $env:GEMINI_KEY }    else { 'PASTE_YOUR_GEMINI_KEY_HERE' }

$InboxDir  = 'C:\n8n\invoice\inbox'
$QueuedDir = 'C:\n8n\invoice\queued'
$ErrorDir  = 'C:\n8n\invoice\error'
$PollSeconds = 15

$ExtractPrompt = @'
You are an invoice parser. Return ONLY minified JSON, no markdown. Schema:
{"vendor":string,"invoice_no":string,"invoice_date":"YYYY-MM-DD","currency":string,"lines":[{"description":string,"guest_or_ref":string,"service_date":"YYYY-MM-DD"|null,"reservation_code":string|null,"amount":number}]}
Rules: amount is a plain number (no thousands separators). guest_or_ref = the guest name or booking reference the line is for. If a line is a discount, rebate, or adjustment that applies to another line (e.g. a room charge), give it the SAME guest_or_ref and reservation_code as that line, and make amount negative. Do not invent a discount that is not printed on the invoice. If a line has no own date, leave service_date null.
'@

# ------------------------------------------------------------------ setup ---
foreach ($d in @($InboxDir, $QueuedDir, $ErrorDir)) {
    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Force -Path $d | Out-Null }
}

function Get-MimeType([string]$path) {
    switch ([System.IO.Path]::GetExtension($path).ToLower()) {
        '.pdf'  { 'application/pdf' }
        '.png'  { 'image/png' }
        '.jpg'  { 'image/jpeg' }
        '.jpeg' { 'image/jpeg' }
        default { 'application/octet-stream' }
    }
}

function Process-Invoice([string]$path) {
    $name = Split-Path $path -Leaf
    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Processing $name"

    try {
        $bytes  = [System.IO.File]::ReadAllBytes($path)
        $base64 = [System.Convert]::ToBase64String($bytes)
        $mime   = Get-MimeType $path

        # 1. Extract with Gemini ------------------------------------------
        $geminiBody = @{
            contents = @(@{ parts = @(
                @{ inline_data = @{ mime_type = $mime; data = $base64 } },
                @{ text = $ExtractPrompt }
            ) })
            generationConfig = @{ response_mime_type = 'application/json' }
        } | ConvertTo-Json -Depth 10 -Compress

        $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=$GeminiKey"
        $geminiResp = Invoke-RestMethod -Uri $geminiUrl -Method Post -ContentType 'application/json' -Body $geminiBody

        $invoiceJson = $geminiResp.candidates[0].content.parts[0].text
        $inv = $invoiceJson | ConvertFrom-Json

        # 2. NET discount/adjustment lines into the booking they apply to
        #    (the review API rejects a negative cost, and matching is per
        #    booking anyway, not per invoice line), then shape into the
        #    review-queue payload. -----------------------------------------
        $groups = [ordered]@{}
        foreach ($l in $inv.lines) {
            $key = if ($l.reservation_code) { $l.reservation_code.Trim().ToLower() }
                   elseif ($l.guest_or_ref) { $l.guest_or_ref.Trim().ToLower() }
                   else { $l.description.Trim().ToLower() }

            $amountStr = [string]$l.amount -replace '[^0-9.\-]', ''
            $amount = [double]$amountStr

            if (-not $groups.Contains($key)) {
                $groups[$key] = @{
                    party_name   = if ($l.guest_or_ref) { $l.guest_or_ref } else { $l.description }
                    service_date = $l.service_date
                    res_code     = $l.reservation_code
                    descriptions = New-Object System.Collections.Generic.List[string]
                    cost         = 0.0
                }
            }
            $g = $groups[$key]
            $g.cost += $amount
            if ($l.description) { $g.descriptions.Add($l.description) }
            if (-not $g.service_date -and $l.service_date) { $g.service_date = $l.service_date }
        }

        $items = @()
        foreach ($key in $groups.Keys) {
            $g = $groups[$key]
            if ($g.cost -lt 0) { continue } # a group that still nets negative is a data problem - drop it, don't send an invalid cost
            $items += @{
                supplier              = $inv.vendor
                service_date          = if ($g.service_date) { $g.service_date } else { $inv.invoice_date }
                party_name            = $g.party_name
                description           = ($g.descriptions -join ' + ')
                res_code               = $g.res_code
                cost                   = [math]::Round($g.cost, 2)
                supplier_invoice_ref   = $inv.invoice_no
                supplier_invoice_date  = $inv.invoice_date
            }
        }

        $reviewBody = @{
            source      = 'watch-invoices.ps1'
            file_name   = $name
            vendor      = $inv.vendor
            window_days = 5
            items       = $items
        } | ConvertTo-Json -Depth 10 -Compress

        # 3. POST to the review queue ---------------------------------------
        $headers = @{ Authorization = "Bearer $ApiToken" }
        $result  = Invoke-RestMethod -Uri "$ApiBase/purchase/lines/review" -Method Post -ContentType 'application/json' -Headers $headers -Body $reviewBody

        Write-Host ("  batch {0}: received {1}, would_apply {2}, over_budget {3}, not_found {4}, ambiguous {5}" -f `
            $result.batch_id, $result.summary.received, $result.summary.would_apply, $result.summary.over_budget, $result.summary.not_found, $result.summary.ambiguous)

        Move-Item -Path $path -Destination (Join-Path $QueuedDir $name) -Force
    }
    catch {
        Write-Host "  FAILED: $($_.Exception.Message)" -ForegroundColor Red
        try { Move-Item -Path $path -Destination (Join-Path $ErrorDir $name) -Force } catch {}
    }
}

# -------------------------------------------------------------- main loop ---
Write-Host "Watching $InboxDir every $PollSeconds s. Ctrl+C to stop."
while ($true) {
    $files = Get-ChildItem -Path $InboxDir -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Extension -match '\.(pdf|png|jpg|jpeg)$' }
    foreach ($f in $files) { Process-Invoice $f.FullName }
    Start-Sleep -Seconds $PollSeconds
}
