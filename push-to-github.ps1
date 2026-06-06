# ── Push MyCarbonFootprint to GitHub ──
# Run this once from D:\mycarbonfootprint in PowerShell

$repoName  = "mycarbonfootprint"
$githubUser = "abdullamusthafa-ops"

Write-Host ""
Write-Host "=== Push MyCarbonFootprint to GitHub ===" -ForegroundColor Green
Write-Host ""
$token = Read-Host "Paste your GitHub Personal Access Token" -AsSecureString
$plainToken = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($token)
)

# Create the repo via GitHub API
Write-Host "`nCreating GitHub repo '$repoName'..." -ForegroundColor Cyan
$headers = @{
    Authorization = "token $plainToken"
    Accept        = "application/vnd.github+json"
}
$body = @{
    name        = $repoName
    description = "UAE Carbon Footprint Calculator — CFC 2026 Methodology"
    private     = $false
    auto_init   = $false
} | ConvertTo-Json

try {
    $resp = Invoke-RestMethod -Uri "https://api.github.com/user/repos" `
        -Method Post -Headers $headers -Body $body -ContentType "application/json"
    Write-Host "Repo created: $($resp.html_url)" -ForegroundColor Green
} catch {
    $msg = $_.ErrorDetails.Message | ConvertFrom-Json
    if ($msg.message -like "*already exists*") {
        Write-Host "Repo already exists — continuing..." -ForegroundColor Yellow
    } else {
        Write-Host "Error: $($msg.message)" -ForegroundColor Red
        exit 1
    }
}

# Set remote and push
$remoteUrl = "https://$plainToken@github.com/$githubUser/$repoName.git"
git remote remove origin 2>$null
git remote add origin $remoteUrl
git branch -M main
git push -u origin main

Write-Host ""
Write-Host "Done! View your repo at:" -ForegroundColor Green
Write-Host "https://github.com/$githubUser/$repoName" -ForegroundColor Cyan
Write-Host ""

# Remove the token from remote URL for safety
git remote set-url origin "https://github.com/$githubUser/$repoName.git"
Write-Host "Token removed from remote URL." -ForegroundColor Gray

# Self-delete this script (optional)
Remove-Item $MyInvocation.MyCommand.Path -Force 2>$null
