<#
.SYNOPSIS
    Tester Helper Tool untuk Djuragan POS
    Membantu tester memantau update dari upstream (repo tim) dan sinkronisasi ke origin (repo tester),
    serta memantau log & performa sistem.

.EXAMPLE
    .\tester-tool.ps1 updates    # Cek update terbaru dari upstream tim
    .\tester-tool.ps1 sync       # Tarik update upstream/main dan push ke origin/main
    .\tester-tool.ps1 logs       # Pantau log error terbaru
    .\tester-tool.ps1 status     # Cek status git & spark routes/migrations
#>

param(
    [ValidateSet('updates', 'sync', 'logs', 'status', 'help')]
    [string]$Action = 'updates'
)

function Show-Header {
    Write-Host "==================================================" -ForegroundColor Cyan
    Write-Host "   DJURAGAN POS - TESTER & MONITORING TOOL        " -ForegroundColor Yellow
    Write-Host "==================================================" -ForegroundColor Cyan
}

switch ($Action) {
    'updates' {
        Show-Header
        Write-Host "[1/3] Mengambil informasi update terbaru dari upstream (tim)..." -ForegroundColor Cyan
        git fetch upstream
        
        Write-Host "`n[2/3] Commit baru di upstream/main yang belum masuk ke lokal:" -ForegroundColor Yellow
        $commits = git log main..upstream/main --oneline -n 10
        if ($commits) {
            $commits | ForEach-Object { Write-Host " - $_" -ForegroundColor Green }
        } else {
            Write-Host "Tidak ada commit baru di branch upstream/main. Branch sudah up to date!" -ForegroundColor Green
        }

        Write-Host "`n[3/3] Status branch remote tim lainnya:" -ForegroundColor Cyan
        git branch -r | Select-String "upstream/" | ForEach-Object { Write-Host " $_" -ForegroundColor Gray }
        Write-Host "`nUntuk menggabungkan update tim: jalankan '.\tester-tool.ps1 sync'" -ForegroundColor DarkYellow
    }

    'sync' {
        Show-Header
        Write-Host "[1/3] Fetching dari upstream..." -ForegroundColor Cyan
        git fetch upstream
        
        Write-Host "[2/3] Merging update dari upstream/main ke main lokal..." -ForegroundColor Cyan
        git merge upstream/main
        
        Write-Host "[3/3] Pushing hasil update ke repository tester (origin/main)..." -ForegroundColor Cyan
        git push origin main
        Write-Host "`nSinkronisasi selesai! Repository kamu sudah mendapatkan update terbaru." -ForegroundColor Green
    }

    'logs' {
        Show-Header
        Write-Host "Mencari file log CodeIgniter terbaru di writable/logs/..." -ForegroundColor Cyan
        $latestLog = Get-ChildItem -Path "writable/logs" -Filter "log-*.log" -ErrorAction SilentlyContinue | 
                     Sort-Object LastWriteTime -Descending | 
                     Select-Object -First 1

        if ($latestLog) {
            Write-Host "Menampilkan 40 baris terakhir dari: $($latestLog.Name)" -ForegroundColor Yellow
            Write-Host "Tekan Ctrl+C untuk berhenti memantau.`n" -ForegroundColor DarkGray
            Get-Content -Path $latestLog.FullName -Tail 40 -Wait
        } else {
            Write-Host "Belum ada file log error di writable/logs/." -ForegroundColor Green
        }
    }

    'status' {
        Show-Header
        Write-Host "--- Git Remotes ---" -ForegroundColor Yellow
        git remote -v
        
        Write-Host "`n--- Git Status ---" -ForegroundColor Yellow
        git status -s
        
        Write-Host "`n--- Status Migrasi Database ---" -ForegroundColor Yellow
        php spark migrate:status
    }

    'help' {
        Show-Header
        Write-Host "Daftar Perintah:" -ForegroundColor Yellow
        Write-Host "  .\tester-tool.ps1 updates  : Cek update commit baru dari tim developer (upstream)"
        Write-Host "  .\tester-tool.ps1 sync     : Tarik update terbaru upstream/main lalu sync ke repo kamu"
        Write-Host "  .\tester-tool.ps1 logs     : Pantau real-time log error CodeIgniter"
        Write-Host "  .\tester-tool.ps1 status   : Cek konfigurasi remote, git status, dan migrasi database"
    }
}
