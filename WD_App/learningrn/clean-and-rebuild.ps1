# Clean and Rebuild Script
# Removes the problematic QR library and rebuilds the project

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Clean and Rebuild Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Remove node_modules and package-lock.json
Write-Host "Step 1: Cleaning node_modules..." -ForegroundColor Yellow
if (Test-Path "node_modules") {
    Remove-Item -Recurse -Force "node_modules"
    Write-Host "  ✓ Removed node_modules" -ForegroundColor Green
} else {
    Write-Host "  ℹ node_modules not found" -ForegroundColor Gray
}

if (Test-Path "package-lock.json") {
    Remove-Item -Force "package-lock.json"
    Write-Host "  ✓ Removed package-lock.json" -ForegroundColor Green
} else {
    Write-Host "  ℹ package-lock.json not found" -ForegroundColor Gray
}

Write-Host ""

# Step 2: Reinstall dependencies
Write-Host "Step 2: Installing dependencies (without QR library)..." -ForegroundColor Yellow
npm install
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ✗ npm install failed" -ForegroundColor Red
    exit 1
}
Write-Host "  ✓ Dependencies installed" -ForegroundColor Green
Write-Host ""

# Step 3: Clean Android build
Write-Host "Step 3: Cleaning Android build..." -ForegroundColor Yellow
if (Test-Path "android") {
    Set-Location "android"
    & .\gradlew.bat clean --no-daemon
    if ($LASTEXITCODE -ne 0) {
        Write-Host "  ⚠ Clean had warnings, but continuing..." -ForegroundColor Yellow
    } else {
        Write-Host "  ✓ Android build cleaned" -ForegroundColor Green
    }
    Set-Location ".."
} else {
    Write-Host "  ℹ android directory not found" -ForegroundColor Gray
}
Write-Host ""

# Step 4: Run prebuild to regenerate native code
Write-Host "Step 4: Running Expo prebuild..." -ForegroundColor Yellow
npx expo prebuild --clean
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ✗ Prebuild failed" -ForegroundColor Red
    exit 1
}
Write-Host "  ✓ Prebuild completed" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Green
Write-Host "Cleanup Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. Run: .\build-android-release.ps1" -ForegroundColor Yellow
Write-Host "     OR" -ForegroundColor Gray
Write-Host "  2. Run: cd android; .\gradlew.bat assembleRelease" -ForegroundColor Yellow
Write-Host ""
