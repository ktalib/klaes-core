@extends('layouts.app')
@section('page-title')
    {{ __('EDMS Workflow') }}
@endsection

@include('sectionaltitling.partials.assets.css')

@section('content')
<style>
    /* Modern EDMS Workflow Design - Compact & Clean */
    .main-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        min-height: 100vh;
    }
    
    .workflow-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 1.5rem;
        padding: 2.5rem;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.25);
        position: relative;
        overflow: hidden;
    }
    
    .workflow-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="90" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        pointer-events: none;
    }
    
    .workflow-title {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }
    
    .workflow-subtitle {
        font-size: 1rem;
        opacity: 0.95;
        position: relative;
        z-index: 1;
    }
    
    /* Compact Application Summary */
    .application-card {
        background: white;
        border-radius: 1.25rem;
        padding: 1.75rem;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .application-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #48bb78, #38a169, #2f855a);
    }
    
    .application-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .application-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .application-icon {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
        padding: 0.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 3px 10px rgba(72, 187, 120, 0.3);
    }
    
    .application-id {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 1.5rem;
        font-weight: 600;
        font-size: 0.875rem;
        box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
    }
    
    .application-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.25rem;
    }
    
    .application-field {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 1rem;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .application-field:hover {
        border-color: #cbd5e0;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    .field-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.375rem;
    }
    
    .field-value {
        font-size: 1rem;
        font-weight: 600;
        color: #2d3748;
        line-height: 1.3;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.375rem 0.75rem;
        border-radius: 1.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
        border: 1px solid #93c5fd;
    }
    
    /* Compact Workflow Steps */
    .workflow-steps-card {
        background: white;
        border-radius: 1.25rem;
        padding: 2rem;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .workflow-steps-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #f093fb, #f5576c, #4facfe);
    }
    
    .steps-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .steps-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 0.375rem;
    }
    
    .steps-subtitle {
        font-size: 0.875rem;
        color: #718096;
    }
    
    .workflow-timeline {
        position: relative;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
        align-items: stretch;
    }
    
    .timeline-connector {
        position: absolute;
        top: 2.5rem;
        left: 8.33%;
        right: 8.33%;
        height: 2px;
        background: linear-gradient(90deg, #e2e8f0 0%, #e2e8f0 100%);
        border-radius: 2px;
        z-index: 1;
    }
    
    .timeline-progress {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        background: linear-gradient(90deg, #48bb78, #38a169);
        border-radius: 2px;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .workflow-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 2;
        width: 100%;
    }
    
    .step-circle {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.65rem;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid transparent;
        position: relative;
        flex-shrink: 0;
    }
    
    .step-circle.completed {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
        box-shadow: 0 8px 25px rgba(72, 187, 120, 0.4);
        transform: scale(1.05);
    }
    
    .step-circle.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        animation: pulse 2s infinite;
    }
    
    .step-circle.pending {
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        color: #9ca3af;
        border-color: #d1d5db;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }
    
    .step-content {
        text-align: left;
        padding: 1rem;
        background: white;
        border-radius: 0.875rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #f1f5f9;
        transition: all 0.3s ease;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        position: relative;
        overflow: hidden;
    }
    
    .step-content::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #e2e8f0, #cbd5e0);
        transition: background 0.3s ease;
    }
    
    .step-content.completed {
        border-color: #48bb78;
        background: linear-gradient(135deg, #f0fff4 0%, #dcfce7 100%);
        transform: scale(1.02);
    }
    
    .step-content.completed::before {
        background: linear-gradient(90deg, #48bb78, #38a169);
    }
    
    .step-content.active {
        border-color: #667eea;
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        transform: scale(1.02);
    }
    
    .step-content.active::before {
        background: linear-gradient(90deg, #667eea, #764ba2);
    }
    
    /* ST Blind Scan Card Special Styling */
    .st-blind-scan-card .step-content {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        color: white !important;
        border: 1px solid rgba(255,255,255,0.2) !important;
        box-shadow: 0 8px 30px rgba(16, 185, 129, 0.3) !important;
    }
    
    .st-blind-scan-card .step-content::before {
        background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1)) !important;
    }
    
    .st-blind-scan-card .step-title {
        color: white !important;
    }
    
    .st-blind-scan-card .step-description {
        color: rgba(255,255,255,0.9) !important;
    }
    
    .st-blind-scan-card .step-status {
        background: rgba(255,255,255,0.15) !important;
        color: white !important;
        border: 1px solid rgba(255,255,255,0.3) !important;
        backdrop-filter: blur(10px);
    }
    
    .st-blind-scan-card .btn {
        backdrop-filter: blur(10px);
    }
    
    .st-blind-scan-card .step-actions .btn:first-child {
        background: rgba(255,255,255,0.2) !important;
        color: white !important;
        border: 1px solid rgba(255,255,255,0.3) !important;
    }
    
    .st-blind-scan-card .step-actions .btn:first-child:hover {
        background: rgba(255,255,255,0.3) !important;
        box-shadow: 0 6px 20px rgba(255,255,255,0.2) !important;
    }
    
    .st-blind-scan-card .step-actions .btn:last-child {
        background: white !important;
        color: #10b981 !important;
        border: 1px solid white !important;
    }
    
    .st-blind-scan-card .step-actions .btn:last-child:hover {
        background: rgba(255,255,255,0.95) !important;
        color: #059669 !important;
        box-shadow: 0 6px 20px rgba(255,255,255,0.4) !important;
    }
    
    .st-blind-scan-card .mt-3 {
        background: rgba(255,255,255,0.1) !important;
        border: 1px solid rgba(255,255,255,0.2) !important;
        padding: 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.7rem;
        backdrop-filter: blur(10px);
    }
    
    .step-content:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
    }
    
    .step-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
    }
    
    .step-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: #1f2937;
        line-height: 1.3;
        flex: 1;
    }
    
    .step-description {
        font-size: 0.75rem;
        color: #6b7280;
        line-height: 1.4;
        margin: 0;
    }
    
    .step-status {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.6rem;
        border-radius: 0.875rem;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        flex-shrink: 0;
    }
    
    .step-status.completed {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border: 1px solid #6ee7b7;
    }
    
    .step-status.active {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
        border: 1px solid #f59e0b;
    }
    
    .step-status.pending {
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        color: #64748b;
        border: 1px solid #cbd5e1;
    }
    
    .step-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .step-actions .btn {
        flex: 1;
        min-width: 100px;
    }
    
    .btn {
        padding: 0.45rem 0.85rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        text-decoration: none;
        border: none;
        cursor: pointer;
        font-size: 0.7rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        position: relative;
        overflow: hidden;
    }
    
    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s ease;
    }
    
    .btn:hover::before {
        left: 100%;
    }
    
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .btn-success:hover {
        background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        box-shadow: 0 6px 20px rgba(72, 187, 120, 0.4);
    }
    
    .btn-outline {
        background: white;
        border: 2px solid #e2e8f0;
        color: #4a5568;
    }
    
    .btn-outline:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
        color: #2d3748;
    }
    
    .btn-disabled {
        background: #f1f5f9;
        color: #9ca3af;
        cursor: not-allowed;
        opacity: 0.6;
        border: 1px solid #e2e8f0;
    }
    
    .btn-disabled:hover {
        transform: none;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    }
    
    .btn-disabled::before {
        display: none;
    }
    
    /* Compact Progress Summary */
    .progress-summary {
        background: white;
        border-radius: 1.25rem;
        padding: 2rem;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .progress-summary::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #4facfe, #00f2fe);
    }
    
    .progress-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
    }
    
    .progress-item {
        text-align: center;
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .progress-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e0;
    }
    
    .progress-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.375rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .progress-value.completed {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .progress-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.25rem;
    }
    
    .progress-date {
        font-size: 0.625rem;
        color: #718096;
    }
    
    /* Compact Action Section */
    .action-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }
    
    .completion-badge {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        padding: 0.75rem 1.5rem;
        border-radius: 1.5rem;
        font-weight: 700;
        font-size: 1rem;
        border: 1px solid #6ee7b7;
        box-shadow: 0 6px 20px rgba(72, 187, 120, 0.25);
    }
    
    .btn-back {
        background: white;
        border: 1px solid #e2e8f0;
        color: #4a5568;
        border-radius: 0.75rem;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        font-size: 0.875rem;
    }
    
    .btn-back:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .workflow-timeline {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.875rem;
        }
        
        .timeline-connector {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .main-container {
            padding: 1rem;
        }
        
        .workflow-header {
            padding: 2rem;
        }
        
        .workflow-title {
            font-size: 1.75rem;
        }
        
        .workflow-timeline {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        
        .timeline-connector {
            display: none;
        }
        
        .workflow-step {
            max-width: 100%;
        }
        
        .step-content {
            padding: 0.875rem;
            gap: 0.5rem;
        }
        
        .step-circle {
            width: 2.25rem;
            height: 2.25rem;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .application-grid {
            grid-template-columns: 1fr;
        }
        
        .action-section {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
        
        .progress-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .btn {
            padding: 0.4rem 0.75rem;
            font-size: 0.65rem;
        }
        
        .step-title {
            font-size: 0.8rem;
        }
        
        .step-description {
            font-size: 0.7rem;
        }
    }
    
    @media (max-width: 480px) {
        .step-content {
            padding: 0.75rem;
            gap: 0.45rem;
        }
        
        .step-title {
            font-size: 0.75rem;
        }
        
        .step-description {
            font-size: 0.65rem;
        }
        
        .step-status {
            padding: 0.2rem 0.5rem;
            font-size: 0.6rem;
        }
    }
    
    /* Loading Animation */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    .spinner {
        display: inline-block;
        width: 0.875rem;
        height: 0.875rem;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<!-- Main Content -->
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Dashboard Content -->
    <div class="main-container">
        <!-- Workflow Header -->
        <div class="workflow-header">
            <h1 class="workflow-title">EDMS Workflow Dashboard</h1>
            <p class="workflow-subtitle">Electronic Document Management System - Streamlined digital processing for your application</p>
        </div>

        <!-- Application Summary Card -->
        <div class="application-card">
            <div class="application-header">
                <div class="application-title">
                    <div class="application-icon">
                        <i data-lucide="file-text" style="width: 1.25rem; height: 1.25rem;"></i>
                    </div>
                    @if(isset($type) && $type === 'sub')
                        Unit Application Summary
                    @elseif(isset($applicationType) && $applicationType === 'recertification')
                        Recertification Application Summary
                    @else
                        Application Summary
                    @endif
                </div>
                <div class="application-id">
                    @if(isset($type) && $type === 'sub')
                        Unit ID: {{ $application->id }}
                    @elseif(isset($applicationType) && $applicationType === 'recertification')
                        Recert ID: {{ $application->id }}
                    @else
                        ID: {{ $application->applicationID ?? $application->id }}
                    @endif
                </div>
            </div>
            
            @if(isset($applicationType) && $applicationType === 'recertification')
                <!-- Recertification application fields -->
                <div class="application-grid">
                    <div class="application-field">
                        <div class="field-label">File Number</div>
                        <div class="field-value">{{ $application->file_number ?? 'Not Assigned' }}</div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Applicant Name</div>
                        <div class="field-value">
                            @if($application->applicant_type === 'Corporate')
                                {{ $application->organisation_name ?? 'Corporate Applicant' }}
                            @else
                                {{ trim(($application->surname ?? '') . ' ' . ($application->first_name ?? '')) ?: 'Individual Applicant' }}
                            @endif
                        </div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Plot Details</div>
                        <div class="field-value">
                            @php
                                $plotDetails = '';
                                if ($application->plot_number) {
                                    $plotDetails .= 'Plot: ' . $application->plot_number;
                                }
                                if ($application->layout_district) {
                                    $plotDetails .= ($plotDetails ? ', ' : '') . $application->layout_district;
                                }
                                if ($application->plot_size) {
                                    $plotDetails .= ($plotDetails ? ', ' : '') . 'Size: ' . $application->plot_size;
                                }
                                if (empty($plotDetails)) {
                                    $plotDetails = 'N/A';
                                }
                            @endphp
                            {{ $plotDetails }}
                        </div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">LGA</div>
                        <div class="field-value">{{ $application->lga_name ?? 'Not Specified' }}</div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Application Status</div>
                        <div class="field-value">
                            <span class="status-badge">
                                <i data-lucide="info" style="width: 0.875rem; height: 0.875rem; margin-right: 0.375rem;"></i>
                                {{ $application->application_status ?? 'Pending Review' }}
                            </span>
                        </div>
                    </div>
                </div>
            @elseif(isset($type) && $type === 'sub')
                <!-- Sub-application fields -->
                <div class="application-grid">
                    <div class="application-field">
                        <div class="field-label">Unit Owner</div>
                        <div class="field-value">
                            @if($application->applicant_type === 'individual')
                                {{ trim(($application->first_name ?? '') . ' ' . ($application->middle_name ?? '') . ' ' . ($application->surname ?? '')) ?: 'Individual Applicant' }}
                            @elseif($application->applicant_type === 'corporate')
                                {{ $application->corporate_name ?? 'Corporate Applicant' }}
                            @elseif($application->applicant_type === 'multiple')
                                @php
                                    $names = json_decode($application->multiple_owners_names ?? '[]', true);
                                @endphp
                                {{ is_array($names) && count($names) > 0 ? $names[0] . ' et al.' : 'Multiple Owners' }}
                            @else
                                {{ 'Unknown Applicant' }}
                            @endif
                        </div>
                    </div>
                    
                    @if(isset($motherApplication))
                        <div class="application-field">
                            <div class="field-label">Primary File Number</div>
                            <div class="field-value">{{ $motherApplication->fileno ?? 'Not Assigned' }}</div>
                        </div>
                        
                        <div class="application-field">
                            <div class="field-label">Property Description</div>
                            <div class="field-value">
                                @php
                                $addressParts = [];
                                if (isset($motherApplication) && !empty($motherApplication->property_plot_no)) $addressParts[] = $motherApplication->property_plot_no;
                                if (isset($motherApplication) && !empty($motherApplication->property_street_name)) $addressParts[] = $motherApplication->property_street_name;
                                if (isset($motherApplication) && !empty($motherApplication->property_district)) $addressParts[] = $motherApplication->property_district;
                                if (isset($motherApplication) && !empty($motherApplication->property_lga)) $addressParts[] = $motherApplication->property_lga;
                                if (isset($motherApplication) && !empty($motherApplication->property_state)) $addressParts[] = $motherApplication->property_state;
                                $propertyDescription = !empty($addressParts) ? implode(', ', array_filter($addressParts)) : 'Property description not available';
                                @endphp
                                {{ $propertyDescription }}
                            </div>
                        </div>
                    @endif
                         
                    <div class="application-field">
                        <div class="field-label">Unit Number</div>
                        <div class="field-value">{{ $application->unit_number ?? 'Not Assigned' }}</div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Block Number</div>
                        <div class="field-value">{{ $application->block_number ?? 'Not Assigned' }}</div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Floor Number</div>
                        <div class="field-value">{{ $application->floor_number ?? 'Not Assigned' }}</div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Application Status</div>
                        <div class="field-value">
                            <span class="status-badge">
                                <i data-lucide="info" style="width: 0.875rem; height: 0.875rem; margin-right: 0.375rem;"></i>
                                {{ $application->application_status ?? 'Pending Review' }}
                            </span>
                        </div>
                    </div>
                </div>
            @else
                <!-- Primary application fields -->
                <div class="application-grid">
                    <div class="application-field">
                        <div class="field-label">File Number</div>
                        <div class="field-value">{{ $application->np_fileno ?? $application->fileno ?? 'Not Assigned' }}</div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Applicant Name</div>
                        <div class="field-value">
                            @if(isset($application->applicant_type) && $application->applicant_type === 'individual')
                                {{ trim(($application->first_name ?? '') . ' ' . ($application->middle_name ?? '') . ' ' . ($application->surname ?? '')) ?: 'Individual Applicant' }}
                            @elseif(isset($application->applicant_type) && $application->applicant_type === 'corporate')
                                {{ $application->corporate_name ?? 'Corporate Applicant' }}
                            @elseif(isset($application->applicant_type) && $application->applicant_type === 'multiple')
                                @php
                                    $names = json_decode($application->multiple_owners_names ?? '[]', true);
                                @endphp
                                {{ is_array($names) && count($names) > 0 ? $names[0] . ' et al.' : 'Multiple Owners' }}
                            @else
                                {{ 'Unknown Applicant' }}
                            @endif
                        </div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Land Use Type</div>
                        <div class="field-value">{{ $application->land_use ?? 'Not Specified' }}</div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Property Description</div>
                        <div class="field-value">
                            @php
                                $addressParts = [];
                                if (isset($application) && !empty($application->property_plot_no)) $addressParts[] = $application->property_plot_no;
                                if (isset($application) && !empty($application->property_street_name)) $addressParts[] = $application->property_street_name;
                                if (isset($application) && !empty($application->property_district)) $addressParts[] = $application->property_district;
                                if (isset($application) && !empty($application->property_lga)) $addressParts[] = $application->property_lga;
                                if (isset($application) && !empty($application->property_state)) $addressParts[] = $application->property_state;
                                $propertyDescription = !empty($addressParts) ? implode(', ', array_filter($addressParts)) : 'Property description not available';
                            @endphp
                            {{ $propertyDescription }}
                        </div>
                    </div>
                    
                    <div class="application-field">
                        <div class="field-label">Application Status</div>
                        <div class="field-value">
                            <span class="status-badge">
                                <i data-lucide="info" style="width: 0.875rem; height: 0.875rem; margin-right: 0.375rem;"></i>
                                {{ $application->application_status ?? 'Pending Review' }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- EDMS Workflow Steps -->
        <div class="workflow-steps-card">
            <div class="steps-header">
                <h2 class="steps-title">EDMS Processing Pipeline</h2>
                <p class="steps-subtitle">Follow the three-step process to digitize and organize your application documents</p>
            </div>
            
            @php
                $hasFileIndexing = $fileIndexing !== null;
                $hasScanning = false;
                $hasPageTyping = false;
                $scanningCount = 0;
                $pageTypingCount = 0;
                
                if ($hasFileIndexing) {
                    try {
                        $hasScanning = $fileIndexing->scannings && $fileIndexing->scannings->count() > 0;
                        $scanningCount = $hasScanning ? $fileIndexing->scannings->count() : 0;
                        $hasPageTyping = $fileIndexing->pagetypings && $fileIndexing->pagetypings->count() > 0;
                        $pageTypingCount = $hasPageTyping ? $fileIndexing->pagetypings->count() : 0;
                    } catch (Exception $e) {
                        $hasScanning = false;
                        $hasPageTyping = false;
                        $scanningCount = 0;
                        $pageTypingCount = 0;
                    }
                }
                
                $currentStep = 1;
                $progressPercentage = 0;
                
                if ($hasPageTyping) {
                    $currentStep = 4; // Completed
                    $progressPercentage = 100;
                } elseif ($hasScanning) {
                    $currentStep = 3; // Page Typing
                    $progressPercentage = 66;
                } elseif ($hasFileIndexing) {
                    $currentStep = 2; // Scanning
                    $progressPercentage = 33;
                }
            @endphp

            <div class="workflow-timeline">
                <div class="timeline-connector">
                    <div class="timeline-progress" style="width: {{ $progressPercentage }}%"></div>
                </div>

                <!-- Step 1: File Indexing -->
                <div class="workflow-step">
                    <div class="step-circle {{ $hasFileIndexing ? 'completed' : ($currentStep == 1 ? 'active' : 'pending') }}">
                        @if($hasFileIndexing)
                            <i data-lucide="check" style="width: 1.25rem; height: 1.25rem;"></i>
                        @else
                            <span>1</span>
                        @endif
                    </div>
                    
                    <div class="step-content {{ $hasFileIndexing ? 'completed' : ($currentStep == 1 ? 'active' : '') }}">
                        <div class="step-header">
                            <h3 class="step-title">File Indexing</h3>
                            @if($hasFileIndexing)
                                <div class="step-status completed">
                                    <i data-lucide="check-circle" style="width: 0.7rem; height: 0.7rem;"></i>
                                    Done
                                </div>
                            @else
                                <div class="step-status {{ $currentStep == 1 ? 'active' : 'pending' }}">
                                    <i data-lucide="clock" style="width: 0.7rem; height: 0.7rem;"></i>
                                    {{ $currentStep == 1 ? 'Ready' : 'Pending' }}
                                </div>
                            @endif
                        </div>
                        
                        <p class="step-description">Create digital index with metadata for document organization</p>
                        
                        <div class="step-actions">
                            @if($hasFileIndexing)
                                <a href="{{ route('edms.fileindexing', $fileIndexing->id) }}" class="btn btn-outline">
                                    <i data-lucide="eye" style="width: 0.8rem; height: 0.8rem;"></i>
                                    View
                                </a>
                            @else
                                @if(isset($type) && $type === 'sub')
                                    <a href="{{ route('edms.create-file-indexing', [$application->id, 'sub']) }}" class="btn btn-primary">
                                        <i data-lucide="folder-plus" style="width: 0.8rem; height: 0.8rem;"></i>
                                        Start
                                    </a>
                                @elseif(isset($applicationType) && $applicationType === 'recertification')
                                    <a href="{{ route('edms.recertification.create-file-indexing', $application->id) }}" class="btn btn-primary">
                                        <i data-lucide="folder-plus" style="width: 0.8rem; height: 0.8rem;"></i>
                                        Start
                                    </a>
                                @else
                                    <a href="{{ route('edms.create-file-indexing', $application->id) }}" class="btn btn-primary">
                                        <i data-lucide="folder-plus" style="width: 0.8rem; height: 0.8rem;"></i>
                                        Start
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <!-- ST Blind Scan Card -->
                <div class="workflow-step st-blind-scan-card">
                    <div class="step-circle" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: 2px solid rgba(255,255,255,0.3); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                        <i data-lucide="building" style="width: 1.25rem; height: 1.25rem;"></i>
                    </div>
                    
                    <div class="step-content" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 4px 16px rgba(16, 185, 129, 0.25);">
                        <div class="step-header">
                            <h3 class="step-title" style="color: white;">ST Blind Scan</h3>
                            <div class="step-status" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3);">
                                <i data-lucide="scan-line" style="width: 0.7rem; height: 0.7rem;"></i>
                                Ready
                            </div>
                        </div>
                        
                        <p class="step-description" style="color: rgba(255,255,255,0.9);">Process ST documents through blind scanning workflow</p>
                        
                        <div class="step-actions">
                            <a href="/blind-scanning?url=st_edms&app_id={{ $application->id }}" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); backdrop-filter: blur(10px);">
                                <i data-lucide="scan-line" style="width: 0.8rem; height: 0.8rem;"></i>
                                Launch
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Document Scanning -->
                <div class="workflow-step">
                    <div class="step-circle {{ $hasScanning ? 'completed' : ($currentStep == 2 ? 'active' : 'pending') }}">
                        @if($hasScanning)
                            <i data-lucide="check" style="width: 1.25rem; height: 1.25rem;"></i>
                        @else
                            <span>2</span>
                        @endif
                    </div>
                    
                    <div class="step-content {{ $hasScanning ? 'completed' : ($currentStep == 2 ? 'active' : '') }}">
                        <div class="step-header">
                            <h3 class="step-title">Scanning</h3>
                            @if($hasScanning)
                                <div class="step-status completed">
                                    <i data-lucide="check-circle" style="width: 0.7rem; height: 0.7rem;"></i>
                                    {{ $scanningCount }} Docs
                                </div>
                            @elseif($hasFileIndexing)
                                <div class="step-status {{ $currentStep == 2 ? 'active' : 'pending' }}">
                                    <i data-lucide="upload" style="width: 0.7rem; height: 0.7rem;"></i>
                                    {{ $currentStep == 2 ? 'Ready' : 'Waiting' }}
                                </div>
                            @else
                                <div class="step-status pending">
                                    <i data-lucide="lock" style="width: 0.7rem; height: 0.7rem;"></i>
                                    Locked
                                </div>
                            @endif
                        </div>
                        
                        <p class="step-description">Upload scanned documents in PDF or image format</p>
                        
                        <div class="step-actions">
                            @if($hasScanning)
                                <a href="{{ route('edms.scanning', $fileIndexing->id) }}" class="btn btn-outline">
                                    <i data-lucide="eye" style="width: 0.8rem; height: 0.8rem;"></i>
                                    View
                                </a>
                                <a href="{{ route('edms.scanning', $fileIndexing->id) }}" class="btn btn-primary">
                                    <i data-lucide="upload" style="width: 0.8rem; height: 0.8rem;"></i>
                                   Upload More
                                </a>
                            @elseif($hasFileIndexing)
                                <a href="{{ route('edms.scanning', $fileIndexing->id) }}" class="btn btn-primary">
                                    <i data-lucide="upload" style="width: 0.8rem; height: 0.8rem;"></i>
                                    Upload
                                </a>
                            @else
                                @if(request()->get('url') !== 'more')
                                    <button class="btn btn-disabled" disabled>
                                        <i data-lucide="upload" style="width: 0.8rem; height: 0.8rem;"></i>
                                        Upload
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Step 3: Page Classification -->
                <div class="workflow-step">
                    <div class="step-circle {{ $hasPageTyping ? 'completed' : ($currentStep == 3 ? 'active' : 'pending') }}">
                        @if($hasPageTyping)
                            <i data-lucide="check" style="width: 1.25rem; height: 1.25rem;"></i>
                        @else
                            <span>3</span>
                        @endif
                    </div>
                    
                    <div class="step-content {{ $hasPageTyping ? 'completed' : ($currentStep == 3 ? 'active' : '') }}">
                        <div class="step-header">
                            <h3 class="step-title">Pagetyping</h3>
                            @if($hasPageTyping)
                                <div class="step-status completed">
                                    <i data-lucide="check-circle" style="width: 0.7rem; height: 0.7rem;"></i>
                                    {{ $pageTypingCount }} Pages
                                </div>
                            @elseif($hasScanning)
                                <div class="step-status {{ $currentStep == 3 ? 'active' : 'pending' }}">
                                    <i data-lucide="tag" style="width: 0.7rem; height: 0.7rem;"></i>
                                    {{ $currentStep == 3 ? 'Ready' : 'Waiting' }}
                                </div>
                            @else
                                <div class="step-status pending">
                                    <i data-lucide="lock" style="width: 0.7rem; height: 0.7rem;"></i>
                                    Locked
                                </div>
                            @endif
                        </div>
                        
                        <p class="step-description">Classify and label document pages with metadata</p>
                        
                        <div class="step-actions">
                            @if($hasPageTyping)
                                <a href="{{ route('edms.pagetyping', $fileIndexing->id) }}" class="btn btn-outline">
                                    <i data-lucide="eye" style="width: 0.8rem; height: 0.8rem;"></i>
                                    View
                                </a>
                                <a href="{{ route('edms.pagetyping', $fileIndexing->id) }}" class="btn btn-primary">
                                    <i data-lucide="tag" style="width: 0.8rem; height: 0.8rem;"></i>
                                 Type More
                                </a>
                            @elseif($hasScanning)
                                <a href="{{ route('edms.pagetyping', $fileIndexing->id) }}" class="btn btn-primary">
                                    <i data-lucide="tag" style="width: 0.8rem; height: 0.8rem;"></i>
                                    Classify
                                </a>
                            @else
                                @if(request()->get('url') !== 'more')
                                    <button class="btn btn-disabled" disabled>
                                        <i data-lucide="tag" style="width: 0.8rem; height: 0.8rem;"></i>
                                        Classify
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Summary -->
        @if($hasFileIndexing)
        <div class="progress-summary">
            <div class="steps-header">
                <h2 class="steps-title">Progress Overview</h2>
                <p class="steps-subtitle">Track your EDMS workflow completion status and key metrics</p>
            </div>
            
            <div class="progress-grid">
                <div class="progress-item">
                    <div class="progress-value {{ $hasFileIndexing ? 'completed' : '' }}">
                        {{ $hasFileIndexing ? '✓' : '○' }}
                    </div>
                    <div class="progress-label">File Indexed</div>
                    @if($hasFileIndexing)
                        <div class="progress-date">{{ $fileIndexing->created_at->format('M d, Y H:i') }}</div>
                    @else
                        <div class="progress-date">Not started</div>
                    @endif
                </div>
                
                <div class="progress-item">
                    <div class="progress-value {{ $hasScanning ? 'completed' : '' }}">
                        {{ $scanningCount }}
                    </div>
                    <div class="progress-label">Documents Scanned</div>
                    @if($hasScanning)
                        <div class="progress-date">{{ $fileIndexing->scannings->first()->created_at->format('M d, Y H:i') }}</div>
                    @else
                        <div class="progress-date">No documents uploaded</div>
                    @endif
                </div>
                
                <div class="progress-item">
                    <div class="progress-value {{ $hasPageTyping ? 'completed' : '' }}">
                        {{ $pageTypingCount }}
                    </div>
                    <div class="progress-label">Pages Classified</div>
                    @if($hasPageTyping)
                        <div class="progress-date">{{ $fileIndexing->pagetypings->first()->created_at->format('M d, Y H:i') }}</div>
                    @else
                        <div class="progress-date">No pages classified</div>
                    @endif
                </div>
                
                <div class="progress-item">
                    <div class="progress-value completed">
                        {{ $progressPercentage }}%
                    </div>
                    <div class="progress-label">Overall Progress</div>
                    <div class="progress-date">
                        @if($progressPercentage == 100)
                            Workflow Complete
                        @elseif($progressPercentage > 0)
                            In Progress
                        @else
                            Not Started
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Action Section -->
        <div class="action-section">
            @if(isset($applicationType) && $applicationType === 'recertification')
                <a href="{{ route('recertification.edms') }}" class="btn-back">
            @else
                <a href="{{ route('sectionaltitling.primary', $application->id) }}" class="btn-back">
            @endif
                <i data-lucide="arrow-left" style="width: 1rem; height: 1rem;"></i>
                Back to Application
            </a>
            
            @if($hasPageTyping)
                <div class="completion-badge">
                    <i data-lucide="check-circle" style="width: 1.25rem; height: 1.25rem;"></i>
                    <span>EDMS Workflow Complete!</span>
                </div>
            @else
                <div style="color: #718096; font-style: italic; font-size: 0.875rem;">
                    Complete all three steps to finish the EDMS workflow
                </div>
            @endif
        </div>
    </div>

    <!-- Footer -->
    @include('admin.footer')
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    lucide.createIcons();
    
    // Add smooth scrolling and enhanced interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Animate progress bar on load
        const progressBar = document.querySelector('.timeline-progress');
        if (progressBar) {
            const targetWidth = progressBar.style.width;
            progressBar.style.width = '0%';
            setTimeout(() => {
                progressBar.style.width = targetWidth;
            }, 500);
        }
        
        // Add hover effects to workflow steps
        const workflowSteps = document.querySelectorAll('.workflow-step');
        workflowSteps.forEach(step => {
            step.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            step.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Add click tracking for analytics
        const actionButtons = document.querySelectorAll('.btn');
        actionButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Add loading state
                if (!this.disabled && !this.classList.contains('btn-disabled')) {
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<div class="spinner"></div> Loading...';
                    this.disabled = true;
                    
                    // Re-enable after 3 seconds if still on page
                    setTimeout(() => {
                        if (this) {
                            this.innerHTML = originalContent;
                            this.disabled = false;
                            lucide.createIcons();
                        }
                    }, 3000);
                }
            });
        });
    });
</script>
@endsection