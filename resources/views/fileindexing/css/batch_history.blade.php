<style>
/* Additional CSS for Batch History functionality */
/* Add this to your existing CSS file or include it separately */

/* Badge styles for batch history */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 500;
  border-radius: 0.375rem;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.badge-blue {
  background-color: #dbeafe;
  color: #1e40af;
}

.badge-green {
  background-color: #dcfce7;
  color: #166534;
}

.badge-purple {
  background-color: #e9d5ff;
  color: #7c2d12;
}

.badge-gray {
  background-color: #f3f4f6;
  color: #374151;
}

.badge-yellow {
  background-color: #fef3c7;
  color: #92400e;
}

/* Modal improvements */
.max-h-90vh {
  max-height: 90vh;
}

/* Loading states */
.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* Batch history table improvements */
#batch-history-table-container table tbody tr:hover {
  background-color: #f9fafb;
}

/* Action buttons in table */
#batch-history-table-container .text-blue-600:hover {
  text-decoration: underline;
}

#batch-history-table-container .text-green-600:hover {
  text-decoration: underline;
}

/* Responsive improvements for batch history */
@media (max-width: 768px) {
  #batch-history-table-container {
    overflow-x: auto;
  }
  
  #batch-history-table-container table {
    min-width: 800px;
  }
}
</style>