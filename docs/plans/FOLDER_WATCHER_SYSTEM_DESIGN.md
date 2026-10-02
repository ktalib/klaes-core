# Folder Name Correction & Auto-Watcher System Design Plan

**Date:** December 6, 2025  
**Status:** Planning Phase  
**Priority:** High  
**Purpose:** Automatically detect, correct, and monitor folder names based on file numbers

---

## Executive Summary

This system will:
1. **Monitor** incoming folders for incorrect file number naming
2. **Detect** common file number errors in folder names
3. **Auto-correct** folder names to proper format
4. **Log all changes** with audit trail
5. **Handle conflicts** (duplicates, overwrites, etc.)
6. **Watch continuously** for new folders and correct them in real-time

---

## Key Changes for Configurable, Root-Based Python Solution

✅ **Python with Watchdog** - Real-time monitoring, cross-platform  
✅ **Location:** `/xampp/htdocs/klaes/folder-watcher/` (in Laravel root)  
✅ **Configurable Paths** - All watch paths in YAML config  
✅ **Relative Path Support** - Paths relative to Laravel root  
✅ **Command-Line Arguments** - `--batch`, `--watch`, `--dry-run`, `--path`  
✅ **Background Service Ready** - Can run as Windows Service
✅ **Zero Dependencies** - Just Python + watchdog + PyYAML  

**Quick Start:**
```bash
cd /xampp/htdocs/klaes/folder-watcher
python watcher.py --batch          # Correct existing folders
python watcher.py --watch          # Monitor for new folders
```

---

## Part 1: Current Situation Analysis

### 1.1 Problem Statement

**Current Issues:**
- Users manually create folders with inconsistent file number names
- Common mistakes: `C0M-RES-92-1234`, `RES--1992--1234`, `COM - RES - 1992 - 1234`
- No validation before folder creation
- Manual corrections are time-consuming and error-prone
- Difficult to track what was changed and why

**File Number Errors Reference:**
See `FILE_NUMBER_SYSTEM_DESIGN_PLAN.md` Part 2 for comprehensive documentation of:
- 17 documented error types (ERR001-ERR017)
- Common OCR mistakes and input errors
- Real examples from the system
- Correction strategies for each error type

This folder watcher system automates the correction of these errors in folder names.

**Example Folder Structures:**
```
/storage/
├── C0M-RES-1992-1234/          ❌ Wrong (0 instead of O)
├── RES--1992--1176/             ❌ Wrong (double hyphens)
├── COM - RES - 1992 - 123/     ❌ Wrong (spaces everywhere)
├── CON-RES-1982-1263/           ✅ Correct
├── RES-1992-1176/               ✅ Correct
└── ST-RES-2025-001/             ✅ Correct
```

### 1.2 Scope

**What We'll Handle:**
- Folder renames based on corrected file numbers
- Real-time folder monitoring
- Batch processing of existing folders
- Error logging and reporting
- Conflict resolution
- Audit trail

**What We'll NOT Handle:**
- File contents (read-only, folder names only)
- Permission changes
- Folder deletion (only renaming)
- Database updates (optional integration)

---

## Part 2: System Architecture

### 2.1 Technology Options

#### **Option A: Python with Watchdog (Recommended for Windows)**
**Pros:**
- Easy to use, lightweight
- Windows platform optimized
- Real-time file system monitoring
- Monitor multiple paths simultaneously
- Minimal dependencies

**Cons:**
- Requires Python installation
- Need to run as background service

**Best for:** Comprehensive solution with continuous monitoring

#### **Option B: Windows Task Scheduler + Python Script**
**Pros:**
- Native Windows integration
- Simple setup
- Runs periodically or on-demand

**Cons:**
- Not truly real-time (can miss fast changes)
- Limited to periodic execution

**Best for:** Simple periodic corrections

---

## Part 3: Recommended Solution - Python Watchdog (Windows-Based)

### 3.1 System Components

```
┌─────────────────────────────────────────────────────────────┐
│                 Folder Watcher System                        │
└─────────────────────────────────────────────────────────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
    ┌─────────┐         ┌─────────┐        ┌─────────┐
    │  Monitor│         │ Validate │       │  Correct │
    │  Folders│         │  Names   │       │  Names   │
    └─────────┘         └─────────┘        └─────────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             │
                    ┌────────────────┐
                    │  Audit & Logs  │
                    └────────────────┘
```

### 3.2 File Structure (In Laravel Root)

```
/xampp/htdocs/klaes/                   # Laravel Project Root
├── folder-watcher/                    # Watcher Program Directory
│   ├── config.yaml                    # Configuration file (CONFIGURABLE PATHS)
│   ├── watcher.py                     # Main watcher script
│   ├── file_number_validator.py       # Validation logic
│   ├── folder_corrector.py            # Correction logic
│   ├── logger.py                      # Logging utilities
│   ├── requirements.txt               # Python dependencies
│   ├── logs/                          # Log files
│   │   ├── corrections.log            # All corrections
│   │   ├── errors.log                 # Errors encountered
│   │   ├── watcher.log                # General logs
│   │   └── audit.log                  # Audit trail
│   ├── archive/                       # Backup of old names
│   │   └── corrections_backup_YYYY-MM-DD.json
│   ├── .env.example                   # Environment variables template
│   ├── README.md                      # Documentation
│   └── venv/                          # Python virtual environment (created during setup)
├── storage/                           # Laravel storage folder
├── app/                               # Laravel application code
└── ...
```

**Note:** The `folder-watcher` directory is at the root level of the Laravel project (`/xampp/htdocs/klaes/`), making it easy to access and maintain.

### 3.3 Configuration File (config.yaml) - Fully Configurable Paths

