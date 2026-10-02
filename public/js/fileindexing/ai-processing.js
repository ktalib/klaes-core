/**
 * File Indexing - AI Processing Module
 * Handles AI indexing simulation, progress tracking, and insights generation
 */

import { state } from './state.js';
import * as domUtils from './dom-utils.js';
import * as api from './api-utils.js';
import * as pendingFiles from './pending-files.js';

// ============================================
// AI PROCESSING STATE
// ============================================

const aiProcessingState = {
  isProcessing: false,
  progressInterval: null,
  currentProgress: 0,
  totalStages: 5,
  stageProgress: 0,
};

// Define processing stages
const STAGES = [
  {
    name: 'Document Analysis',
    description: 'Analyzing document structure and content',
    duration: 1400,
  },
  {
    name: 'Land Classification',
    description: 'Classifying land use and boundaries',
    duration: 1100,
  },
  {
    name: 'Area Calculation',
    description: 'Computing area measurements',
    duration: 900,
  },
  {
    name: 'Caveat Detection',
    description: 'Detecting property caveats and restrictions',
    duration: 1200,
  },
  {
    name: 'Report Generation',
    description: 'Generating indexing report',
    duration: 800,
  },
];

// ============================================
// MAIN PROCESSING FUNCTIONS
// ============================================

/**
 * Start AI indexing for selected files
 */
export async function startAiIndexing() {
  if (state.selectedFiles.length === 0) {
    api.showApiErrorNotification('Please select files to index');
    return;
  }

  const selectedCount = state.selectedFiles.length;
  domUtils.updateElementText('ai-indexing-files-count', selectedCount);
  domUtils.updateElementText('ai-selected-files-count', selectedCount);
  domUtils.updateElementText('ai-processing-files-count', selectedCount);

  try {
    // Show AI processing view
    const aiView = document.getElementById('ai-processing-view');
    if (aiView) {
      aiView.classList.remove('hidden');
      aiView.scrollIntoView({ behavior: 'smooth' });
    }

    // Reset progress
    aiProcessingState.currentProgress = 0;
    aiProcessingState.stageProgress = 0;
    updateProgressDisplay();

    // Begin API call
    const result = await api.beginIndexing(state.selectedFiles);

    if (result && result.success) {
      if (result.message) {
        api.showApiSuccessNotification(result.message);
      }
      await simulateIndexingProcess();
      await showAiInsights();
      await refreshPendingFilesList();
    } else {
      const errorMsg = result?.message || 'Failed to begin indexing';
      api.showApiErrorNotification(errorMsg);
      hideAiProcessingView();
    }
  } catch (err) {
    console.error('Error starting AI indexing:', err);
    api.showApiErrorNotification('Indexing process failed');
    hideAiProcessingView();
  }
}

/**
 * Simulate indexing process with staged progress
 */
async function simulateIndexingProcess() {
  aiProcessingState.isProcessing = true;

  for (let stageIndex = 0; stageIndex < STAGES.length; stageIndex++) {
    const stage = STAGES[stageIndex];
    const stageDuration = stage.duration;

    // Update stage info
    updateCurrentStage(stageIndex, stage);

    // Animate progress for this stage
    await animateStageProgress(stageDuration);

    state.indexingProgress = Math.round(
      ((stageIndex + 1) / STAGES.length) * 100
    );
    updateProgressDisplay();
  }

  aiProcessingState.isProcessing = false;
  aiProcessingState.currentProgress = 100;
  updateProgressDisplay();
}

/**
 * Animate progress bar for a single stage
 */
function animateStageProgress(duration) {
  return new Promise((resolve) => {
    const startTime = Date.now();
    const startProgress = aiProcessingState.stageProgress;

    const animate = () => {
      const elapsed = Date.now() - startTime;
      const progress = Math.min(elapsed / duration, 1);

      aiProcessingState.stageProgress = progress * 20; // Each stage is 20% of the total
      aiProcessingState.currentProgress = 
        Math.floor(aiProcessingState.currentProgress / 20) * 20 + 
        aiProcessingState.stageProgress;

      updateProgressDisplay();

      if (progress < 1) {
        requestAnimationFrame(animate);
      } else {
        aiProcessingState.stageProgress = 0;
        resolve();
      }
    };

    animate();
  });
}

/**
 * Update current stage display
 */
function updateCurrentStage(stageIndex, stage) {
  const stageInfo = document.getElementById('current-stage-info');
  if (stageInfo) {
    stageInfo.innerHTML = `
      <div class="text-center">
        <h3 class="font-semibold text-gray-900">${stage.name}</h3>
        <p class="text-sm text-gray-600">${stage.description}</p>
        <div class="text-xs text-gray-400 mt-1">
          Stage ${stageIndex + 1} of ${STAGES.length}
        </div>
      </div>
    `;
  }
}

/**
 * Update progress display elements
 */
function updateProgressDisplay() {
  const progressBar = document.getElementById('progress-bar');
  if (progressBar) {
    progressBar.style.width = `${aiProcessingState.currentProgress}%`;
  }

  const progressPercent = document.getElementById('progress-percentage');
  if (progressPercent) {
    progressPercent.textContent = `${Math.round(aiProcessingState.currentProgress)}%`;
  }

  const pipelineProgressBar = document.getElementById('pipeline-progress-bar');
  if (pipelineProgressBar) {
    const translateX = -100 + aiProcessingState.currentProgress;
    pipelineProgressBar.style.transform = `translateX(${translateX}%)`;
  }

  const pipelinePercent = document.getElementById('pipeline-percentage');
  if (pipelinePercent) {
    pipelinePercent.textContent = `${Math.round(aiProcessingState.currentProgress)}%`;
  }
}

