# Android Release Build Script
# This script builds a release APK for the React Native app

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Android Release Build Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Set environment variables
$env:ANDROID_HOME = "C:\Android\Sdk"
$env:JAVA_HOME = "C:\Program Files\Java\jdk-17"
$env:ANDROID_SDK_ROOT = "C:\Android\Sdk"

# Add Java and Android tools to PATH
$env:PATH = "$env:JAVA_HOME\bin;$env:ANDROID_HOME\platform-tools;$env:ANDROID_HOME\tools;$env:ANDROID_HOME\tools\bin;$env:PATH"

Write-Host "Environment Configuration:" -ForegroundColor Yellow
Write-Host "  ANDROID_HOME: $env:ANDROID_HOME" -ForegroundColor Gray
Write-Host "  JAVA_HOME: $env:JAVA_HOME" -ForegroundColor Gray
Write-Host ""

# Verify Java installation
Write-Host "Verifying Java installation..." -ForegroundColor Yellow
try {
    $javaVersion = & "$env:JAVA_HOME\bin\java.exe" -version 2>&1
    Write-Host "  Java found: $($javaVersion[0])" -ForegroundColor Green
} catch {
    Write-Host "  ERROR: Java not found at $env:JAVA_HOME" -ForegroundColor Red
    exit 1
}

# Verify Android SDK
Write-Host "Verifying Android SDK..." -ForegroundColor Yellow
if (Test-Path $env:ANDROID_HOME) {
    Write-Host "  Android SDK found at: $env:ANDROID_HOME" -ForegroundColor Green
} else {
    Write-Host "  ERROR: Android SDK not found at $env:ANDROID_HOME" -ForegroundColor Red
    exit 1
}

# Navigate to android directory
$androidDir = Join-Path $PSScriptRoot "android"
if (-not (Test-Path $androidDir)) {
    Write-Host "ERROR: android directory not found at $androidDir" -ForegroundColor Red
    exit 1
}

Set-Location $androidDir
Write-Host ""
Write-Host "Changed to directory: $androidDir" -ForegroundColor Green
Write-Host ""

# Clean previous builds
Write-Host "Cleaning previous builds..." -ForegroundColor Yellow
& .\gradlew.bat clean --no-daemon
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Warning: Clean failed, but continuing..." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Building Release APK..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Includes:" -ForegroundColor Yellow
Write-Host "  - Keypad responsiveness fixes" -ForegroundColor Gray
Write-Host "  - Login UX improvements" -ForegroundColor Gray
Write-Host "  - Disconnector support" -ForegroundColor Gray
Write-Host "  - Tiered pricing for Rate Code C & D" -ForegroundColor Gray
Write-Host ""
Write-Host "This will take 10-20 minutes..." -ForegroundColor Yellow
Write-Host ""

# Build release APK with specific architectures
$buildCommand = ".\gradlew.bat assembleRelease -PreactNativeArchitectures=armeabi-v7a,arm64-v8a --no-daemon"
Write-Host "Executing: $buildCommand" -ForegroundColor Cyan
Write-Host ""

& .\gradlew.bat assembleRelease "-PreactNativeArchitectures=armeabi-v7a,arm64-v8a" --no-daemon

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "Build Successful!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    
    $apkPath = Join-Path $androidDir "app\build\outputs\apk\release\app-release.apk"
    if (Test-Path $apkPath) {
        $apkSize = (Get-Item $apkPath).Length / 1MB
        Write-Host "APK Location: $apkPath" -ForegroundColor Green
        Write-Host "APK Size: $([math]::Round($apkSize, 2)) MB" -ForegroundColor Green
        Write-Host ""
        Write-Host "You can now install this APK on Android devices." -ForegroundColor Cyan
    } else {
        Write-Host "Warning: APK file not found at expected location." -ForegroundColor Yellow
    }
} else {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Red
    Write-Host "Build Failed!" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please check the error messages above." -ForegroundColor Yellow
    exit 1
}