```yaml
# Folder Watcher Configuration
# Place this file in: /xampp/htdocs/klaes/folder-watcher/config.yaml

# Laravel Project Root (auto-detected, can be overridden)
laravel_root: ..                        # Relative path to Laravel root

# Paths to monitor - can be multiple paths (relative to Laravel root OR absolute Windows paths)
watch_paths:
  - storage/app/public/EDMS/BLIND_SCAN  # Path 1: Blind Scanning folder
  - storage/files                       # Path 2: Additional files folder
  - storage/documents                   # Path 3: Documents folder
  - storage/archives                    # Path 4: Archive folder
  # Add more paths as needed - all will be monitored simultaneously
  # - C:\xampp\htdocs\klaes\storage\additional  # Absolute Windows path
  # - D:\external\shared\files          # External Windows path

# Logging configuration
logging:
  level: INFO                           # DEBUG, INFO, WARNING, ERROR, CRITICAL
  log_dir: logs                         # Relative to watcher directory
  max_log_size: 10MB
  backup_count: 5
  json_format: true                     # Export logs as JSON for UI parsing

# Behavior configuration
behavior:
  auto_correct: true                    # Auto-correct folder names
  dry_run: false                        # Preview changes without executing
  create_backup: true                   # Backup old names
  recurse_subdirs: false                # Watch subdirectories
  debounce_ms: 1000                     # Wait before processing (avoid rapid changes)
  ignore_patterns:                      # Patterns to ignore
    - ".*"                              # Hidden files/folders
    - "temp*"
    - ".DS_Store"
    - "Thumbs.db"
    - "desktop.ini"

# Correction rules
correction:
  max_rename_depth: 3                   # How deep to look for folders
  skip_already_correct: true            # Skip already correct names
  handle_conflicts: "rename_old"        # Options: skip, rename_old, overwrite
  max_concurrent_renames: 5             # Parallel rename operations
  
# Notification settings
notifications:
  log_corrections: true
  log_errors: true
  alert_on_conflict: true
  email_on_error: false
  email_to: "admin@example.com"

# Performance tuning
performance:
  check_interval_seconds: 5             # If not using real-time watch
  enable_real_time_watch: true          # Use watchdog for real-time monitoring

# Whitelist/Blacklist for file number prefixes
filters:
  whitelist_prefixes:                   # Only process these prefixes
    - ST-
    - RES-
    - COM-
    - IND-
    - AG-
    - CON-
    - MISC-
    - SIT-
    - SLTR-
  blacklist_keywords:                   # Never process folders with these keywords
    - ARCHIVE
    - LOCKED
    - READONLY
    - BACKUP
```

**Path Resolution Strategy (Windows Only):**
1. If path contains `:\` → treat as absolute Windows path
2. Otherwise → relative to Laravel root directory
3. Can monitor multiple paths simultaneously in one process

**Examples:**
```yaml
# Relative paths (recommended)
watch_paths:
  - storage/files
  - storage/documents
  - storage/archives
# Resolves to:
# C:\xampp\htdocs\klaes\storage\files
# C:\xampp\htdocs\klaes\storage\documents
# C:\xampp\htdocs\klaes\storage\archives

# Absolute Windows paths
watch_paths:
  - C:\xampp\htdocs\klaes\storage\files
  - D:\shared\documents
  - E:\backup\archives

# Mixed relative and absolute paths (most flexible)
watch_paths:
  - storage/app/public/EDMS/BLIND_SCAN  # Relative
  - storage/files                       # Relative
  - D:\external\shared\files           # Absolute Windows path
  - E:\network\documents               # Absolute Windows path
```

---

## Part 4: Python Implementation Strategy

### 4.1 Main Watcher Script (watcher.py)

```python
"""
Folder Name Watcher and Corrector
Monitors directories and automatically corrects file number folder names
"""

import os
import sys
import yaml
import logging
import json
from pathlib import Path
from datetime import datetime
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler, DirCreatedEvent

from file_number_validator import FileNumberValidator
from folder_corrector import FolderCorrector
from logger import setup_logging

class FolderNameEventHandler(FileSystemEventHandler):
    """Handles folder creation/modification events"""
    
    def __init__(self, config, validator, corrector, logger):
        self.config = config
        self.validator = validator
        self.corrector = corrector
        self.logger = logger
        self.pending_folders = set()
    
    def on_created(self, event):
        """Handle folder creation"""
        if event.is_directory:
            # Debounce rapid changes
            folder_path = event.src_path
            self.logger.info(f"Detected new folder: {folder_path}")
            
            # Add to pending and schedule check
            self.pending_folders.add(folder_path)
            self._schedule_correction(folder_path)
    
    def on_modified(self, event):
        """Handle folder modification"""
        # Check if folder was renamed
        if event.is_directory:
            self.logger.debug(f"Detected modification: {event.src_path}")
    
    def _schedule_correction(self, folder_path):
        """Schedule correction for a folder"""
        try:
            folder_name = os.path.basename(folder_path)
            
            # Validate the folder name
            validation_result = self.validator.validate_and_normalize(folder_name)
            
            if not validation_result['is_valid']:
                self.logger.warning(f"Invalid folder name: {folder_name}")
                self.logger.warning(f"Errors: {validation_result['errors']}")
            
            # Check if correction is needed
            if validation_result['needs_correction']:
                corrected_name = validation_result['normalized']
                
                # Perform the correction
                result = self.corrector.rename_folder(
                    folder_path,
                    corrected_name,
                    validation_result
                )
                
                if result['success']:
                    self.logger.info(
                        f"✓ Renamed: {folder_name} → {corrected_name}"
                    )
                else:
                    self.logger.error(
                        f"✗ Failed to rename: {folder_name} - {result['error']}"
                    )
            else:
                self.logger.debug(f"✓ Folder name already correct: {folder_name}")
        
        except Exception as e:
            self.logger.error(f"Error processing folder: {folder_path} - {str(e)}")

class FolderWatcher:
    """Main watcher orchestrator"""
    
    def __init__(self, config_path='config.yaml'):
        self.watcher_root = Path(__file__).parent.absolute()
        self.laravel_root = self.watcher_root.parent  # Parent of folder-watcher
        self.config = self._load_config(config_path)
        self.logger = setup_logging(self.config, self.watcher_root)
        self.validator = FileNumberValidator()
        self.corrector = FolderCorrector(self.config, self.logger, self.laravel_root)
        self.observer = None
    
    def _load_config(self, config_path):
        """Load configuration from YAML file"""
        config_file = self.watcher_root / config_path
        try:
            with open(config_file, 'r') as f:
                config = yaml.safe_load(f)
            
            # Resolve watch paths relative to Laravel root
            resolved_paths = []
            for path in config.get('watch_paths', []):
                resolved_path = self._resolve_path(path)
                resolved_paths.append(resolved_path)
            
            config['watch_paths'] = resolved_paths
            return config
        
        except FileNotFoundError:
            print(f"Config file not found: {config_file}")
            sys.exit(1)
    
    def _resolve_path(self, path):
        """
        Resolve path to absolute path
        - If absolute, use as-is
        - If relative, resolve relative to Laravel root
        """
        path_obj = Path(path)
        
        # Check if already absolute
        if path_obj.is_absolute():
            return str(path_obj.absolute())
        
        # Resolve relative to Laravel root
        resolved = self.laravel_root / path
        return str(resolved.absolute())
    
    def start_watching(self):
        """Start monitoring folders"""
        self.logger.info("=" * 60)
        self.logger.info("Folder Watcher Started")
        self.logger.info("=" * 60)
        
        # Create observer
        self.observer = Observer()
        event_handler = FolderNameEventHandler(
            self.config,
            self.validator,
            self.corrector,
            self.logger
        )
        
        # Register paths
        watch_paths = self.config.get('watch_paths', [])
        for path in watch_paths:
            if os.path.exists(path):
                self.observer.schedule(
                    event_handler,
                    path,
                    recursive=self.config['behavior'].get('recurse_subdirs', False)
                )
                self.logger.info(f"Watching: {path}")
            else:
                self.logger.error(f"Path not found: {path}")
        
        # Start observer
        self.observer.start()
        
        try:
            while True:
                time.sleep(1)
        except KeyboardInterrupt:
            self.stop_watching()
    
    def stop_watching(self):
        """Stop monitoring folders"""
        if self.observer:
            self.observer.stop()
            self.observer.join()
            self.logger.info("Folder Watcher Stopped")
    
    def scan_existing_folders(self):
        """Scan and correct existing folders (batch mode)"""
        self.logger.info("Starting batch correction of existing folders...")
        
        for path in self.config.get('watch_paths', []):
            if os.path.exists(path):
                self._scan_directory(path)
        
        self.logger.info("Batch correction completed")
    
    def _scan_directory(self, directory):
        """Recursively scan directory for folder corrections"""
        try:
            for item in os.listdir(directory):
                item_path = os.path.join(directory, item)
                
                if os.path.isdir(item_path):
                    # Validate and correct if needed
                    validation = self.validator.validate_and_normalize(item)
                    
                    if validation['needs_correction']:
                        corrected_name = validation['normalized']
                        result = self.corrector.rename_folder(
                            item_path,
                            corrected_name,
                            validation
                        )
                        
                        if result['success']:
                            self.logger.info(
                                f"✓ Batch: Renamed {item} → {corrected_name}"
                            )
        except Exception as e:
            self.logger.error(f"Error scanning directory {directory}: {str(e)}")

