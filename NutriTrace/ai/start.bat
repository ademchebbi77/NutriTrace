@echo off
rem Starts the local AI service of NutriTrace (chatbot + transport anomaly check).
rem Double-click this file and keep the window open while using the site.
title NutriTrace AI (ne pas fermer)
cd /d "%~dp0"

if not exist ".venv\Scripts\python.exe" (
    echo The Python environment is missing: see README.md, section 1 "Installation".
    pause
    exit /b 1
)

echo Chargement du modele (environ 30 secondes)...
.venv\Scripts\python.exe -m uvicorn server:app --host 127.0.0.1 --port 8001
pause
