$content = Get-Content 'c:\xampp\htdocs\kangi.com.ng\resources\views\instrument_registration\index.blade.php'
$newContent = @()

foreach ($line in $content) {
    $newContent += $line
    if ($line -match "batchregistermodal") {
        $newContent += "    @include('instrument_registration.partials.quickbatchmodal')"
    }
}

$newContent | Set-Content 'c:\xampp\htdocs\kangi.com.ng\resources\views\instrument_registration\index.blade.php'