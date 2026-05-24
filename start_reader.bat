@echo off
title Plat Reader
echo Starting Plat Reader...
echo.
echo Pastikan Python 3.10+ sudah terinstall.
echo Dependencies: easyocr, opencv-python-headless, mysql-connector-python
echo.
echo Kamera akan diproses tiap 5 detik.
echo Tekan Ctrl+C untuk berhenti.
echo.
cd /d "%~dp0"
python python\reader.py --interval 5
pause
