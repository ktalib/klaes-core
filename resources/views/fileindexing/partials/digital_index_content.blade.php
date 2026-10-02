<div class="space-y-6">
    <div class="card">
        <div class="p-6">
            <div class="flex items-center mb-2">
                <i data-lucide="brain" class="h-5 w-5 text-purple-600 mr-2"></i>
                <h2 class="text-xl font-bold">Digital Index (AI)</h2>
            </div>
            <p class="text-sm text-gray-500 mb-6">AI-powered document analysis and metadata extraction</p>

            <div class="card p-6 mb-4 border border-dashed border-purple-200 bg-purple-50/40">
                <div class="flex items-center mb-4">
                    <i data-lucide="brain" class="h-5 w-5 text-purple-600 mr-2"></i>
                    <h3 class="text-lg font-medium">AI Indexing: <span id="ai-indexing-files-count">0</span> Files</h3>
                </div>

                <p class="mb-6">Ready to begin AI-powered indexing for <span id="ai-selected-files-count">0</span> selected files.</p>

                <div class="flex justify-center">
                    <button class="btn btn-primary disabled:opacity-50 disabled:cursor-not-allowed" id="start-ai-indexing-btn" disabled>
                        <i data-lucide="brain" class="h-4 w-4 mr-2"></i>
                        Start AI Indexing
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="hidden" id="ai-processing-view">
        <div class="card p-6 mb-4">
            <div class="flex items-center mb-4">
                <i data-lucide="layers" class="h-5 w-5 text-green-500 mr-2"></i>
                <h3 class="text-lg font-medium">AI Indexing: <span id="ai-processing-files-count">0</span> Files</h3>
            </div>

            <div class="mb-4">
                <div class="flex justify-between mb-2">
                    <div class="flex items-center">
                        <i data-lucide="layers" class="h-4 w-4 text-green-500 mr-2"></i>
                        <span class="text-sm">Extracting key information and metadata. Recognizing text, names, dates, and property details...</span>
                    </div>
                    <span class="text-sm" id="progress-percentage">0%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
                </div>
            </div>

            <div class="card p-4 mb-4">
                <div class="mb-2">
                    <span class="text-sm font-medium">AI Processing Pipeline</span>
                    <span class="text-sm float-right" id="pipeline-percentage">0% Complete</span>
                </div>

                <div class="progress mb-2">
                    <div class="progress-bar" id="pipeline-progress-bar" style="width: 0%"></div>
                </div>

                <div class="pipeline">
                    <div class="pipeline-line"></div>
                    <div class="pipeline-progress" id="pipeline-progress-line" style="width: 0%"></div>

                    <div class="pipeline-stage">
                        <div class="pipeline-dot active" id="stage-init"></div>
                        <span class="pipeline-label active">Init</span>
                    </div>

                    <div class="pipeline-stage">
                        <div class="pipeline-dot pending" id="stage-analyze"></div>
                        <span class="pipeline-label pending">Analyze</span>
                    </div>

                    <div class="pipeline-stage">
                        <div class="pipeline-dot pending" id="stage-extract"></div>
                        <span class="pipeline-label pending">Extract</span>
                    </div>

                    <div class="pipeline-stage">
                        <div class="pipeline-dot pending" id="stage-categorize"></div>
                        <span class="pipeline-label pending">Categorize</span>
                    </div>

                    <div class="pipeline-stage">
                        <div class="pipeline-dot pending" id="stage-validate"></div>
                        <span class="pipeline-label pending">Validate</span>
                    </div>

                    <div class="pipeline-stage">
                        <div class="pipeline-dot pending" id="stage-complete"></div>
                        <span class="pipeline-label pending">Complete</span>
                    </div>
                </div>

                <div class="flex items-start gap-3 mt-4" id="current-stage-info">
                    <div class="p-2 bg-green-100 rounded-full">
                        <i data-lucide="loader" class="h-5 w-5 text-green-500"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium mb-1">Current Stage: Initialization</p>
                        <p class="text-xs text-gray-600">Setting up AI processing environment and preparing documents for analysis...</p>
                    </div>
                </div>
            </div>

            <div class="bg-purple-50 p-4 rounded-md border border-purple-100 mb-6">
                <p class="text-purple-700">
                    Our AI is analyzing your documents, extracting metadata, and identifying key information. This process uses machine learning to understand document structure, recognize text, and categorize content.
                </p>
            </div>

            <div class="mb-4" id="ai-insights-container">
                <!-- AI insights will be populated here -->
            </div>

            <div class="flex justify-end">
                <button class="btn btn-primary hidden" id="confirm-save-results-btn">
                    Confirm & Save Results
                </button>
            </div>
        </div>
    </div>
</div>
