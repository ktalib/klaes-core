{{-- The PIC as a PDF page (dompdf, A5 landscape). The card is cadastral_dept.pic._card. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Property Index Card · {{ $pic['file_number'] ?? '' }}</title>
  <style>
    @page { margin: 6mm; }
    body { margin: 0; }
  </style>
</head>
<body>
  @include('cadastral_dept.pic._card', ['pic' => $pic])
</body>
</html>
