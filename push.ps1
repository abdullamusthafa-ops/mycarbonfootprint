Set-Location "D:\mycarbonfootprint"
$repoName   = "mycarbonfootprint"
$githubUser = "abdullamusthafa-ops"

Write-Host "Paste your GitHub Personal Access Token (input hidden):" -ForegroundColor Cyan
$secToken   = Read-Host -AsSecureString
$plainToken = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secToken)
)

$headers = @{
    Authorization = "token $plainToken"
    Accept        = "application/vnd.github+json"
}
$body = @{
    name        = $repoName
    description = "UAE Carbon Footprint Calculator CFC 2026"
    private     = $false
    auto_init   = $false
} | ConvertTo-Json

Write-Host "Creating repo on GitHub..." -ForegroundColor Cyan
try {
    $resp = Invoke-RestMethod -Uri "https://api.github.com/user/repos" `
        -Method Post -Headers $headers -Body $body -ContentType "application/json"
    Write-Host "Created: $($resp.html_url)" -ForegroundColor Green
} catch {
    Write-Host "Repo may already exist, continuing with push..." -ForegroundColor Yellow
}

$remote = "https://${plainToken}@github.com/${githubUser}/${repoName}.git"
git remote remove origin 2>$null
git remote add origin $remote
git branch -M main
git push -u origin main

git remote set-url origin "https://github.com/${githubUser}/${repoName}.git"
Write-Host ""
Write-Host "Done! Repo live at: https://github.com/${githubUser}/${repoName}" -ForegroundColor Green
Remove-Item $MyInvocation.MyCommand.Path -Force
