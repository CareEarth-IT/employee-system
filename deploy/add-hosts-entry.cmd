@echo off
setlocal
echo.
echo hosts に employee.local を追加します（管理者権限が必要です）
echo.
echo 次の1行を hosts ファイルの末尾に追加してください:
echo.
echo   127.0.0.1    employee.local
echo.
echo メモ帳が開きます。上記1行を貼り付けて保存してください。
echo.
pause
powershell -Command "Start-Process notepad 'C:\Windows\System32\drivers\etc\hosts' -Verb RunAs"
echo.
echo 保存後、XAMPP で Apache を再起動してから開いてください:
echo   http://employee.local/login
echo.
pause
