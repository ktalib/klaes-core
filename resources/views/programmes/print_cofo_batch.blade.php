<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $PageTitle ?? 'Batch Certificate of Occupancy' }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: "Times New Roman", serif;
            margin: 0;
            padding: 0;
            line-height: 1.1;
            font-size: 10pt;
            text-align: justify;
            width: 100%;
            box-sizing: border-box;
            background-color: white;
        }

        .certificate-page {
            width: 210mm;
            min-height: 297mm;
            position: relative;
            margin: 0 auto;
            padding: 45mm 20mm 40mm 20mm;
            box-sizing: border-box;
            page-break-after: always;
        }

        .certificate-page:last-child {
            page-break-after: auto;
        }

        .header-container {
            display: flex;
            justify-content: center;
            position: relative;
            margin-bottom: 3mm;
            padding-top: 0;
        }

        .header-content {
            text-align: center;
            flex: 1;
            max-width: 70%;
        }

        .header h1 {
            font-size: 14pt;
            margin: 0;
            text-transform: uppercase;
            font-weight: normal;
        }

        .file-info {
            text-align: center;
            margin: 1mm 0;
            font-size: 10pt;
        }

        .passport-section {
            position: absolute;
            right: -1mm;
            top: -18mm;
            width: 25mm;
        }

        .passport-slot {
            width: 23mm;
            height: 30mm;
            position: relative;
            background-color: rgb(255, 255, 255);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            border: 0.5px solid #eaeaea;
        }

        .main-content {
            margin-top: 2mm;
        }

        .certify-text {
            margin: 1mm 0;
            font-size: 10pt;
        }

        .holder-name {
            font-weight: bold;
            margin-left: 5mm;
        }

        .holder-address {
            margin-left: 5mm;
        }

        .terms {
            margin: 2mm 0;
            font-size: 9pt;
        }

        .terms p,
        .terms ol {
            margin: 0;
            padding: 0;
        }

        .terms ol {
            padding-left: 4mm;
        }

        .terms ol li {
            margin-bottom: 0;
        }

        .date-section {
            font-weight: bold;
            text-align: center;
            margin: 2mm 0;
            font-size: 9pt;
            width: 100%;
        }

        .signature-section {
            text-align: right;
            margin: 2mm 0 0 auto;
            width: fit-content;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 50mm;
            margin-left: auto;
            margin-top: 2mm;
        }

        .signature-name {
            margin: 0;
            padding: 1mm;
            font-size: 10pt;
        }

        .signature-title {
            margin: 0;
            padding: 0;
            font-style: italic;
            font-size: 9pt;
        }

        .controls {
            text-align: center;
            margin: 2mm 0;
            padding: 10px;
        }

        .print-btn {
            background-color: #004080;
            color: white;
            border: none;
            padding: 8px 16px;
            font-size: 11pt;
            cursor: pointer;
            margin-bottom: 5mm;
            border-radius: 4px;
        }

        .print-btn:hover {
            background-color: #003060;
        }

        @media print {
            .controls {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .certificate-page {
                height: 297mm;
                min-height: 297mm;
                padding: 45mm 20mm 40mm 20mm;
                margin: 0;
                box-shadow: none;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        @media screen {
            body {
                background: #f0f0f0;
                padding: 20px;
            }
            .certificate-page {
                background: white;
                margin-bottom: 20px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <!-- Print button (hidden when printing) -->
    <div class="controls">
        <button class="print-btn" onclick="window.print()">
            Print All Certificates ({{ $certificates->count() }})
        </button>
        <p style="font-size: 9pt; color: #666; margin-top: 5mm;">
            Note: For best printing results, use the "More settings" option in your browser's print dialog and disable headers and footers.
        </p>
    </div>

    @foreach($certificates as $cofo)
        @php
            $fileNumber = $cofo->file_no ?? 'N/A';
            $landUseValue = $cofo->land_use ?? null;
            $landUseUpper = $landUseValue ? strtoupper($landUseValue) : 'N/A';

            $unitDescription = null;
            if (!empty($cofo->unit_description)) {
                $unitDescription = trim($cofo->unit_description);
            } else {
                $unitParts = [];
                if (!empty($cofo->block_no)) {
                    $unitParts[] = 'BLOCK ' . \Illuminate\Support\Str::upper(trim((string) $cofo->block_no));
                }
                if (!empty($cofo->floor_no)) {
                    $unitParts[] = 'FLOOR ' . \Illuminate\Support\Str::upper(trim((string) $cofo->floor_no));
                }
                if (!empty($cofo->flat_no)) {
                    $unitParts[] = 'UNIT ' . \Illuminate\Support\Str::upper(trim((string) $cofo->flat_no));
                }
                if (!empty($cofo->plot_no)) {
                    $unitParts[] = 'PLOT ' . \Illuminate\Support\Str::upper(trim((string) $cofo->plot_no));
                }
                $unitDescription = !empty($unitParts) ? implode(', ', $unitParts) : null;
            }
            if (!$unitDescription && !empty($cofo->certificate_number)) {
                $unitDescription = 'Certificate No: ' . trim($cofo->certificate_number);
            }
            $unitDescription = $unitDescription ?: 'N/A';

            if ($unitDescription !== 'N/A') {
                $unitDescription = normalizeLocationText($unitDescription) ?: $unitDescription;
            }

            $holderName = !empty($cofo->holder_name) ? trim($cofo->holder_name) : 'N/A';
            $holderAddress = !empty($cofo->holder_address) ? trim($cofo->holder_address) : 'N/A';
            if ($holderAddress !== 'N/A') {
                $holderAddress = normalizeLocationText($holderAddress) ?: $holderAddress;
            }

            $totalTermRaw = $cofo->total_term ?? null;
            if (!empty($totalTermRaw) || $totalTermRaw === 0 || $totalTermRaw === '0') {
                $termNormalized = trim((string) $totalTermRaw);
                $totalTermLabel = stripos($termNormalized, 'year') !== false ? $termNormalized : $termNormalized . ' years';
            } else {
                $totalTermLabel = 'N/A';
            }

            $startDateFormatted = !empty($cofo->start_date)
                ? \Carbon\Carbon::parse($cofo->start_date)->format('F d, Y')
                : 'N/A';

            $issuedDateInstance = null;
            if (!empty($cofo->issued_date)) {
                $issuedDateInstance = \Carbon\Carbon::parse($cofo->issued_date);
            } elseif (!empty($cofo->created_at)) {
                $issuedDateInstance = \Carbon\Carbon::parse($cofo->created_at);
            }
            $issuedDay = $issuedDateInstance ? $issuedDateInstance->format('jS') : 'N/A';
            $issuedMonth = $issuedDateInstance ? $issuedDateInstance->format('F') : 'N/A';
            $issuedYear = $issuedDateInstance ? $issuedDateInstance->format('Y') : 'N/A';
            $issuedLine = 'DATED This ' . $issuedDay . ' day of ' . $issuedMonth . ', ' . $issuedYear;
            if ($issuedDay === 'N/A' && $issuedMonth === 'N/A' && $issuedYear === 'N/A') {
                $issuedLine = 'DATED This N/A';
            }

            $signedByLabel = !empty($cofo->signed_by) ? trim($cofo->signed_by) : '';
            $signedTitle = !empty($cofo->signed_title) ? trim($cofo->signed_title) : 'N/A';
            $landUseClause = $landUseUpper;

            $passportPath = null;
            if (!empty($cofo->passport)) {
                $passportPath = $cofo->passport;
            } elseif (!empty($cofo->multiple_owners_passport)) {
                $multiPassports = json_decode($cofo->multiple_owners_passport, true);
                if (is_array($multiPassports) && count($multiPassports) > 0) {
                    $passportPath = $multiPassports[0];
                }
            }
            $passportUrl = $passportPath ? asset('storage/' . $passportPath) : asset('placeholder-user.jpg');
        @endphp

        <div class="certificate-page">
            <div class="header-container" style="margin-bottom: 0.1mm;">
                <div class="header-content">
                    <br>
                    <div class="header">
                        <h1>SECTIONAL TITLING CERTIFICATE OF OCCUPANCY</h1>
                    </div>
                    <div class="file-info">
                        New File No: <span>{{ $fileNumber }}</span><br>
                        <span>{{ $landUseUpper }}</span><br>
                        <span>{{ $unitDescription }}</span>
                    </div>
                </div>

                <div class="passport-section">
                    <div class="passport-slot" style="background-image: url('{{ $passportUrl }}');"></div>
                </div>
            </div>

            <div class="main-content">
                <div class="certify-text">
                    This is to certify that:-
                    <span class="holder-name">{{ $holderName }}</span><br>
                    Whose address is
                    <span class="holder-address">{{ $holderAddress }}</span>
                </div>

                <div class="terms">
                    <p>
                        (Herein after called the holder, which terms shall include any
                        person/persons in title) is hereby granted a right of occupancy for
                        in and over the land described in the schedule, and more
                        particularly in the plan printed hereto for a term of
                        <strong>{{ $totalTermLabel }}</strong> commencing from
                        <strong>{{ $startDateFormatted }}</strong> according to the true
                        intent and meaning of the Kano State Sectional and Systematic Land
                        Titling Registration Law, 2024 and subject to the provisions thereof
                        and to the following special terms and conditions:
                    </p>

                    <ol>
                        <li>
                            To pay in advance without demand to the Government of the State
                            (herein after referred to as the Governor) or any other officer or
                            agency appointed by the Governor of the State:
                            <ol type="a">
                                <li>
                                    Whatever is the computed revised and the current ground rent
                                    from the first day of January of each year or
                                </li>
                                <li>
                                    Such revised ground rent as the Governor may from time to time
                                    prescribe.
                                </li>
                                <li>
                                    Such penal rent as the Governor may from time to time impose.
                                </li>
                            </ol>
                        </li>
                        <li>
                            To pay and discharge all rates (including utilities), assessment
                            and impositions, whatsoever which shall at any time be charged or
                            imposed on the said land or any building thereon, or upon the
                            occupier or occupiers thereof.
                        </li>
                        <li>
                            To pay forthwith to the Kano State Government through Ministry of
                            Land and Physical Planning or such other body or agency appointed
                            by the Governor (if not sooner paid) all survey fees and other
                            charges due in respect of the preparation, registration and
                            issuance of this certificate.
                        </li>
                        <li>
                            Within two years from the day of the commencement of the right of
                            occupancy to erect and complete on the said land building(s) or
                            other works specified in the related plans approved or to be
                            approved by the Kano State Government or any other agency
                            empowered to do so. The approval may be revoked after two (2)
                            years.
                        </li>
                        <li>
                            To maintain in good and substantial repair to the satisfaction of
                            Kano State Government or any other officer appointed by the
                            Governor, all buildings on the said land and appurtenances
                            thereof, and to do other works, properly maintained in clean and
                            good sanitary condition around all of the land and surroundings of
                            the buildings.
                        </li>
                        <li>
                            Upon the expiration of the said term to deliver up to the Governor
                            in good and tenable state to the satisfaction of the Kano State
                            Government or any other agency appointed by the State Governor,
                            the said land and building(s) thereon.
                        </li>
                        <li>
                            Not to erect build or permit to be erected or built on the land,
                            buildings other than those permitted to be erected by virtue of
                            this certificate of occupancy nor to make or permit to be made any
                            addition or alteration to the said building(s) already erected on
                            the land except in accordance with the plans and specifications
                            approved by the Governor and or any officer authorized by him on
                            his behalf.
                        </li>
                        <li>
                            The Governor or any public officer duly authorized by the Governor
                            on his behalf, shall have the power to enter upon and inspect the
                            land comprised in any statutory right of occupancy or any
                            improvements effected thereon, at any reasonable hour during the
                            day and the occupier shall permit and give free access to the
                            Governor or any such officer to enter and so inspect.
                        </li>
                        <li>
                            Not to alienate the right of occupancy hereby granted or any part
                            thereof by sale, assignment, mortgage, transfer of possessions,
                            sub-lease or bequest, or otherwise howsoever without the prior
                            consent of the Governor.
                        </li>
                        <li>
                            To use the said land only for
                            <strong>{{ $landUseClause }}</strong> purpose.
                        </li>
                        <li>
                            Not to contravene any of the provisions of the Kano State
                            Sectional and Systematic Land Titling Registration Law, 2024 and
                            to conform and comply with all rules and regulations laid down
                            from time to time by Kano State Government.
                        </li>
                        <li>
                            To become joint owner of the common property of the Sectional
                            Titling Land and actively participate in all quotas that benefit
                            or burden sections.
                        </li>
                        <li>
                            To exclusively use certain parts and share undivided sections of
                            the common property e.g, Garage, Garden, Parking space Storeroom
                            among others.
                        </li>
                        <li>
                            For the purpose of the rent to be paid under this certificate of
                            occupancy:
                            <ol type="a">
                                <li>
                                    The term of the Right of Occupancy shall be divided into
                                    periods of five years and Governor may, at the expiration of
                                    each period of five years, revise the rent and fix the sum
                                    which shall be payable for the next period of five years. If
                                    the Governor shall so revise the rent, he shall cause a notice
                                    to be sent to the holder/holders and the rent so fixed or
                                    revised shall commenced to be payable one calendar month from
                                    the date of the receipt of such notice.
                                </li>
                                <li>
                                    If any rent for the time being payable in respect of the land
                                    or any part hereof shall be in arrears for the period of three
                                    months whether same shall or shall not have been legally
                                    demanded of if the holder/holders become bankrupt or make a
                                    composition with creditors or enter into liquidation, whether
                                    compulsory or voluntarily or if there shall be any breach or
                                    non-observance of any of the occupier's covenants or
                                    agreements herein contained. Then and in any of the said cases
                                    it shall be lawful for the Governor at any given time
                                    thereafter to hold and enjoy the same as if the right of
                                    occupancy had not been granted but without prejudice to Right
                                    of Action or remedy of Governor for any antecedent breach of
                                    covenant by the holder/holders.
                                </li>
                            </ol>
                        </li>
                    </ol>
                </div>

                <!-- Date section -->
                <div class="date-section">
                    {{ $issuedLine }}<br>
                    <br><em>Given under my hand the day and year above written</em>
                </div>

                <!-- Signature section -->
                <div class="signature-section">
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $signedByLabel }}</div>
                    <div class="signature-title">{{ $signedTitle }}</div>
                    <div class="signature-title">Kano State, Nigeria</div>
                </div>
            </div>
        </div>
    @endforeach

    <script>
        // Auto-print if opened via Print Manager
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            // Small delay to ensure rendering is complete
            setTimeout(() => window.print(), 500);
        }
    </script>
</body>
</html>