if __name__ == '__main__':
    import time
    import argparse
    
    # Parse command line arguments
    parser = argparse.ArgumentParser(description='Folder Name Watcher')
    parser.add_argument('--watch', action='store_true', help='Start continuous watching')
    parser.add_argument('--batch', action='store_true', help='Do one-time batch correction')
    parser.add_argument('--config', default='config.yaml', help='Path to config file')
    parser.add_argument('--path', help='Watch specific path (overrides config)')
    parser.add_argument('--dry-run', action='store_true', help='Preview changes without executing')
    parser.add_argument('--debug', action='store_true', help='Enable debug logging')
    
    args = parser.parse_args()
    
    watcher = FolderWatcher(args.config)
    
    # Override config if command-line args provided
    if args.dry_run:
        watcher.config['behavior']['dry_run'] = True
    if args.debug:
        watcher.logger.setLevel(logging.DEBUG)
    if args.path:
        watcher.config['watch_paths'] = [watcher._resolve_path(args.path)]
    
    # Execute requested action
    if args.batch:
        print("Starting batch correction...")
        watcher.scan_existing_folders()
        print("Batch correction completed!")
    elif args.watch:
        print("Starting folder watcher...")
        watcher.start_watching()
    else:
        # Default: show help
        parser.print_help()
        print("\nExample usage:")
        print("  python watcher.py --batch                    # Correct existing folders")
        print("  python watcher.py --watch                    # Watch for new folders")
        print("  python watcher.py --watch --dry-run          # Preview without changes")
        print("  python watcher.py --batch --path storage/files")
```
```

### 4.2 File Number Validator (file_number_validator.py)

