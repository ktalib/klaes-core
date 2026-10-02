@php
    $serialNo = trim((string) ($requestRecord->serial_no ?? ''));
    if ($serialNo === '' && !empty($requestRecord->id)) {
        $serialNo = str_pad((string) $requestRecord->id, 6, '0', STR_PAD_LEFT);
    } elseif (ctype_digit($serialNo)) {
        $serialNo = str_pad($serialNo, 6, '0', STR_PAD_LEFT);
    }

    $normalizeSignatureBinary = function (string $contents, string $mimeType): array {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecrop') || !function_exists('imagepng')) {
            return [$contents, $mimeType];
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return [$contents, $mimeType];
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            return [$contents, $mimeType];
        }

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                // Keep only non-transparent, non-near-white pixels to isolate actual pen strokes.
                $isVisible = $alpha < 118;
                $isInk = ($red + $green + $blue) < 735 - 24;

                if (!$isVisible || !$isInk) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            imagedestroy($image);

            return [$contents, $mimeType];
        }

        $padding = (int) max(6, round(min($width, $height) * 0.03));
        $cropRect = [
            'x' => max(0, $minX - $padding),
            'y' => max(0, $minY - $padding),
            'width' => min($width - max(0, $minX - $padding), ($maxX - $minX + 1) + ($padding * 2)),
            'height' => min($height - max(0, $minY - $padding), ($maxY - $minY + 1) + ($padding * 2)),
        ];

        $cropped = @imagecrop($image, $cropRect);
        imagedestroy($image);

        if ($cropped === false) {
            return [$contents, $mimeType];
        }

        imagesavealpha($cropped, true);
        ob_start();
        imagepng($cropped);
        $trimmed = ob_get_clean();
        imagedestroy($cropped);

        if (!is_string($trimmed) || $trimmed === '') {
            return [$contents, $mimeType];
        }

        return [$trimmed, 'image/png'];
    };

    $resolveSignatureSrc = function (?string $path) use ($normalizeSignatureBinary): ?string {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($path, 'data:')) {
            return $path;
        }

        if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $candidates = [$path];

        if (\Illuminate\Support\Str::startsWith($path, '/storage/')) {
            $candidates[] = 'public/' . ltrim(substr($path, 9), '/');
        } elseif (\Illuminate\Support\Str::startsWith($path, 'storage/')) {
            $candidates[] = 'public/' . ltrim(substr($path, 8), '/');
        } elseif (!\Illuminate\Support\Str::startsWith($path, 'public/')) {
            $candidates[] = 'public/' . ltrim($path, '/');
        }

        foreach (array_unique($candidates) as $candidate) {
            if (!\Illuminate\Support\Facades\Storage::exists($candidate)) {
                continue;
            }

            try {
                $mimeType = \Illuminate\Support\Facades\Storage::mimeType($candidate) ?: 'image/png';
                $contents = \Illuminate\Support\Facades\Storage::get($candidate);
                [$contents, $mimeType] = $normalizeSignatureBinary($contents, $mimeType);

                return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
            } catch (\Throwable $e) {
                // Fall through to URL-based rendering.
            }
        }

        if (\Illuminate\Support\Str::startsWith($path, ['/storage/', 'storage/'])) {
            return asset(ltrim($path, '/'));
        }

        if (\Illuminate\Support\Str::startsWith($path, 'public/')) {
            return \Illuminate\Support\Facades\Storage::url($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    };

    $landsSignatureSrc = $resolveSignatureSrc($requestRecord->lands_section_signature ?: $requestRecord->director_signature);
    $directorCadastralSignatureSrc = $resolveSignatureSrc($requestRecord->director_cadastral_signature ?: $requestRecord->director_signature);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Request for Survey Report - {{ $serialNo !== '' ? $serialNo : 'LAND 12' }}</title>
    <style>
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        @media print {
            html, body {
                height: 100%;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden;
            }
            body {
                display: block !important;
                background: #fff !important;
            }
            @page {
                size: A4;
                margin: 0;
            }
            .no-print { display: none !important; }
            .a4-page {
                width: 210mm;
                height: 297mm;
                padding: 8mm !important;
                margin: 0 !important;
                border: none !important;
                box-shadow: none !important;
                overflow: hidden !important;
                break-inside: avoid-page !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }
            .section-box {
                padding: 9px !important;
                margin-bottom: 8px !important;
            }
            .signature-line {
                height: 38px !important;
            }
            .signature-line img {
                height: 33px !important;
            }
            .signature-footer-line {
                height: 52px !important;
                width: 72% !important;
            }
            .signature-footer-line img {
                height: 46px !important;
            }
            .a4-page .mt-8 {
                margin-top: 1rem !important;
            }
            .a4-page .mt-4 {
                margin-top: 0.4rem !important;
            }
            .a4-page .mb-6 {
                margin-bottom: 0.75rem !important;
            }
            .a4-page .mb-4 {
                margin-bottom: 0.45rem !important;
            }
            .a4-page .space-y-4 > :not([hidden]) ~ :not([hidden]) {
                margin-top: 0.7rem !important;
            }
            .director-cadastral-sign-row {
                margin-top: 0.95rem !important;
                margin-bottom: 0.25rem !important;
            }
            .commissioner-row {
                margin-top: 0.1rem !important;
            }
            .commissioner-sign-line {
                height: 50px !important;
            }
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .line-input {
            border-bottom: 1px solid #000;
            flex-grow: 1;
            height: 20px;
            margin-left: 4px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            flex-grow: 1;
            margin-left: 4px;
            height: 46px;
            position: relative;
            overflow: hidden;
        }

        .signature-line img {
            position: absolute;
            left: 50%;
            bottom: -1px;
            transform: translateX(-50%);
            height: 40px;
            max-width: 96%;
            object-fit: contain;
        }

        .signature-footer-line {
            width: 75%;
            margin: 0 auto;
            height: 64px;
            position: relative;
            border-bottom: 1px solid #000;
            overflow: hidden;
        }

        .signature-footer-line img {
            position: absolute;
            left: 50%;
            bottom: -1px;
            transform: translateX(-50%);
            height: 56px;
            max-width: 96%;
            object-fit: contain;
        }

        .director-cadastral-sign-row {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .commissioner-row {
            margin-top: 0.75rem;
        }

        .commissioner-sign-line {
            height: 52px;
            display: flex;
            align-items: flex-end;
        }

        .section-box {
            border: 2px solid #2e4a3d;
            padding: 12px;
            margin-bottom: 15px;
            position: relative;
        }

        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            font-weight: 800;
            color: rgba(220, 38, 38, 0.12);
            transform: rotate(-20deg);
            pointer-events: none;
            z-index: 1;
        }
    </style>
</head>
<body class="bg-gray-200 flex flex-col items-center p-4">

    <button onclick="window.print()" class="no-print mb-6 bg-[#2e4a3d] text-white px-10 py-3 rounded-full font-bold shadow-xl hover:bg-[#1f332a] transition-all">
        Print Land12 Form
    </button>

    <div class="a4-page bg-white w-[210mm] h-[297mm] p-12 border border-gray-300 shadow-2xl relative text-black box-border flex flex-col">
        @if(($watermark ?? 'ORIGINAL') !== 'ORIGINAL')
            <div class="watermark">{{ $watermark }}</div>
        @endif

        <div class="flex justify-between items-start mb-2 relative z-10">
            <div class="w-20">
                <img src="{{ qr_data_uri('KanoLandSurvey12', 100) }}" alt="QR Code" class="w-16 h-16">
            </div>
            <div class="text-center flex-1 pt-2">
                <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" class="w-14 mx-auto mb-2" alt="Nigeria Coat of Arms">
            </div>
            <div class="w-40 text-[11px]">
                <div class="border border-black p-1 flex justify-between mb-1">
                    <span class="font-bold">CODE:</span> <span>LAND 12</span>
                </div>
                <div class="border border-black p-1 flex justify-between">
                    <span class="font-bold">SERIAL NO:</span>
                    <span style="font-size:11px;font-weight:900;letter-spacing:0.12em;color:#1e293b;font-family:'Courier New',monospace;">{{ $serialNo !== '' ? $serialNo : '000000' }}</span>
                </div>
            </div>
        </div>

        <div class="text-center mb-6 relative z-10">
            <h1 class="text-[#2e4a3d] text-2xl font-bold tracking-tight uppercase">Ministry of Land and Physical Planning</h1>
            <p class="text-[9px] font-medium text-gray-700">No. 2 Dr. Bala Mohd Road, Nassarawa GRA, Kano PMB 3038 Kano-Nigeria.</p>
        </div>

        <div class="section-box relative z-10">
            <h2 class="text-[#2e4a3d] font-bold text-sm mb-3 underline">DIRECTOR-CADASTRAL</h2>

            <div class="flex justify-center mb-4">
                <span class="bg-red-500 text-white font-bold uppercase rounded shadow-sm italic"
                    style="font-size: 0.9rem; padding: 0.15rem 1.8rem; line-height: 1.2;">
                    Request for Survey Report
                </span>
            </div>

            <p class="text-[11px] mb-2 font-semibold">Please find enclosed hereby the following:</p>
            <ul class="text-[11px] ml-4 mb-4 space-y-1">
                <li>a. Completed application forms</li>
                <li>b. Four copies of site-plan and</li>
                <li>c. Local Government Conformation</li>
            </ul>

            <p class="text-[11px] font-semibold mb-4 italic">You are to record the application and prepare surveyCadastral report in due course</p>

            <div class="flex">
                <div class="w-1/2 space-y-3">
                    <div class="flex items-end text-[11px]">
                        <span>Date:</span>
                        <div class="line-input flex items-end">
                            <span class="text-[11px] font-semibold">{{ optional($requestRecord->lands_section_date ?? $requestRecord->director_cadastral_date)->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </div>
                <div class="w-1/2 space-y-3 pl-8">
                    <div class="flex items-end text-[11px]">
                        <span>Name:</span>
                        <div class="line-input flex items-end">
                            <span class="text-[11px] font-semibold">{{ $requestRecord->lands_section_name ?? $requestRecord->director_name ?? '' }}</span>
                        </div>
                    </div>
                    <div class="flex items-end text-[11px]">
                        <span>Rank:</span>
                        <div class="line-input flex items-end">
                            <span class="text-[11px] font-semibold">{{ $requestRecord->lands_section_rank ?? $requestRecord->director_rank ?? '' }}</span>
                        </div>
                    </div>
                    <div class="flex items-end text-[11px]">
                        <span>Signature:</span>
                        <div class="signature-line">
                            @if($landsSignatureSrc)
                                <img src="{{ $landsSignatureSrc }}" alt="Officer Signature">
                            @else
                                <span class="text-[11px] font-semibold">{{ $requestRecord->director_signature ?? '' }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right text-[10px] font-bold pr-2 pt-1">For: Director Land</div>
                </div>
            </div>
        </div>

        <div class="section-box relative z-10">
            <h2 class="text-[#2e4a3d] font-bold text-sm mb-4 underline">PERMANENT SECRETARY</h2>

            <div class="space-y-4 mb-6">
                <div class="flex items-end text-[11px]">
                    <span class="font-semibold">The above application has been allocated Right of Occupancy No:</span>
                    <div class="line-input flex items-end">
                        <span class="text-[11px] font-semibold">{{ $requestRecord->rofo_no ?? '' }}</span>
                    </div>
                </div>
                <div class="flex items-end text-[11px]">
                    <span class="font-semibold">Item G. No:</span>
                    <div class="line-input flex items-end">
                        <span class="text-[11px] font-semibold">{{ $requestRecord->item_g_no ?? '' }}</span>
                    </div>
                </div>
            </div>

            <div class="space-y-4 text-[11px]">
                <div class="flex gap-2">
                    <span class="font-bold">1.</span>
                    <p class="font-semibold">The ground is open and application is completed satisfactory with regards to survey requirements.</p>
                </div>

                <div class="flex gap-2 flex-col">
                    <div class="flex gap-2">
                        <span class="font-bold">2.</span>
                        <p class="font-semibold">I have to report that:</p>
                    </div>

                    <div class="ml-6 space-y-4">
                        <div class="flex flex-col">
                            <span class="mb-1">a. The correct description of the plot is</span>
                            <div class="border-b border-black min-h-[20px]">
                                <span class="text-[11px] font-semibold">{{ $requestRecord->plot_description ?? '' }}</span>
                            </div>
                            <div class="border-b border-black min-h-[20px]"></div>
                        </div>

                        <div class="flex items-end">
                            <span>b. Survey will be necessary</span>
                            <div class="line-input flex items-end">
                                <span class="text-[11px] font-semibold">{{ $requestRecord->survey_necessary ?? '' }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col">
                            <span>c. The applicant is require to Confirm/Note that the plot is demarcated by the following property beacons number:</span>
                            <div class="border-b border-black min-h-[20px] mt-2">
                                <span class="text-[11px] font-semibold">{{ $requestRecord->beacon_numbers ?? '' }}</span>
                            </div>
                            <div class="border-b border-black min-h-[20px]"></div>
                            <div class="border-b border-black min-h-[20px]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="director-cadastral-sign-row flex items-end">
                <div class="w-1/3 text-center flex flex-col justify-end">
                    <div class="w-3/4 mx-auto flex items-end" style="height: 3.5rem;">
                        <div class="border-b border-black w-full"></div>
                    </div>
                    <span class="mt-1 text-[10px] font-bold">Date</span>
                </div>
                <div class="w-2/3 text-center flex flex-col justify-end">
                    <div class="signature-footer-line">
                        @if($directorCadastralSignatureSrc)
                            <img src="{{ $directorCadastralSignatureSrc }}" alt="Director Cadastral Signature">
                        @endif
                    </div>
                    <span class="mt-1 text-[10px] font-bold uppercase">For: DIRECTOR CADASTRAL</span>
                </div>
            </div>

        </div>

        <div class="commissioner-row flex justify-between items-end relative z-10">
            <div class="w-1/3 text-center">
                <div class="commissioner-sign-line w-3/4 mx-auto">
                    <div class="border-b border-black w-full"></div>
                </div>
                <span class="text-[10px] font-bold">
                  Date
                </span>
            </div>
            <div class="w-1/2 text-center">
                <div class="commissioner-sign-line w-full">
                    <div class="border-b border-black w-full"></div>
                </div>
                <span class="text-[10px] font-bold uppercase">For: HONOURABLE COMMISSIONER</span>
            </div>
        </div>

    </div>

</body>
</html>
