 
<script>
    // Tailwind config
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#3b82f6',
            'primary-foreground': '#ffffff',
            muted: '#f3f4f6',
            'muted-foreground': '#6b7280',
            border: '#e5e7eb',
            destructive: '#ef4444',
            'destructive-foreground': '#ffffff',
            secondary: '#f1f5f9',
            'secondary-foreground': '#0f172a',
          }
        }
      }
    }
    </script>
    
    <style>
    /* Modal backdrop */
    .modal-backdrop {
      background-color: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
    }
    
    /* Custom radio button styles */
    .radio-item {
      position: relative;
      display: flex;
      align-items: center;
      padding: 0.5rem;
      border-radius: 0.375rem;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .radio-item:hover {
      background-color: #f9fafb;
    }
    
    .radio-item input[type="radio"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    
    .radio-circle {
      width: 1rem;
      height: 1rem;
      border: 2px solid #d1d5db;
      border-radius: 50%;
      margin-right: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .radio-item input[type="radio"]:checked + .radio-circle {
      border-color: #3b82f6;
      background-color: #3b82f6;
    }
    
    .radio-circle::after {
      content: '';
      width: 0.25rem;
      height: 0.25rem;
      border-radius: 50%;
      background-color: white;
      opacity: 0;
      transition: opacity 0.2s;
    }
    
    .radio-item input[type="radio"]:checked + .radio-circle::after {
      opacity: 1;
    }
    
    /* Custom checkbox styles */
    .checkbox-item {
      position: relative;
      display: flex;
      align-items: center;
      cursor: pointer;
    }
    
    .checkbox-item input[type="checkbox"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    
    .checkbox-box {
      width: 1rem;
      height: 1rem;
      border: 2px solid #d1d5db;
      border-radius: 0.25rem;
      margin-right: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .checkbox-item input[type="checkbox"]:checked + .checkbox-box {
      border-color: #3b82f6;
      background-color: #3b82f6;
    }
    
    .checkbox-box::after {
      content: '✓';
      color: white;
      font-size: 0.75rem;
      opacity: 0;
      transition: opacity 0.2s;
    }
    
    .checkbox-item input[type="checkbox"]:checked + .checkbox-box::after {
      opacity: 1;
    }
    
    /* Step indicator styles */
    .step-indicator {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.5rem;
    }
    
    .step-circle {
      width: 2rem;
      height: 2rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.3s;
      cursor: pointer;
      border: 2px solid transparent;
    }
    
    .step-circle:hover {
      transform: scale(1.1);
      box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }
    
    .step-circle.active {
      background-color: #3b82f6;
      color: white;
      border-color: #3b82f6;
    }
    
    .step-circle.inactive {
      background-color: #f3f4f6;
      color: #6b7280;
      border-color: #e5e7eb;
    }
    
    .step-circle.inactive:hover {
      background-color: #e5e7eb;
      color: #374151;
    }
    
    .step-line {
      width: 3rem;
      height: 0.125rem;
      margin: 0 0.5rem;
      transition: all 0.3s;
    }
    
    .step-line.active {
      background-color: #3b82f6;
    }
    
    .step-line.inactive {
      background-color: #f3f4f6;
    }
    
    /* Form validation styles */
    .form-field.error input,
    .form-field.error select,
    .form-field.error textarea {
      border-color: #ef4444;
      box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
    }
    
    .form-field.error .error-message {
      display: block;
    }
    
    .error-message {
      display: none;
      color: #ef4444;
      font-size: 0.75rem;
      margin-top: 0.25rem;
    }
    
    /* Loading spinner */
    .loading-spinner {
      width: 1rem;
      height: 1rem;
      border: 2px solid #e5e7eb;
      border-top: 2px solid #3b82f6;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Fade in animation */
    .fade-in {
      animation: fadeIn 0.3s ease-in-out;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    
    /* Photo upload area */
    .photo-upload-area {
      border: 2px dashed #d1d5db;
      border-radius: 0.5rem;
      padding: 1rem;
      text-align: center;
      height: 12rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .photo-upload-area:hover {
      border-color: #3b82f6;
      background-color: #f8fafc;
    }
    
    /* Signature area */
    .signature-area {
      height: 6rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #fafafa;
      color: #6b7280;
    }
    </style>