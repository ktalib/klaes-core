<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Title Document Verification</title>
    <style>
        /* A4 Print Styles */
        @page {
            size: A4;
            margin: 0.75in;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background: #f3f4f6;
            display: flex;
            justify-content: center;
            padding: 40px 0;
        }
        
        .document-container {
            background: white;
            width: 100%;
            max-width: 600px;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border: 1px solid #d1d5db;
        }
        
        /* Print button - hide when printing */
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .no-print:hover {
            background: #2563eb;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact;
            }
            
            .document-container {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
                width: 100% !important;
            }
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        
        .logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }
        
        .file-number {
            font-size: 14px;
            color: #4b5563;
            font-weight: 600;
        }
        
        /* Title */
        .title {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            color: #059669;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .subtitle {
            text-align: center;
            color: #374151;
            font-size: 14px;
            margin-bottom: 24px;
        }
        
        /* Form Sections */
        .form-section {
            margin-top: 24px;
        }
        
        .form-section.divider {
            margin-top: 32px;
            border-top: 1px solid #d1d5db;
            padding-top: 24px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            display: block;
            color: #374151;
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 12pt;
        }
        
        .form-input {
    width: 100%;
    border: none;
    border-bottom: 1px solid #9ca3af;
    padding: 8px 0;
    font-size: 12pt;
    background: transparent;
    outline: none;
    min-height: 24px;
}

/* Disabled state */
.form-input:disabled {
    background-color: #f3f4f6; /* light gray background */
    color: #6b7280; /* gray text */
    cursor: not-allowed;
    opacity: 0.7;
}

        
        .form-input:focus {
            border-bottom-color: #059669;
        }
        
        /* Footer */
        .footer {
            margin-top: 32px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            font-weight: 600;
        }
        
        /* Print optimizations */
        @media print {
            .form-input {
                border-bottom: 1px solid #000 !important;
                -webkit-print-color-adjust: exact;
            }
            
            .title {
                color: #000 !important;
            }
            
            .form-label {
                color: #000 !important;
            }
            
            .subtitle {
                color: #000 !important;
            }
            
            .file-number {
                color: #000 !important;
            }
            
            .footer {
                color: #000 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button class="no-print" onclick="window.print()">🖨️ Print This Page</button>
    
    <div class="document-container">
        <!-- Header -->
        <div class="header">
            <img src="http://klas.com.ng/assets/logo/logo2.jpg" alt="KANGIS Logo" class="logo" />
            <div class="file-number">{{ $application->file_number ?? 'KNPG 00239' }}</div>
        </div>

        <h1 class="title">
            Title Document Verification
        </h1>
        <p class="subtitle">
            Verify the title document submitted for recertification, please
        </p>

        <!-- First Section -->
        <div class="form-section">
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label">Name:</label>
                    <input type="text"  class="form-input"  disabled/>
                </div>
                <div class="form-field">
                    <label class="form-label">Rank:</label>
                    <input type="text"  class="form-input"  disabled/>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label">Signature:</label>
                    <input type="text" class="form-input"  disabled/>
                </div>
                <div class="form-field">
                    <label class="form-label">Date:</label>
                    <input type="text"   class="form-input"  disabled/>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label">KANGIS Number:</label>
                    <input type="text" class="form-input"  disabled/>
                </div>
                <div class="form-field">
                    <label class="form-label">Land File Number:</label>
                    <input type="text"   class="form-input"  disabled/>
                </div>
            </div>
        </div>

        <!-- Second Section -->
        <div class="form-section divider">
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label">Name:</label>
                    <input type="text" class="form-input"  disabled/>
                </div>
                <div class="form-field">
                    <label class="form-label">Rank:</label>
                    <input type="text" class="form-input"  disabled/>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label">Sign:</label>
                    <input type="text" class="form-input"  disabled/>
                </div>
                <div class="form-field">
                    <label class="form-label">Date:</label>
                    <input type="text" class="form-input"  disabled/>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            SERVICES CENTER
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
