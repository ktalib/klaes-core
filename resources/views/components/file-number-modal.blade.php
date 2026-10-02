<!-- Global File Number Modal Component -->
<div id="globalFileNumberModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="fileNumberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fileNumberModalLabel">File Number Selector</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" id="fileNumberTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="mls-tab" data-toggle="tab" href="#mls" role="tab">MLPP Format</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="kangis-tab" data-toggle="tab" href="#kangis" role="tab">KANGIS Format</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="newkangis-tab" data-toggle="tab" href="#newkangis" role="tab">New KANGIS Format</a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content mt-3" id="fileNumberTabContent">
                    <!-- MLS Format Tab -->
                    <div class="tab-pane fade show active" id="mls" role="tabpanel">
                        <div class="row mb-3">
                            <!-- Smart Selector -->
                            <div class="col-12 mb-3">
                                <label>Select Existing MLPP File Number</label>
                                <select class="form-control file-number-select" data-type="mls"></select>
                            </div>
                            
                            <!-- Manual Entry -->
                            <div class="col-12">
                                <label>Manual Entry</label>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>File Type</label>
                                            <select class="form-control" id="mlsType">
                                                <option value="regular">Regular</option>
                                                <option value="temporary">Temporary</option>
                                                <option value="extension">Extension</option>
                                                <option value="miscellaneous">Miscellaneous</option>
                                                <option value="sit">SIT</option>
                                                <option value="sltr">SLTR</option>
                                            </select>
                                        </div>
                                        
                                        <div id="mlsRegularFields">
                                            <div class="form-row">
                                                <div class="col">
                                                    <label>Prefix</label>
                                                    <input type="text" class="form-control" id="mlsPrefix">
                                                </div>
                                                <div class="col">
                                                    <label>Year</label>
                                                    <input type="text" class="form-control" id="mlsYear" value="{{ date('Y') }}">
                                                </div>
                                                <div class="col">
                                                    <label>Serial</label>
                                                    <input type="text" class="form-control" id="mlsSerial">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview -->
                        <div class="preview-section">
                            <label>Preview</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="mlsPreview" readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary copy-btn" type="button" data-preview="mlsPreview">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KANGIS Format Tab -->
                    <div class="tab-pane fade" id="kangis" role="tabpanel">
                        <div class="row mb-3">
                            <!-- Smart Selector -->
                            <div class="col-12 mb-3">
                                <label>Select Existing KANGIS File Number</label>
                                <select class="form-control file-number-select" data-type="kangis"></select>
                            </div>
                            
                            <!-- Manual Entry -->
                            <div class="col-12">
                                <label>Manual Entry</label>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="form-row">
                                            <div class="col">
                                                <label>Prefix</label>
                                                <input type="text" class="form-control" id="kangisPrefix">
                                            </div>
                                            <div class="col">
                                                <label>Number</label>
                                                <input type="text" class="form-control" id="kangisNumber">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview -->
                        <div class="preview-section">
                            <label>Preview</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="kangisPreview" readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary copy-btn" type="button" data-preview="kangisPreview">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New KANGIS Format Tab -->
                    <div class="tab-pane fade" id="newkangis" role="tabpanel">
                        <div class="row mb-3">
                            <!-- Smart Selector -->
                            <div class="col-12 mb-3">
                                <label>Select Existing New KANGIS File Number</label>
                                <select class="form-control file-number-select" data-type="newkangis"></select>
                            </div>
                            
                            <!-- Manual Entry -->
                            <div class="col-12">
                                <label>Manual Entry</label>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="form-row">
                                            <div class="col">
                                                <label>Prefix</label>
                                                <input type="text" class="form-control" id="newKangisPrefix">
                                            </div>
                                            <div class="col">
                                                <label>Number</label>
                                                <input type="text" class="form-control" id="newKangisNumber">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview -->
                        <div class="preview-section">
                            <label>Preview</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="newKangisPreview" readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary copy-btn" type="button" data-preview="newKangisPreview">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Selections -->
                <div class="mt-4">
                    <h6>Recent Selections</h6>
                    <div class="recent-selections" id="recentSelections"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyFileNumber">Apply</button>
            </div>
        </div>
    </div>
</div>
