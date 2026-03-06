@echo off
echo Adding Windows Firewall rule for Laravel Development Server...
echo.

netsh advfirewall firewall delete rule name="Laravel Development Server" >nul 2>&1
netsh advfirewall firewall add rule name="Laravel Development Server" dir=in action=allow protocol=TCP localport=8000

echo.
echo Firewall rule added successfully!
echo Port 8000 is now open for incoming connections.
echo.
pause