```python
"""
File Number Validator for Folder Names
Reuses logic from main system
"""

import re

class FileNumberValidator:
    """Validates and normalizes file numbers in folder names"""
    
    def __init__(self):
        self.valid_prefixes = ['ST', 'RES', 'COM', 'IND', 'AG', 'CON', 'MISC', 'SLTR', 'SIT', 'KN']
    
    def validate_and_normalize(self, folder_name):
        """
        Validate and normalize folder name
        
        Returns:
            {
                'is_valid': bool,
                'is_correct': bool,
                'needs_correction': bool,
                'normalized': str,
                'original': str,
                'type': str,
                'errors': list,
                'corrections_applied': list
            }
        """
        
        result = {
            'original': folder_name,
            'normalized': None,
            'is_valid': False,
            'is_correct': False,
            'needs_correction': False,
            'type': 'unknown',
            'errors': [],
            'warnings': [],
            'corrections_applied': [],
        }
        
        if not folder_name or not isinstance(folder_name, str):
            result['errors'].append('Folder name is empty or invalid')
            return result
        
        # Step 1: Initial cleanup
        cleaned = self._initial_cleanup(folder_name)
        
        if cleaned != folder_name:
            result['corrections_applied'].append('Initial cleanup applied')
        
        # Step 2: Character corrections
        corrected = self._correct_characters(cleaned)
        
        if corrected != cleaned:
            result['corrections_applied'].append('Character corrections applied')
        
        # Step 3: Prefix correction
        prefix_corrected = self._correct_prefixes(corrected)
        
        if prefix_corrected != corrected:
            result['corrections_applied'].append('Prefix correction applied')
        
        # Step 4: Year normalization
        year_normalized = self._normalize_year(prefix_corrected)
        
        if year_normalized != prefix_corrected:
            result['corrections_applied'].append('Year normalization applied')
        
        # Step 5: Serial cleanup
        serial_cleaned = self._clean_serial(year_normalized)
        
        if serial_cleaned != year_normalized:
            result['corrections_applied'].append('Serial number cleaned')
        
        # Step 6: Classify and validate
        classification = self._classify(serial_cleaned)
        result['type'] = classification['type']
        
        # Step 7: Check if valid
        if self._is_valid_pattern(serial_cleaned, classification['type']):
            result['is_valid'] = True
            result['normalized'] = serial_cleaned
            
            # Check if original was already correct
            if serial_cleaned == folder_name:
                result['is_correct'] = True
            else:
                result['needs_correction'] = True
        else:
            result['errors'].append(f"Does not match valid pattern for type: {classification['type']}")
        
        return result
    
    def _initial_cleanup(self, name):
        """Trim, uppercase, remove spaces"""
        name = name.strip()
        name = name.upper()
        name = name.replace(' ', '')  # Remove all spaces
        return name
    
    def _correct_characters(self, name):
        """Fix slashed zeros, special characters"""
        name = name.replace('Ø', 'O').replace('∅', 'O').replace('⊘', 'O')
        name = name.replace('/', '-').replace('=', '-').replace('_', '-')
        name = re.sub(r'-{2,}', '-', name)  # Multiple hyphens to single
        return name
    
    def _correct_prefixes(self, name):
        """Fix prefix errors"""
        # CN → CON
        name = re.sub(r'^CN-', 'CON-', name)
        
        # Character substitution in prefix (C0M → COM, R3S → RES, etc.)
        if re.match(r'^[A-Z0-9]+-', name):
            prefix_match = re.match(r'^([A-Z0-9]+)-', name)
            if prefix_match:
                prefix = prefix_match.group(1)
                rest = name[len(prefix):]
                
                # Substitute numbers that look like letters
                prefix = prefix.replace('0', 'O')
                prefix = prefix.replace('3', 'E')
                prefix = prefix.replace('1', 'I')
                prefix = prefix.replace('5', 'S')
                
                name = prefix + rest
        
        return name
    
    def _normalize_year(self, name):
        """Expand 2-digit years, fix 18XX"""
        # Fix 18XX → 19XX
        name = re.sub(r'^(.+?)-18(\d{2})-', r'\1-19\2-', name)
        
        # Expand 2-digit years
        def expand_year(match):
            prefix = match.group(1)
            year = int(match.group(3))
            suffix = match.group(4)
            
            if year <= 25:
                year = 2000 + year
            else:
                year = 1900 + year
            
            return f"{prefix}-{year}{suffix}"
        
        name = re.sub(r"^(.+?)-(['\"])?(\d{2})(-\d+.*)$", expand_year, name)
        
        return name
    
    def _clean_serial(self, name):
        """Clean serial number: O→0, I→1"""
        if re.match(r'^.+?-(\d+.*)$', name):
            # Match everything after last hyphen
            parts = name.rsplit('-', 1)
            if len(parts) == 2:
                prefix = parts[0]
                serial = parts[1]
                
                # Only replace in serial position
                serial = serial.replace('O', '0').replace('I', '1')
                
                name = f"{prefix}-{serial}"
        
        return name
    
    def _classify(self, name):
        """Classify file number type"""
        patterns = {
            'st_primary': r'^ST-(RES|COM|IND|MIXED)-\d{4}-\d+$',
            'st_unit': r'^ST-(RES|COM|IND|MIXED)-\d{4}-\d+-\d+$',
            'mls_standard': r'^(RES|COM|IND|AG)-\d{4}-\d+$',
            'mls_conversion': r'^CON-(RES|COM|IND|AG)-\d{4}-\d+$',
            'mls_rc': r'^(RES|COM|IND|AG)-RC-\d{4}-\d+$',
            'mls_conversion_rc': r'^CON-(RES|COM|IND|AG)-RC-\d{4}-\d+$',
            'mls_legacy': r'^(RES|COM|IND|AG)-\d{3,5}$',
            'kangis': r'^[A-Z]{4}\s?\d{5}$',
            'kangis_new': r'^KN\d{4}$',
        }
        
        for type_name, pattern in patterns.items():
            if re.match(pattern, name, re.IGNORECASE):
                return {'type': type_name, 'pattern': pattern}
        
        return {'type': 'unknown', 'pattern': None}
    
    def _is_valid_pattern(self, name, type_name):
        """Check if name matches valid pattern"""
        patterns = {
            'st_primary': r'^ST-(RES|COM|IND|MIXED)-\d{4}-\d+$',
            'st_unit': r'^ST-(RES|COM|IND|MIXED)-\d{4}-\d+-\d+$',
            'mls_standard': r'^(RES|COM|IND|AG)-\d{4}-\d+$',
            'mls_conversion': r'^CON-(RES|COM|IND|AG)-\d{4}-\d+$',
            'mls_rc': r'^(RES|COM|IND|AG)-RC-\d{4}-\d+$',
            'mls_conversion_rc': r'^CON-(RES|COM|IND|AG)-RC-\d{4}-\d+$',
            'mls_legacy': r'^(RES|COM|IND|AG)-\d{3,5}$',
            'kangis': r'^[A-Z]{4}\s?\d{5}$',
            'kangis_new': r'^KN\d{4}$',
        }
        
        if type_name not in patterns:
            return False
        
        return bool(re.match(patterns[type_name], name, re.IGNORECASE))
```

### 4.3 Folder Corrector (folder_corrector.py)

```python
"""
Folder Name Corrector
Handles renaming of folders with conflict resolution
"""

import os
import shutil
import json
from datetime import datetime
from pathlib import Path

class FolderCorrector:
    """Handles folder renaming with conflict management"""
    
    def __init__(self, config, logger, watcher_root=None):
        self.config = config
        self.logger = logger
        
        # Set archive directory relative to watcher root
        if watcher_root:
            self.archive_dir = watcher_root / 'archive'
        else:
            self.archive_dir = Path('./archive')
        
        self.archive_dir.mkdir(exist_ok=True)
    
    def rename_folder(self, folder_path, new_name, validation_result):
        """
        Rename folder to corrected name
        
        Args:
            folder_path: Full path to folder
            new_name: Corrected folder name
            validation_result: Result from validator
        
        Returns:
            {'success': bool, 'old_path': str, 'new_path': str, 'error': str or None}
        """
        
        try:
            old_name = os.path.basename(folder_path)
            parent_dir = os.path.dirname(folder_path)
            new_path = os.path.join(parent_dir, new_name)
            
            # Check if already correct
            if old_name == new_name:
                return {
                    'success': True,
                    'old_path': folder_path,
                    'new_path': new_path,
                    'message': 'Folder name already correct',
                    'skipped': True
                }
            
            # Check if dry run mode
            if self.config['behavior'].get('dry_run'):
                self.logger.info(f"[DRY RUN] Would rename: {old_name} → {new_name}")
                return {
                    'success': True,
                    'old_path': folder_path,
                    'new_path': new_path,
                    'message': 'Dry run mode - no actual changes',
                    'dry_run': True
                }
            
            # Check for conflicts
            if os.path.exists(new_path):
                return self._handle_conflict(
                    folder_path,
                    new_path,
                    old_name,
                    new_name
                )
            
            # Perform rename
            os.rename(folder_path, new_path)
            
            # Log the change
            self._log_correction(old_name, new_name, validation_result)
            
            # Create backup record
            if self.config['behavior'].get('create_backup'):
                self._backup_change(old_name, new_name, validation_result)
            
            return {
                'success': True,
                'old_path': folder_path,
                'new_path': new_path,
                'old_name': old_name,
                'new_name': new_name,
                'timestamp': datetime.now().isoformat()
            }
        
        except PermissionError:
            error_msg = f"Permission denied renaming {folder_path}"
            self.logger.error(error_msg)
            return {'success': False, 'error': error_msg}
        
        except Exception as e:
            error_msg = f"Error renaming folder: {str(e)}"
            self.logger.error(error_msg)
            return {'success': False, 'error': error_msg}
    
    def _handle_conflict(self, old_path, new_path, old_name, new_name):
        """Handle conflict when target folder already exists"""
        
        strategy = self.config['correction'].get('handle_conflicts', 'rename_old')
        
        if strategy == 'skip':
            error_msg = f"Target exists, skipping: {new_name}"
            self.logger.warning(error_msg)
            return {'success': False, 'error': error_msg, 'conflict': True}
        
        elif strategy == 'rename_old':
            # Rename old folder with timestamp
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            backup_name = f"{old_name}_backup_{timestamp}"
            backup_path = os.path.join(os.path.dirname(old_path), backup_name)
            
            try:
                os.rename(old_path, backup_path)
                os.rename(backup_path, new_path)
                
                self.logger.warning(
                    f"Conflict resolved: renamed old folder to {backup_name}, "
                    f"then to {new_name}"
                )
                
                return {
                    'success': True,
                    'old_path': old_path,
                    'new_path': new_path,
                    'conflict_resolved': True,
                    'backup_path': backup_path
                }
            except Exception as e:
                return {
                    'success': False,
                    'error': f"Failed to resolve conflict: {str(e)}",
                    'conflict': True
                }
        
        elif strategy == 'overwrite':
            # This is dangerous - log warning
            self.logger.warning(f"Overwriting existing folder: {new_path}")
            
            try:
                shutil.rmtree(new_path)
                os.rename(old_path, new_path)
                
                return {
                    'success': True,
                    'old_path': old_path,
                    'new_path': new_path,
                    'overwritten': True
                }
            except Exception as e:
                return {
                    'success': False,
                    'error': f"Failed to overwrite: {str(e)}"
                }
        
        return {
            'success': False,
            'error': f"Unknown conflict strategy: {strategy}"
        }
    
    def _log_correction(self, old_name, new_name, validation):
        """Log the correction for audit trail"""
        # Implemented in logger.py
        pass
    
    def _backup_change(self, old_name, new_name, validation):
        """Create backup record of changes"""
        backup_file = self.archive_dir / f"corrections_{datetime.now().strftime('%Y-%m-%d')}.json"
        
        record = {
            'timestamp': datetime.now().isoformat(),
            'old_name': old_name,
            'new_name': new_name,
            'corrections_applied': validation.get('corrections_applied', []),
            'type': validation.get('type', 'unknown')
        }
        
        try:
            # Load existing records
            records = []
            if backup_file.exists():
                with open(backup_file, 'r') as f:
                    records = json.load(f)
            
            # Add new record
            records.append(record)
            
            # Save updated records
            with open(backup_file, 'w') as f:
                json.dump(records, f, indent=2)
        
        except Exception as e:
            self.logger.error(f"Failed to backup change: {str(e)}")
```

