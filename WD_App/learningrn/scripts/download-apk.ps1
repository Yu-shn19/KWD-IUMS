# Download APK from EAS Build
$buildId = "372c7ae2-7798-4d2d-9463-caccef48633b"
$buildUrl = "https://expo.dev/accounts/aj_2004/projects/learningrn/builds/$buildId"
$outputFile = "KWD-app.apk"

Write-Host "Downloading APK from EAS Build..." -ForegroundColor Cyan
Write-Host "Build ID: $buildId" -ForegroundColor Gray
Write-Host ""

# Method 1: Try to get download URL from build page
Write-Host "Fetching build page..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri $buildUrl -UseBasicParsing -ErrorAction Stop
    $html = $response.Content
    
    # Look for download link in the HTML
    if ($html -match 'href="([^"]*artifacts[^"]*\.apk[^"]*)"') {
        $downloadUrl = $matches[1]
        Write-Host "Found download URL: $downloadUrl" -ForegroundColor Green
        
        # Download the APK
        Write-Host "Downloading APK..." -ForegroundColor Yellow
        Invoke-WebRequest -Uri $downloadUrl -OutFile $outputFile -UseBasicParsing
        Write-Host "Download complete! APK saved to: $outputFile" -ForegroundColor Green
        exit 0
    }
    elseif ($html -match 'applicationArchiveUrl["\s]*:["\s]*"([^"]+)"') {
        $downloadUrl = $matches[1]
        Write-Host "Found download URL: $downloadUrl" -ForegroundColor Green
        
        # Download the APK
        Write-Host "Downloading APK..." -ForegroundColor Yellow
        Invoke-WebRequest -Uri $downloadUrl -OutFile $outputFile -UseBasicParsing
        Write-Host "Download complete! APK saved to: $outputFile" -ForegroundColor Green
        exit 0
    }
    else {
        Write-Host "Could not extract download URL from build page" -ForegroundColor Yellow
    }
}
catch {
    Write-Host "Error fetching build page: $($_.Exception.Message)" -ForegroundColor Red
}

# Method 2: Direct artifact URL (requires authentication)
$artifactUrl = "https://expo.dev/artifacts/eas/7PaeAkNrMMYCGciS1AA8U8.apk"
Write-Host ""
Write-Host "Trying direct artifact URL..." -ForegroundColor Yellow
try {
    Invoke-WebRequest -Uri $artifactUrl -OutFile $outputFile -UseBasicParsing -ErrorAction Stop
    Write-Host "Download complete! APK saved to: $outputFile" -ForegroundColor Green
    exit 0
}
catch {
    Write-Host "Direct download failed (requires authentication)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please download manually:" -ForegroundColor Cyan
    Write-Host "  1. Open: $buildUrl" -ForegroundColor White
    Write-Host "  2. Click the Download button on the build page" -ForegroundColor White
    Write-Host ""
    Write-Host "  Or use EAS CLI:" -ForegroundColor Cyan
    Write-Host "  eas build:view $buildId" -ForegroundColor White
    Write-Host ""
    
    # Open the build page in browser
    Start-Process $buildUrl
    exit 1
}
