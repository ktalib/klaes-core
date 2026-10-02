<style>
/* Table container styles to allow dropdowns to overflow */
.table-container {
  overflow-x: auto;
  position: relative;
}

/* Dropdown menu styles */
.dropdown-container {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: 100%;
  margin-top: 0.25rem;
  width: 14rem;
  background-color: white;
  border-radius: 0.375rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  border: 1px solid #e5e7eb;
  z-index: 1000 !important;
  max-height: 300px;
  overflow-y: auto;
}

.dropdown-menu.hidden {
  display: none;
}

/* Ensure table cells allow overflow for dropdowns */
.table-row td {
  position: relative;
}

.table-row td:last-child {
  overflow: visible !important;
}

/* Make sure the table allows overflow in the actions column */
table {
  table-layout: fixed;
}

table td:last-child {
  overflow: visible !important;
}

/* Override any parent overflow settings */
.table-container {
  overflow: visible !important;
}

.table-container > div {
  overflow: visible !important;
}

/* Responsive dropdown positioning */
@media (max-width: 768px) {
  .dropdown-menu {
    right: -2rem;
    width: 12rem;
  }
}

/* Fix for table overflow issues */
.table-container table {
  position: relative;
  z-index: 1;
}

.table-container .dropdown-menu {
  z-index: 1001 !important;
}

/* Ensure the dropdown appears above everything */
.dropdown-menu {
  position: fixed !important;
  z-index: 9999 !important;
}
</style>