### 4.4 Logging Setup (logger.py)

```python
"""
Logging configuration for Folder Watcher
"""

import logging
import logging.handlers
from pathlib import Path
from datetime import datetime

def setup_logging(config, watcher_root=None):
    """Setup logging with multiple handlers"""
    
    log_dir_name = config.get('logging', {}).get('log_dir', 'logs')
    
    # Determine log directory path
    if watcher_root:
        log_dir = watcher_root / log_dir_name
    else:
        log_dir = Path(log_dir_name)
    
    log_dir.mkdir(exist_ok=True)
    
    log_level = getattr(
        logging,
        config.get('logging', {}).get('level', 'INFO')
    )
    
    # Create logger
    logger = logging.getLogger('FolderWatcher')
    logger.setLevel(log_level)
    
    # Console handler
    console_handler = logging.StreamHandler()
    console_handler.setLevel(log_level)
    console_formatter = logging.Formatter(
        '%(asctime)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    console_handler.setFormatter(console_formatter)
    logger.addHandler(console_handler)
    
    # File handler for all logs
    file_handler = logging.handlers.RotatingFileHandler(
        log_dir / 'watcher.log',
        maxBytes=1024 * 1024 * 10,  # 10MB
        backupCount=5
    )
    file_handler.setLevel(log_level)
    file_formatter = logging.Formatter(
        '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    file_handler.setFormatter(file_formatter)
    logger.addHandler(file_handler)
    
    # Separate file for corrections only
    corrections_handler = logging.handlers.RotatingFileHandler(
        log_dir / 'corrections.log',
        maxBytes=1024 * 1024 * 5,
        backupCount=5
    )
    corrections_handler.setLevel(logging.INFO)
    corrections_handler.setFormatter(file_formatter)
    
    # Add filter for corrections only
    class CorrectionsFilter(logging.Filter):
        def filter(self, record):
            return '✓ Renamed' in record.getMessage() or '✓ Batch' in record.getMessage()
    
    corrections_handler.addFilter(CorrectionsFilter())
    logger.addHandler(corrections_handler)
    
    # Separate file for errors
    errors_handler = logging.handlers.RotatingFileHandler(
        log_dir / 'errors.log',
        maxBytes=1024 * 1024 * 5,
        backupCount=5
    )
    errors_handler.setLevel(logging.ERROR)
    errors_handler.setFormatter(file_formatter)
    logger.addHandler(errors_handler)
    
    return logger
```

### 4.5 Requirements.txt

```txt
watchdog>=3.0.0
PyYAML>=6.0
```

---

## Part 5: Installation & Setup (Root-Based Structure)

### 5.1 Installation Steps (Windows)

```batch
REM 1. Navigate to Laravel root directory
cd C:\xampp\htdocs\klaes

REM 2. Create watcher directory
mkdir folder-watcher
cd folder-watcher

REM 3. Create Python virtual environment
python -m venv venv

REM 4. Activate virtual environment
venv\Scripts\activate.bat

REM 5. Create requirements.txt file
echo watchdog>=3.0.0 > requirements.txt
echo PyYAML>=6.0 >> requirements.txt

REM 6. Install dependencies
pip install -r requirements.txt

REM 7. Create necessary directories
mkdir logs
mkdir archive
mkdir docs

REM 8. Copy Python scripts here
REM See documentation for: watcher.py, file_number_validator.py, folder_corrector.py, logger.py

REM 9. Create config.yaml (see examples below)
```

**Windows PowerShell Quick Setup:**
```powershell
cd C:\xampp\htdocs\klaes
mkdir folder-watcher
cd folder-watcher
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install watchdog PyYAML
mkdir logs, archive, docs
```

### 5.2 Running the Watcher (Windows)

**From Command Prompt or PowerShell:**

```batch
REM Navigate to watcher directory
cd C:\xampp\htdocs\klaes\folder-watcher

REM Activate virtual environment
venv\Scripts\activate.bat

REM One-time batch correction of existing folders (all configured paths)
python watcher.py --batch

REM Start continuous watching (all configured paths simultaneously)
python watcher.py --watch

REM Watch specific single path only
python watcher.py --watch --path storage/files

REM Dry run (preview changes without executing)
python watcher.py --batch --dry-run

REM Start with debug logging
python watcher.py --watch --debug
```

**View Logs (Windows):**
```batch
REM Open log files in notepad
start notepad logs\corrections.log
start notepad logs\errors.log
start notepad logs\watcher.log

REM Or use PowerShell to tail logs
Get-Content logs\corrections.log -Wait
```

