@echo off
cd /d "%~dp0"
where php >nul 2>&1
if errorlevel 1 (
  echo PHP not found in PATH. Install PHP and reopen this terminal.
  pause
  exit /b 1
)

set PORT=8000
set SSL_CERT_FILE=%~dp0cacert.pem
set CURL_CA_BUNDLE=%~dp0cacert.pem

echo Starting PHP server on http://localhost:%PORT% ...
php -S localhost:%PORT% -t public_html
pause