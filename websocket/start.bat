@echo off
cd /d "%~dp0.."
title ZYNKO WebSocket
php websocket\server.php
pause
