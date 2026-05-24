@echo off
title Plat Reader - All In One
cd /d "%~dp0python"
echo ============================================
echo   Plat Reader - Live View + Deteksi Plat
echo ============================================
echo.
echo Live view : http://127.0.0.1:8093/
echo Dashboard : http://127.0.0.1:8092/
echo.
echo Live view + deteksi plat OTOMATIS berjalan.
echo TIDAK perlu start_reader.bat lagi!
echo.
echo Tekan Ctrl+C untuk berhenti.
echo ============================================
echo.
python streamer.py
pause
