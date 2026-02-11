:loop
@echo off
@REM "C:\Users\hobaIT\AppData\Local\Programs\WinSCP\WinSCP.com" ^
@REM   /log="C:\xampp\htdocs\pleasant\bin\Automatik\WinSCP.log" /ini=nul ^
@REM   /command ^
@REM     "open ftp://reinhold-sohn-hygiene:Ro8n%%21n265@82.165.79.24/" ^
@REM     "synchronize remote Z:\data /httpdocs/public/artikelbilder" ^
@REM     "exit"
@REM C:\xampp\php\php.exe C:\xampp\htdocs\pleasant\db\updateFromSync.php
@REM C:\xampp\php\php.exe C:\xampp\htdocs\pleasant\db\orders.php
@REM C:\xampp\php\php.exe C:\xampp\htdocs\pleasant\db\customer.php
@REM C:\xampp\php\php.exe C:\xampp\htdocs\pleasant\db\updateVertreter.php
@REM C:\xampp\php\php.exe C:\xampp\htdocs\pleasant\db\updateRules.php
@REM C:\xampp\php\php.exe C:\xampp\htdocs\pleasant\db\remove_deleted_products.php

C:\xampp\php\php.exe C:\xampp\htdocs\pleasant\db\scripts\update_from_sync.php
timeout /t 600
goto loop
