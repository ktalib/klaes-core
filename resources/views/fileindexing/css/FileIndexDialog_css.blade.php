<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

       

        /* Dialog styles */
        .dialog-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }

        .dialog {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 650px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .dialog-header {
            background-color: #1f2937;
            padding: 1.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dialog-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dialog-description {
            color: #6b7280;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            padding: 1rem 1.5rem;
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .dialog-content {
            padding: 1.5rem;
            overflow-y: auto;
        }

        /* Form styles */
        .form-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .form-section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1f2937;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .form-label.required::after {
            content: "*";
            color: #ef4444;
            margin-left: 0.25rem;
        }

        .form-info {
            background-color: #ecfdf5;
            border: 1px solid #d1fae5;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .form-info-icon {
            color: #10b981;
            flex-shrink: 0;
        }

        .form-info-text {
            font-size: 0.875rem;
            color: #065f46;
        }

        .form-help-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .form-radio-group {
            display: flex;
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .form-radio-item {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            background-color: white;
            cursor: pointer;
            border-right: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }

        .form-radio-item:last-child {
            border-right: none;
        }

        .form-radio-item.active {
            background-color: #dbeafe;
            font-weight: 500;
            color: #1d4ed8;
        }

        .form-radio-item:hover {
            background-color: #f3f4f6;
        }

        .form-radio-item input {
            display: none;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .form-checkbox input {
            margin-right: 0.5rem;
            width: 1rem;
            height: 1rem;
        }

        .form-checkbox label {
            font-size: 0.875rem;
            color: #374151;
            cursor: pointer;
        }

        .input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .grid {
            display: grid;
        }

        .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .gap-4 {
            gap: 1rem;
        }

        .flex {
            display: flex;
        }

        .justify-between {
            justify-content: space-between;
        }

        .mt-6 {
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #d1d5db;
            background-color: white;
            color: #374151;
        }

        .btn:hover {
            background-color: #f9fafb;
        }

        .btn-blue {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .btn-blue:hover {
            background-color: #2563eb;
        }

        .hidden {
            display: none;
        }

        .text-white {
            color: white;
        }

        .h-5 {
            height: 1.25rem;
        }

        .w-5 {
            width: 1.25rem;
        }

        .h-4 {
            height: 1rem;
        }

        .w-4 {
            width: 1rem;
        }

        .h-3 {
            height: 0.75rem;
        }

        .w-3 {
            width: 0.75rem;
        }

        .items-start {
            align-items: flex-start;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        /* Demo button */
        .demo-btn {
            background-color: #3b82f6;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .demo-btn:hover {
            background-color: #2563eb;
        }
    </style>