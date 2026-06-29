@echo off
setlocal
cd /d "%~dp0"
set "ROOT=%~dp0.."
if exist "%ROOT%\.venv\Scripts\pythonw.exe" (
    start "" "%ROOT%\.venv\Scripts\pythonw.exe" "%~dp0run.py"
) else (
    start "" "%ROOT%\.venv\Scripts\python.exe" "%~dp0run.py"
)
