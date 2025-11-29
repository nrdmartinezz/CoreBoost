# Quick Dev Release Script for CoreBoost (PowerShell)
# Usage: .\scripts\dev-release.ps1 -ReleaseType dev
# Supports: dev, alpha, beta

param(
    [string]$ReleaseType = "dev"
)

$ErrorActionPreference = "Stop"

Write-Host "🚀 CoreBoost Dev Release Builder" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

# Get paths
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$PluginDir = Split-Path -Parent $ScriptDir

# Get current version from plugin file
$PluginFile = Join-Path $PluginDir "coreboost.php"
$VersionMatch = (Get-Content $PluginFile | Select-String 'Version: (.+)' -AllMatches).Matches[0].Groups[1].Value
$CurrentVersion = $VersionMatch.Trim()

Write-Host "📦 Current plugin version: $CurrentVersion"
Write-Host "📝 Release type: $ReleaseType"
Write-Host ""

# Build timestamp
$BuildTime = Get-Date -Format "yyyyMMdd-HHmmss"
$UnixTime = [int][double]::Parse((Get-Date -UFormat %s))
$DevVersion = "$CurrentVersion-$ReleaseType.$UnixTime"

Write-Host "🏗️  Building dev release: $DevVersion"
Write-Host ""

# Create release directory
$ReleaseDir = "coreboost-dev-build-$BuildTime"
$ReleasePath = Join-Path $PluginDir $ReleaseDir
$PluginPath = Join-Path $ReleasePath "coreboost"

New-Item -ItemType Directory -Path $PluginPath -Force | Out-Null

# Copy plugin files
Write-Host "📋 Copying plugin files..."
Copy-Item $PluginFile $PluginPath -Force
Copy-Item (Join-Path $PluginDir "readme.txt") $PluginPath -Force
Copy-Item (Join-Path $PluginDir "README.md") $PluginPath -Force
Copy-Item (Join-Path $PluginDir "CHANGELOG.md") $PluginPath -Force -ErrorAction SilentlyContinue

# Copy directories
if (Test-Path (Join-Path $PluginDir "includes")) {
    Copy-Item (Join-Path $PluginDir "includes") (Join-Path $PluginPath "includes") -Recurse -Force
}
if (Test-Path (Join-Path $PluginDir "assets")) {
    Copy-Item (Join-Path $PluginDir "assets") (Join-Path $PluginPath "assets") -Recurse -Force
}

# Verify structure
$ClassCount = @(Get-ChildItem -Path (Join-Path $PluginPath "includes") -Filter "*.php" -Recurse -ErrorAction SilentlyContinue).Count
Write-Host "✅ Files copied ($ClassCount PHP classes)"
Write-Host ""

# Create ZIP
Write-Host "📦 Creating ZIP archive..."
$ZipFile = "coreboost-$DevVersion.zip"
$ZipPath = Join-Path $ReleasePath $ZipFile

# Use built-in Windows compression (PowerShell 5.0+)
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($PluginPath, $ZipPath)

$ZipSize = (Get-Item $ZipPath).Length / 1MB
Write-Host "✅ ZIP created: $ZipFile ($($ZipSize.ToString('F1')) MB)"
Write-Host ""

# Create info file
$InfoFile = Join-Path $ReleasePath "RELEASE_INFO.txt"
$BuildDateTime = Get-Date -Format "yyyy-MM-dd HH:mm:ss UTC"
$InfoContent = @"
CoreBoost Dev Release Info
==========================

Version: $DevVersion
Release Type: $ReleaseType
Built: $BuildDateTime
Build Time: $BuildTime

ZIP File: $ZipFile
Size: $($ZipSize.ToString('F1')) MB

---
This is a development build. Test thoroughly before production use.
"@

Set-Content -Path $InfoFile -Value $InfoContent
Write-Host "📄 Release info:"
Get-Content $InfoFile
Write-Host ""

Write-Host "✅ Dev release ready!" -ForegroundColor Green
Write-Host ""
Write-Host "📁 Location: $ReleasePath"
Write-Host ""
Write-Host "Next steps:"
Write-Host "1. Extract: Expand-Archive $ZipPath -DestinationPath (desired location)"
Write-Host "2. Test the plugin"
Write-Host "3. If ready, push with: git tag v$DevVersion && git push origin v$DevVersion"
Write-Host ""
