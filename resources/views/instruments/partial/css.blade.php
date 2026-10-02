  <style>
                body {
                  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                  color: #111827;
                  background-color: #f9fafb;
                }
                
                .card {
                  background-color: white;
                  border-radius: 0.5rem;
                  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                  padding: 1.25rem;
                }
                
                .search-input {
                  width: 100%;
                  padding: 0.5rem 1rem;
                  font-size: 0.875rem;
                  line-height: 1.25rem;
                  border: 1px solid #e5e7eb;
                  border-radius: 0.375rem;
                  background-color: white;
                }
                
                .search-icon {
                  position: absolute;
                  left: 0.75rem;
                  top: 50%;
                  transform: translateY(-50%);
                  color: #9ca3af;
                }
                
                .table-container {
                  background-color: white;
                  border-radius: 0.5rem;
                  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                  overflow: visible;
                }
                
                .table-responsive {
                  overflow-x: auto;
                  max-width: 100%;
                  -webkit-overflow-scrolling: touch;
                }
                
                .table-responsive table {
                  min-width: 1200px;
                  width: max-content;
                }
                
                table {
                  width: 100%;
                  border-collapse: collapse;
                }
                
                th {
                  text-align: left;
                  padding: 0.75rem 1rem;
                  font-size: 0.875rem;
                  font-weight: 500;
                  color: #4b5563;
                  border-bottom: 1px solid #e5e7eb;
                }
                
                td {
                  padding: 0.75rem 1rem;
                  font-size: 0.875rem;
                  border-bottom: 1px solid #e5e7eb;
                  white-space: nowrap;
                  min-width: 120px;
                }
                
                td.expandable {
                  white-space: normal;
                  max-width: 200px;
                  word-wrap: break-word;
                }
                
                .text-proper {
                  text-transform: capitalize;
                }

                .dropdown-menu {
                  z-index: 200;
                }

                .dropdown-menu.drop-up {
                  top: auto !important;
                  bottom: 100%;
                  margin-top: 0 !important;
                  margin-bottom: 0.25rem;
                }
                
                tr:hover {
                  background-color: #f9fafb;
                }
                
                .btn {
                  display: inline-flex;
                  align-items: center;
                  justify-content: center;
                  padding: 0.5rem 1rem;
                  font-size: 0.875rem;
                  font-weight: 500;
                  border-radius: 0.375rem;
                  cursor: pointer;
                }
                
                .btn-primary {
                  background-color: #111827;
                  color: white;
                }
                
                .btn-outline {
                  border: 1px solid #e5e7eb;
                  background-color: white;
                  color: #4b5563;
                }
                
                .badge {
                  display: inline-flex;
                  align-items: center;
                  padding: 0.25rem 0.5rem;
                  font-size: 0.75rem;
                  font-weight: 500;
                  border-radius: 9999px;
                }
                
                .form-input {
                  width: 100%;
                  padding: 0.5rem 0.75rem;
                  font-size: 0.875rem;
                  line-height: 1.25rem;
                  border: 1px solid #e5e7eb;
                  border-radius: 0.375rem;
                  background-color: white;
                }
                
                .form-label {
                  display: block;
                  font-size: 0.875rem;
                  font-weight: 500;
                  margin-bottom: 0.5rem;
                }
                
                .form-section {
                  padding: 1.5rem;
                  border-bottom: 1px solid #e5e7eb;
                }
                
                .form-section-title {
                  font-size: 1.125rem;
                  font-weight: 600;
                  margin-bottom: 1rem;
                }
                
                .form-group {
                  margin-bottom: 1rem;
                }
                
                .form-hint {
                  font-size: 0.75rem;
                  color: #6b7280;
                  margin-top: 0.25rem;
                }
                
                .tab-active {
                  background-color: #f9fafb;
                  border-color: #111827;
                  color: #111827;
                }

                /* Pagination Styles Cleaner */
                .pagination-wrapper nav {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    width: 100%;
                }
                .pagination-wrapper .flex.justify-between.flex-1 {
                    display: none; /* Hide simple mobile pagination if present */
                }
                .pagination-wrapper div:first-child p {
                    font-size: 0.875rem;
                    color: #6b7280;
                    margin: 0;
                }
                /* Remove those boxes/borders around the numbers in "Showing 1 to 10..." */
                .pagination-wrapper span.font-medium {
                    font-weight: 600;
                    color: #111827;
                    border: none !important;
                    padding: 0 !important;
                    background: transparent !important;
                }
                .pagination-wrapper .relative.z-0.inline-flex {
                    border-radius: 0.375rem;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                }
                .pagination-wrapper svg {
                    width: 1.25rem;
                    height: 1.25rem;
                }
                .pagination-wrapper a, .pagination-wrapper span[aria-current="page"] span, .pagination-wrapper span[aria-disabled="true"] span {
                    padding: 0.5rem 0.75rem;
                    border: 1px solid #e5e7eb;
                    font-size: 0.875rem;
                    color: #4b5563;
                    background-color: white;
                    display: inline-flex;
                    align-items: center;
                    text-decoration: none;
                }
                .pagination-wrapper a:hover {
                    background-color: #f9fafb;
                }
                .pagination-wrapper [aria-current="page"] span {
                    background-color: #111827 !important;
                    color: white !important;
                    border-color: #111827 !important;
                    z-index: 10;
                }
                .pagination-wrapper .rounded-l-md { border-top-left-radius: 0.375rem; border-bottom-left-radius: 0.375rem; }
                .pagination-wrapper .rounded-r-md { border-top-right-radius: 0.375rem; border-bottom-right-radius: 0.375rem; }
              </style>