### 5.4 Monitor Multiple Paths Simultaneously

The folder watcher is designed to monitor multiple paths at the same time. All configured paths in `config.yaml` will be watched:

```yaml
watch_paths:
  - storage/app/public/EDMS/BLIND_SCAN
  - storage/files
  - storage/documents
  - storage/archives
  - D:\external\shared\documents
  - E:\backup\archives
```

When you run:
```batch
python watcher.py --watch
```

All 6 paths above will be monitored simultaneously in real-time.

**To monitor only specific paths:**
```batch
REM Monitor only one path temporarily
python watcher.py --watch --path storage/files

REM Or edit config.yaml and comment out other paths:
# watch_paths:
#   - storage/files              # Only this one active
#   - storage/documents          # Commented out
#   - storage/archives           # Commented out
```

### 5.3 Setup as Windows Service (Auto-Start)

**Method 1: Using Windows Task Scheduler (Recommended - No Admin Needed)**

```batch
REM Navigate to watcher directory
cd C:\xampp\htdocs\klaes\folder-watcher

REM Create run_watcher.bat file:
@echo off
cd /d C:\xampp\htdocs\klaes\folder-watcher
venv\Scripts\python.exe watcher.py --watch
pause

REM Then use Windows Task Scheduler:
REM 1. Open Task Scheduler
REM 2. Create Basic Task
REM 3. Name: "Folder Watcher"
REM 4. Trigger: "At startup"
REM 5. Action: Start program "C:\xampp\htdocs\klaes\folder-watcher\run_watcher.bat"
REM 6. Check "Run with highest privileges"
REM 7. Click OK
```

**Method 2: Using NSSM (Non-Sucking Service Manager)**

```batch
REM Download NSSM from https://nssm.cc/download and extract to C:\tools\nssm

REM Install NSSM service (requires admin)
nssm install FolderWatcher "C:\xampp\htdocs\klaes\folder-watcher\venv\Scripts\python.exe" "watcher.py --watch"

REM Set working directory
nssm set FolderWatcher AppDirectory "C:\xampp\htdocs\klaes\folder-watcher"

REM Start service
nssm start FolderWatcher

REM View status
nssm status FolderWatcher

REM Stop service
nssm stop FolderWatcher

REM Remove service (when no longer needed)
nssm remove FolderWatcher confirm
```

### 5.4 Setup as Linux Service (Auto-Start)

```bash
# Create systemd service file
sudo nano /etc/systemd/system/folder-watcher.service

# Content:
[Unit]
Description=KLAES Folder Name Watcher
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/xampp/htdocs/klaes/folder-watcher
ExecStart=/xampp/htdocs/klaes/folder-watcher/venv/bin/python watcher.py --watch
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target

# Save and close (Ctrl+X, Y, Enter)

# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable folder-watcher
sudo systemctl start folder-watcher

# Check status
sudo systemctl status folder-watcher

# View logs
sudo journalctl -u folder-watcher -f
```

### 5.6 Quick Start for Testing (Windows)

```batch
cd C:\xampp\htdocs\klaes\folder-watcher
venv\Scripts\activate.bat

REM Test batch correction of all configured paths
python watcher.py --batch

REM Test with dry-run to preview changes
python watcher.py --batch --dry-run

REM Start watching all configured paths (Ctrl+C to stop)
python watcher.py --watch
```

---

## Part 6: Features & Capabilities

### 6.1 What It Does

✅ **Real-time Monitoring**
- Detects new folder creation instantly in all configured paths
- Watches multiple folders simultaneously in one process
- Watches for folder renames
- Debounces rapid changes

✅ **Automatic Correction**
- Fixes all error types (slashed zeros, spaces, CN→CON, etc.)
- Expands 2-digit years
- Cleans serial numbers
- Normalizes hyphens

✅ **Batch Processing**
- Scan existing folders
- Correct all in one pass
- Generate correction report

✅ **Conflict Resolution**
- Detect duplicate folder names
- Rename old folders with timestamp
- Safe overwrite options

✅ **Comprehensive Logging**
- All changes logged with timestamp
- Separate logs for corrections/errors
- Rotating log files
- Audit trail maintained

✅ **Dry Run Mode**
- Preview changes without executing
- Test before production

✅ **Configurability**
- YAML-based configuration
- Multiple watch paths
- Custom ignore patterns
- Notification settings

### 6.2 Example Log Output

```
2025-12-06 10:15:23 - Folder Watcher Started
2025-12-06 10:15:23 - Watching: /xampp/htdocs/klaes/storage/files
2025-12-06 10:16:45 - Detected new folder: C0M-RES-1992-1234
2025-12-06 10:16:46 - ✓ Renamed: C0M-RES-1992-1234 → COM-RES-1992-1234
2025-12-06 10:17:12 - Detected new folder: RES--1992--1176
2025-12-06 10:17:13 - ✓ Renamed: RES--1992--1176 → RES-1992-1176
2025-12-06 10:18:30 - Detected new folder: COM - RES - 1992 - 123
2025-12-06 10:18:31 - ✓ Renamed: COM - RES - 1992 - 123 → COM-RES-1992-123
```

---

## Part 7: Implementation Details

### **Python Watchdog Solution for Windows**

**Why Python with Watchdog for Windows:**
1. ✅ Real-time file system monitoring optimized for Windows
2. ✅ Monitor multiple paths simultaneously in one process
3. ✅ Easy to understand and maintain
4. ✅ Minimal dependencies (watchdog + PyYAML)
5. ✅ Can run as Windows background service via Task Scheduler or NSSM
6. ✅ Highly configurable with YAML
7. ✅ Comprehensive logging capabilities
8. ✅ Windows-only focused - no Linux/macOS complexity

**Timeline:**
- Setup & Installation: 30 minutes
- Configuration: 15 minutes (add/configure multiple paths)
- Testing: 1 hour
- Production deployment: 30 minutes
- **Total: ~2.25 hours**

---

## Part 8: Monitoring & Logs UI Dashboard

### 8.1 Overview

A web-based monitoring dashboard provides real-time visibility into the folder watcher system. This allows administrators and staff to:
- View all folder corrections in real-time
- Monitor watcher status and health
- Analyze error patterns
- Audit all changes with full history
- Configure alerts and notifications
- Generate reports

### 8.2 Dashboard Architecture

**Technology Stack:**
- **Backend:** Laravel API endpoints (PHP) - read log files and provide data
- **Frontend:** Blade + Tailwind + Alpine.js - interactive dashboard
- **Data Source:** JSON logs from Python folder watcher

**Location:** `/resources/views/folder-watcher/` (Laravel views)

### 8.3 Key Dashboard Pages

#### **Page 1: Live Activity Stream**

