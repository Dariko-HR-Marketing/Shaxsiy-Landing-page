@echo off
title DARIKO_v14_FINAL Vercel Deployer
color 0A
echo ========================================================
echo   DARIKO_v14_FINAL - Vercel.com ga Avtomatik Deploy
echo ========================================================
echo.
echo Vercel API ga so'rov yuborilmoqda...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command "$token='vcp_2RqWQavIRhXp5Hh7C4TigZ990MIn0P4nSbqvzgrTaqUCRzoVZh2hqKr7'; $siteDir='C:\Users\User\Desktop\Новая папка\Antigraviry Dariko.uz\DARIKO_v14_FINAL'; $files=@('index.html','app.js','vercel.json','package.json','sitemap.xml','robots.txt'); $fileList=@(); foreach($f in $files){ $p=Join-Path $siteDir $f; if(Test-Path $p){ $content=[System.IO.File]::ReadAllText($p, [System.Text.Encoding]::UTF8); $fileList += @{ file=$f; data=$content } } }; $payload = @{ name='dariko-v14-final'; files=$fileList; target='production' }; $json = $payload | ConvertTo-Json -Depth 10; try { $res = Invoke-RestMethod -Uri 'https://api.vercel.com/v13/deployments' -Method Post -Headers @{ Authorization=('Bearer ' + $token); 'Content-Type'='application/json' } -Body ([System.Text.Encoding]::UTF8.GetBytes($json)); Write-Host ''; Write-Host '========================================================' -ForegroundColor Green; Write-Host '  SAYT MUVAFFAQIYATLI JONLI EFIRGA CHIQARILDI!' -ForegroundColor Green; Write-Host '========================================================' -ForegroundColor Green; Write-Host ''; Write-Host ('  Jonli Havola: https://' + $res.url) -ForegroundColor Yellow; Write-Host ''; Start-Process ('https://' + $res.url) } catch { Write-Host ('Xatolik yuz berdi: ' + $_.Exception.Message) -ForegroundColor Red }"

echo.
echo Jarayon yakunlandi. Chiqish uchun istalgan tugmani bosing.
pause > nul
