param(
    [string]$OutputDirectory = (Join-Path $env:USERPROFILE "E&R Database Backups")
)

$ErrorActionPreference = "Stop"
$database = if ($env:ER_DB_NAME) { $env:ER_DB_NAME } else { "er_real_estate" }
$hostName = if ($env:ER_DB_HOST) { $env:ER_DB_HOST } else { "localhost" }
$username = if ($env:ER_DB_USER) { $env:ER_DB_USER } else { "root" }
$dumpPath = if ($env:MYSQLDUMP_PATH) { $env:MYSQLDUMP_PATH } else { "C:\xampp\mysql\bin\mysqldump.exe" }

if (-not (Test-Path -LiteralPath $dumpPath -PathType Leaf)) {
    throw "mysqldump was not found. Set MYSQLDUMP_PATH to its full path."
}

if (-not (Test-Path -LiteralPath $OutputDirectory -PathType Container)) {
    New-Item -ItemType Directory -Path $OutputDirectory | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupFile = Join-Path (Resolve-Path -LiteralPath $OutputDirectory).Path "$database-$timestamp.sql"
$arguments = @(
    "--host=$hostName",
    "--user=$username",
    "--single-transaction",
    "--routines",
    "--triggers",
    "--events",
    "--databases",
    $database,
    "--result-file=$backupFile"
)

$startInfo = New-Object System.Diagnostics.ProcessStartInfo
$startInfo.FileName = $dumpPath
$startInfo.Arguments = ($arguments | ForEach-Object { '"' + ($_ -replace '"', '\"') + '"' }) -join " "
$startInfo.UseShellExecute = $false
$startInfo.CreateNoWindow = $true
$startInfo.RedirectStandardError = $true
if ($env:ER_DB_PASSWORD) {
    $startInfo.EnvironmentVariables["MYSQL_PWD"] = $env:ER_DB_PASSWORD
}

$process = New-Object System.Diagnostics.Process
$process.StartInfo = $startInfo
if (-not $process.Start()) {
    throw "The database export process could not be started."
}
$stderr = $process.StandardError.ReadToEnd()
$process.WaitForExit()

if ($process.ExitCode -ne 0 -or -not (Test-Path -LiteralPath $backupFile -PathType Leaf) -or (Get-Item -LiteralPath $backupFile).Length -eq 0) {
    if (Test-Path -LiteralPath $backupFile -PathType Leaf) {
        Remove-Item -LiteralPath $backupFile
    }
    throw "Database export failed. $stderr"
}

Write-Output "Database export created: $backupFile"
