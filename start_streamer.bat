@echo off
title Plat Reader - Camera Streamer
cd /d "%~dp0python"
echo ============================================
echo   Plat Reader - Camera Streamer
echo ============================================
echo.
echo Stream tersedia di:
echo   http://127.0.0.1:8093/
echo.
echo Tekan Ctrl+C untuk berhenti.
echo ============================================
echo.
python streamer.py
pause