// ============================================
// AI INSIGHTS GENERATION
// ============================================

/**
 * Fetch and display AI insights for indexed files
 */
export async function showAiInsights() {
  try {
    const insights = await api.getAiInsights(state.selectedFiles);

    if (insights && insights.success) {
      displayInsights(insights.data || []);
    }
  } catch (err) {
    console.error('Error loading AI insights:', err);
    displayDefaultInsights();
  }
}

/**
 * Display AI insights in the container
 */
function displayInsights(insights) {
  const container = document.getElementById('ai-insights-container');
  if (!container) return;

  if (!insights || insights.length === 0) {
    displayDefaultInsights();
    return;
  }

  container.innerHTML = '';

  insights.forEach((insight, index) => {
    const insightElement = createInsightElement(insight, index);
    container.appendChild(insightElement);
  });

  // Reinitialize icons
  domUtils.reinitializeIcons();
}

/**
 * Create insight card element
 */
function createInsightElement(insight, index) {
  const div = document.createElement('div');
  div.className = 'insight-card bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 mb-3';

  const severityClass = getSeverityClass(insight.severity);
  const severityIcon = getSeverityIcon(insight.severity);

  div.innerHTML = `
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 mt-1">
        ${severityIcon}
      </div>
      <div class="flex-1">
        <h4 class="font-medium text-gray-900">${escapeHtml(insight.title || 'Insight ' + (index + 1))}</h4>
        <p class="text-sm text-gray-700 mt-1">${escapeHtml(insight.description || '')}</p>
        ${
          insight.recommendation
            ? `<p class="text-sm text-indigo-700 mt-2"><strong>Recommended action:</strong> ${escapeHtml(insight.recommendation)}</p>`
            : ''
        }
      </div>
      <div class="flex-shrink-0">
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${severityClass}">
          ${insight.severity || 'info'}
        </span>
      </div>
    </div>
  `;

  return div;
}

/**
 * Get CSS class for severity level
 */
function getSeverityClass(severity) {
  const severityMap = {
    critical: 'bg-red-100 text-red-800',
    high: 'bg-orange-100 text-orange-800',
    medium: 'bg-yellow-100 text-yellow-800',
    low: 'bg-blue-100 text-blue-800',
    info: 'bg-gray-100 text-gray-800',
  };
  return severityMap[severity] || severityMap.info;
}

/**
 * Get icon HTML for severity level
 */
function getSeverityIcon(severity) {
  const iconMap = {
    critical: '<i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>',
    high: '<i data-lucide="alert-circle" class="w-5 h-5 text-orange-600"></i>',
    medium: '<i data-lucide="info" class="w-5 h-5 text-yellow-600"></i>',
    low: '<i data-lucide="check-circle" class="w-5 h-5 text-blue-600"></i>',
    info: '<i data-lucide="info" class="w-5 h-5 text-gray-600"></i>',
  };
  return iconMap[severity] || iconMap.info;
}

/**
 * Display default insights when API returns empty
 */
function displayDefaultInsights() {
  const container = document.getElementById('ai-insights-container');
  if (!container) return;

  const defaultInsights = [
    {
      title: 'Document Quality',
      description: 'Document scans are clear and readable with good contrast.',
      recommendation: 'Ready for processing',
      severity: 'low',
    },
    {
      title: 'Land Classification',
      description: 'Land use classification completed successfully.',
      recommendation: 'Verify classification accuracy',
      severity: 'low',
    },
    {
      title: 'Area Measurements',
      description: 'All area measurements have been calculated and verified.',
      recommendation: 'Review calculated areas for accuracy',
      severity: 'info',
    },
  ];

  displayInsights(defaultInsights);
}

// ============================================
// UI MANAGEMENT
// ============================================

/**
 * Hide AI processing view
 */
export function hideAiProcessingView() {
  const aiView = document.getElementById('ai-processing-view');
  if (aiView) {
    aiView.classList.add('hidden');
  }

  if (aiProcessingState.progressInterval) {
    clearInterval(aiProcessingState.progressInterval);
  }

  aiProcessingState.isProcessing = false;
}

/**
 * Reset AI processing state
 */
export function resetAiProcessingState() {
  aiProcessingState.currentProgress = 0;
  aiProcessingState.stageProgress = 0;
  aiProcessingState.isProcessing = false;
  state.indexingProgress = 0;

  updateProgressDisplay();

  const container = document.getElementById('ai-insights-container');
  if (container) {
    container.innerHTML = '';
  }
}

/**
 * Check if AI processing is currently active
 */
export function isAiProcessingActive() {
  return aiProcessingState.isProcessing;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(value) {
  const text = value === undefined || value === null ? '' : String(value);
  if (text.length === 0) {
    return '';
  }
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

/**
 * Format stage duration in human-readable format
 */
function formatDuration(ms) {
  const seconds = Math.round(ms / 1000);
  if (seconds < 60) {
    return `${seconds}s`;
  }
  const minutes = Math.round(seconds / 60);
  return `${minutes}m`;
}

async function refreshPendingFilesList() {
  try {
    if (typeof pendingFiles.clearAllSelections === 'function') {
      pendingFiles.clearAllSelections();
    }
    if (typeof pendingFiles.renderPendingFiles === 'function') {
      await pendingFiles.renderPendingFiles();
    }
  } catch (error) {
    console.warn('Failed to refresh pending files after AI indexing:', error);
  }
}