**Purpose:** Real-time view of all folder corrections happening now

**Features:**
- Live feed of folder renames (refresh every 5 seconds or WebSocket)
- Shows: Original name → Corrected name, Timestamp, Error type, Status
- Filter by:
  - Date range
  - Error type (ERR001-ERR017)
  - Watch path
  - Status (Success, Failed, Skipped)
- Color coding:
  - ✅ Green = Successful correction
  - ⚠️ Yellow = Warning/Conflict handled
  - ❌ Red = Error/Failed

**Sample Display:**
```
Live Activity - Folder Watcher
─────────────────────────────────────────────────────────────────

2025-12-06 14:23:45  ✅  C0M-RES-1992-1234 → COM-RES-1992-1234  [ERR011]
2025-12-06 14:22:18  ✅  RES--1992--1176 → RES-1992-1176        [ERR016]
2025-12-06 14:21:03  ✅  COM - RES - 1992 - 123 → COM-RES-1992-123  [ERR017]
2025-12-06 14:19:50  ⚠️  RES-1992-1234 (exists) → Renamed to RES-1992-1234_backup_20251206_141950
2025-12-06 14:18:22  ✅  ST-RES-2025-001 (no change, already correct)
```

#### **Page 2: Corrections Summary**

**Purpose:** Aggregated view of all corrections with statistics

**Features:**
- Total corrections today/this week/this month
- Top 10 most common errors:
  - Error type, Count, Examples
  - Visual bar chart
- Corrections by watch path:
  - Path name, Count, Success rate %
- Timeline graph:
  - Corrections over time (hourly, daily, weekly)
- Success rate:
  - % of corrections that succeeded vs failed

**Sample Display:**
```
Summary Dashboard - Folder Watcher
─────────────────────────────────────────────────────────────────

STATISTICS:
  Total Corrections: 1,247 (This Month)
  Success Rate: 98.5%
  Failed/Errors: 19
  
TOP ERRORS THIS MONTH:
  1. ERR016 (Multiple hyphens)      → 342 corrections
  2. ERR001 (Slashed zero)          → 289 corrections
  3. ERR017 (Spaces around hyphen)  → 186 corrections
  4. ERR002 (CN → CON prefix)       → 145 corrections
  5. ERR003 (2-digit year)          → 107 corrections

CORRECTIONS BY WATCH PATH:
  storage/app/public/EDMS/BLIND_SCAN  → 1,087 (97.2%)
  storage/files                        → 89    (2.1%)
  storage/documents                    → 45    (0.7%)

TIMELINE (This Week):
  Monday:    183 corrections
  Tuesday:   201 corrections
  Wednesday: 189 corrections
  Thursday:  195 corrections
  Friday:    179 corrections
  Saturday:  87 corrections
  Sunday:    34 corrections
```

#### **Page 3: Detailed Logs**

**Purpose:** Full detailed log view with search and filtering

**Features:**
- Paginated log entries (100 per page)
- Search box:
  - Search by original folder name
  - Search by corrected name
  - Search by file number
- Filter by:
  - Error code (dropdown)
  - Date range (calendar picker)
  - Watch path (dropdown)
  - Status (Success/Failed/Skipped)
- Sort by: Timestamp, Error Type, Path
- Export options:
  - CSV export
  - JSON export
  - PDF report
- Column display:
  - Timestamp
  - Original Name
  - Corrected Name
  - Error Code
  - Status
  - Corrections Applied
  - Path
  - Details

**Sample Display:**
```
Detailed Logs - Folder Watcher
─────────────────────────────────────────────────────────────────

[Search] _________________ [Filter by Error] [Filter by Date] [Export CSV]

Timestamp              | Original              | Corrected            | Error | Status
─────────────────────────────────────────────────────────────────────────────────────
2025-12-06 14:23:45   | C0M-RES-1992-1234    | COM-RES-1992-1234   | ERR011| ✅
2025-12-06 14:22:18   | RES--1992--1176      | RES-1992-1176       | ERR016| ✅
2025-12-06 14:21:03   | COM - RES - 1992 - 123| COM-RES-1992-123   | ERR017| ✅
2025-12-06 14:19:50   | RES-1992-1234        | RES-1992-1234_backup| CONFLICT| ⚠️
2025-12-06 14:18:22   | ST-RES-2025-001      | ST-RES-2025-001     | NONE  | ⏭️
```

#### **Page 4: Error Analysis**

**Purpose:** Deep dive into error patterns

**Features:**
- Error type breakdown (all 17 error codes):
  - Count, Percentage, Examples
  - Visual pie chart or bar chart
- Error trends:
  - Which errors are increasing/decreasing
  - Historical comparison (this week vs last week)
- Most problematic paths:
  - Which watch path has most errors
  - Error distribution by path
- Time analysis:
  - When errors occur most (hour of day, day of week)
  - Peak correction times
- Root cause analysis:
  - Manual entry mistakes vs OCR errors

**Sample Display:**
```
Error Analysis - Folder Watcher
─────────────────────────────────────────────────────────────────

ERROR TYPE DISTRIBUTION (This Month):
  ERR016 (Multiple hyphens)          27.4%  ████████████
  ERR001 (Slashed zero)              23.2%  ██████████
  ERR017 (Spaces around hyphen)      14.9%  ███████
  ERR002 (CN → CON prefix)           11.6%  █████
  ERR003 (2-digit year)              8.6%   ████
  Others                             14.3%  ██████

ERRORS BY WATCH PATH:
  storage/app/public/EDMS/BLIND_SCAN → 1,087 errors (87.2%)
  storage/files                       → 89 errors (7.1%)
  storage/documents                   → 71 errors (5.7%)

PEAK ERROR TIMES:
  Monday-Friday 9-10am: 234 corrections
  Monday-Friday 2-3pm:  198 corrections
  Saturday/Sunday: 87 corrections
```

#### **Page 5: System Health & Status**

**Purpose:** Monitor watcher service health

**Features:**
- Service status:
  - Running / Stopped / Error
  - Uptime
  - Last restart
- Performance metrics:
  - CPU usage
  - Memory usage
  - Disk space (logs directory)
  - Average correction time
- Log file status:
  - Size of log files
  - Rotation status
  - Archive folder size
- Configuration:
  - Currently watched paths
  - Watch mode (Batch / Watch / Scheduled)
  - Last batch run details
- Alerts:
  - Errors in last 24 hours
  - Warnings
  - Service down alerts

**Sample Display:**
```
System Health - Folder Watcher
─────────────────────────────────────────────────────────────────

SERVICE STATUS:
  Status: ✅ Running
  Uptime: 23 days, 14 hours, 32 minutes
  Last Restart: 2025-11-12 08:15:00
  Process ID: 8192
  
PERFORMANCE:
  CPU Usage: 0.2%
  Memory Usage: 45.6 MB
  Avg Correction Time: 234ms
  Corrections/Hour: ~45
  
LOG FILES:
  watcher.log: 12.4 MB
  corrections.log: 23.7 MB
  errors.log: 0.2 MB
  Archive Folder: 156 MB (36 files)
  
WATCHED PATHS:
  1. storage/app/public/EDMS/BLIND_SCAN ✅ Active
  2. storage/files ✅ Active
  3. storage/documents ✅ Active
  
ALERTS (Last 24h):
  ℹ️ 2025-12-06 10:30 - 15 conflicts handled (auto-resolved)
  ⚠️ 2025-12-06 02:15 - Disk space low in logs (170GB available)
```

#### **Page 6: Audit Trail & History**

**Purpose:** Complete audit log of every action

**Features:**
- All corrections with complete details
- Who triggered the correction (user if manual, system if auto)
- Before/after comparison
- Ability to see what corrections were applied
- Undo capability (if configured):
  - Show "Undo" button for recent corrections
  - Restore original folder name
- Batch run history:
  - When batch runs happened
  - How many folders processed
  - Success/failure count
- Conflict resolution log:
  - All conflicts encountered
  - How they were resolved
  - Backup locations

**Sample Display:**
```
Audit Trail - Folder Watcher
─────────────────────────────────────────────────────────────────

[Show Advanced Filters] [View Undo History]

Date            | Action              | Original              | Corrected           | Corrections    | Details
────────────────────────────────────────────────────────────────────────────────────────────────────────
2025-12-06 14:23| Auto-Correct        | C0M-RES-1992-1234    | COM-RES-1992-1234  | C0M→COM        | [View] [Undo]
2025-12-06 14:22| Auto-Correct        | RES--1992--1176      | RES-1992-1176      | --→-           | [View] [Undo]
2025-12-06 14:21| Auto-Correct        | COM - RES - 1992 - 123| COM-RES-1992-123  | Remove spaces  | [View] [Undo]
2025-12-06 14:19| Conflict Resolved   | RES-1992-1234        | RES-1992-1234_b... | Renamed old    | [View]
2025-12-06 14:18| Verify Pass         | ST-RES-2025-001      | ST-RES-2025-001    | None           | [View]
```

### 8.4 API Endpoints for Dashboard

The Laravel backend should expose these endpoints:

```
GET  /api/folder-watcher/stats                 # Summary statistics
GET  /api/folder-watcher/logs                  # Paginated logs with filtering
GET  /api/folder-watcher/logs/search           # Search logs
GET  /api/folder-watcher/logs/export           # Export logs (CSV/JSON/PDF)
GET  /api/folder-watcher/status                # System health/status
GET  /api/folder-watcher/errors/analysis       # Error analysis data
GET  /api/folder-watcher/errors/by-type        # Breakdown by error type
GET  /api/folder-watcher/audit-trail           # Complete audit log
POST /api/folder-watcher/undo/{log_id}         # Undo a correction
GET  /api/folder-watcher/live-stream           # WebSocket or polling endpoint
```

### 8.5 Features & Capabilities

**Real-Time Updates:**
- WebSocket connection for live activity feed
- Fallback to polling if WebSocket unavailable
- Update every 5 seconds for summary statistics

**Data Export:**
- CSV export with all log details
- JSON export for programmatic access
- PDF reports with charts and summaries
- Scheduled email reports (daily/weekly)

**Notifications & Alerts:**
- Email notifications for critical errors
- In-app notifications (toast/banner)
- Dashboard notifications badge
- Alert configuration by severity level

**Access Control:**
- Admin-only access to full dashboard
- Read-only access for staff
- Audit log shows who viewed what

---

## Part 9: Testing Plan

### 9.1 Test Scenarios

```
Test 1: Slashed Zero
  Input:  C∅M-RES-1992-1234
  Expected: COM-RES-1992-1234
  Status: ✅

Test 2: CN Prefix
  Input:  CN-RES-1992-345
  Expected: CON-RES-1992-345
  Status: ✅

Test 3: Multiple Hyphens
  Input:  RES--1992--1176
  Expected: RES-1992-1176
  Status: ✅

Test 4: Spaces Everywhere
  Input:  C O M - R E S - 1 9 9 2 - 1 2 3
  Expected: COM-RES-1992-123
  Status: ✅

Test 5: 2-Digit Year
  Input:  RES-92-1234
  Expected: RES-1992-1234
  Status: ✅

Test 6: Already Correct
  Input:  RES-1992-1176
  Expected: RES-1992-1176 (no change)
  Status: ✅

Test 7: Conflict Resolution
  Input:  Two folders want same name
  Expected: Old renamed with timestamp
  Status: ✅
```

### 9.2 Dry Run Test

```bash
# Run in dry run mode first
# Edit config.yaml: dry_run: true
python watcher.py --watch

# Check logs to see what WOULD happen
# Then disable dry_run and run again
```

---

## Part 9: Maintenance & Monitoring

### 10.1 Regular Tasks

**Daily:**
- Check error logs for failures
- Monitor disk space for logs

**Weekly:**
- Review corrections.log for trends
- Check if any patterns missed

**Monthly:**
- Archive old logs
- Analyze correction statistics
- Update config if needed

### 10.2 Statistics Reporting

```python
# Generate report of corrections
def generate_report():
    """Generate correction statistics"""
    
    corrections = load_all_corrections()
    
    report = {
        'total_corrections': len(corrections),
        'by_type': count_by_type(corrections),
        'by_error': count_by_error(corrections),
        'by_date': count_by_date(corrections),
        'most_common_errors': find_top_errors(corrections)
    }
    
    return report
```

---

## Part 10: Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Watcher not detecting folders | Path not watched correctly | Check config.yaml watch_paths |
| Permission denied errors | Insufficient folder permissions | Run with proper permissions |
| Folders renamed multiple times | Debounce too low | Increase debounce_ms in config |
| High CPU usage | Watching too many paths | Reduce watch_paths or use ignore patterns |
| Changes not persisting | Dry run mode enabled | Set dry_run: false in config |

---

## Summary

This Python-based folder watcher system will:
1. **Automatically detect** incorrect folder names
2. **Correct them in real-time** as they're created
3. **Handle existing folders** in batch mode
4. **Maintain audit trail** of all changes
5. **Resolve conflicts** safely
6. **Run as a background service** continuously

**Next Steps:**
1. Confirm this approach works for you
2. Provide exact folder paths to watch
3. Customize config.yaml for your environment
4. Set up as background service
5. Monitor logs and refine rules

---

**Questions to Clarify:**
1. What are the exact folder paths to monitor?
2. Should the service run continuously or on schedule?
3. Any specific folder structure patterns?
4. Should it integrate with database updates?
5. Email notifications for errors?

