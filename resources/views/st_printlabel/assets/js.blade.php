<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const LABEL_CAPACITY = 999999;
    // Per-application-type shelf capacity (must mirror StPrintLabelController::RACK_SHELF_CAPACITY_BY_TYPE).
    const LABEL_CAPACITY_BY_TYPE = { primary: 25, pua: 50, sua: 25 };
    function capacityForType(t){ t=(t||'').toString().toLowerCase().trim(); return LABEL_CAPACITY_BY_TYPE[t]||LABEL_CAPACITY; }
    const ST_PREFIX = 'ST';

    let state = {
        selectedFiles: [], labelSize: "30-in-1", labelFormat: "qrcode", activeTab: "files",
        copies: 1, selectedTemplate: "30-in-1", searchTerm: "", orientation: "portrait",
        showAdvancedOptions: false, loadedFromBatch: false, rackPrimary: 'A', rackSecondary: '',
        shelfNumber: '1', fullLabel: 'A1', availableFiles: [], generatedBatches: [], loading: false,
        currentBatchId: null, preparedRecords: null, preparedBaseEntries: null,
        preparedSignature: null, previewPrepared: false, preparedBatchResponse: null,
        previewInFlight: false, previewPromise: null, labelStatistics: null, rackLabelStatus: null,
        excludeAssignedFromBatch: false, reprintMode: false, batchSearchQuery: '',
        batchPagination: { current_page:1, last_page:1, per_page:20, total:0 },
        stPrefix: ST_PREFIX, stApplicationType: '', shelvesUsed: [],
    };

    const PRINT_TEMPLATE_URL = '/st-printlabel/print-template';
    const API = {
        prefixes:        '/st-printlabel/api/prefixes',
        applicationTypes:'/st-printlabel/api/application-types',
        prefixNextRange: '/st-printlabel/api/prefix-next-range',
        files:           '/st-printlabel/api/files',
        rackStatus:      '/st-printlabel/api/rack-label/status',
        createBatch:     '/st-printlabel/api/batch',
        batches:         '/st-printlabel/api/batches',
        batchBase:       '/st-printlabel/api/batch/',
    };

    function coalesce() {
        for (var i = 0; i < arguments.length; i++) {
            var v = arguments[i];
            if (v !== undefined && v !== null) {
                var s = v.toString().trim();
                if (s !== '' && s.toUpperCase() !== 'NULL') return s;
            }
        }
        return '';
    }
    function csrfToken() { return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); }
    function resetPreparedState(o) {
        o = o || {};
        state.preparedRecords = null; state.preparedBaseEntries = null; state.preparedSignature = null;
        state.previewPrepared = false; state.preparedBatchResponse = null; state.previewPromise = null;
        state.previewInFlight = false; state.labelStatistics = null;
        state.shelvesUsed = [];
        if (typeof updateSpilloverNotice === 'function') updateSpilloverNotice();
        updateRackStatisticsDisplay(null);
        if (!o.preserveBatchId) state.currentBatchId = null;
        if (!o.preserveReprint) state.reprintMode = false;
    }
    function showError(m) { Swal.fire({icon:'error',title:'Error',text:m,confirmButtonColor:'#3b82f6'}); console.error(m); }
    function showSuccess(m) { Swal.fire({icon:'success',title:'Success',text:m,timer:3000,showConfirmButton:false}); }
    function showLoading(m) { Swal.fire({title:'Please wait...',text:m||'Loading...',allowOutsideClick:false,allowEscapeKey:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}}); state.loading=true; }
    function hideLoading() { if(state.loading){Swal.close();state.loading=false;} }

    function updateFullLabelDisplay() {
        var p = (state.rackPrimary||'').toUpperCase().trim();
        var sec = (state.rackSecondary||'').toUpperCase().trim();
        var s = (state.shelfNumber||'').toString().trim();
        var l = p + sec + s;
        state.fullLabel = l;
        var el = document.getElementById('fullLabelInput'); if(el) el.value = l;
        var lv = document.getElementById('fullLabelValue'); if(lv) lv.textContent = l || '\u2014';
        return l;
    }
    function updateRackLabelStatusDisplay() {
        var ce = document.getElementById('rackLabelCounterDisplay');
        var se = document.getElementById('rackLabelStatusText');
        if (!ce||!se) return;
        var st = state.rackLabelStatus;
        if (!st) { ce.textContent='0'; se.textContent='Awaiting selection'; se.className='text-slate-500'; return; }
        var c = Math.max(0,parseInt(st.counter)||0);
        var cap = Number(st.capacity)||capacityForType(state.stApplicationType);
        var remaining = Math.max(0, cap - c);
        ce.textContent = c + ' / ' + cap;
        if (remaining <= 0) {
            se.textContent = 'Shelf full — choose another shelf';
            se.className = 'text-red-600 font-medium';
        } else if (remaining <= Math.ceil(cap*0.2)) {
            se.textContent = remaining + ' slot' + (remaining===1?'':'s') + ' remaining';
            se.className = 'text-amber-600 font-medium';
        } else {
            se.textContent = remaining + ' slot' + (remaining===1?'':'s') + ' remaining';
            se.className = 'text-slate-500';
        }
    }
    function updateSpilloverNotice() {
        var el = document.getElementById('shelfSpilloverNotice');
        if (!el) return;
        var used = Array.isArray(state.shelvesUsed) ? state.shelvesUsed : [];
        if (used.length <= 1) { el.style.display='none'; el.textContent=''; return; }
        var parts = used.map(function(s){ return s.shelf + ' (' + s.count + ')'; });
        el.style.display = '';
        el.textContent = 'Files exceeded one shelf and were spread across ' + used.length + ' shelves: ' + parts.join(', ') + '.';
    }
    async function fetchRackLabelStatus(label) {
        var n=(label||'').trim(); if(!n){state.rackLabelStatus=null;updateRackLabelStatusDisplay();return;}
        try{var params={full_label:n};if(state.stApplicationType)params.application_type=state.stApplicationType;var r=await fetch(API.rackStatus+'?'+new URLSearchParams(params));if(!r.ok)throw new Error('err');var d=await r.json();if(d.success){state.rackLabelStatus=d.data;updateRackLabelStatusDisplay();}}
        catch(e){console.error('Rack status error:',e);state.rackLabelStatus=null;updateRackLabelStatusDisplay();}
    }

    function normalizeLocationValue(raw) {
        if(!raw||raw==='null')return'Shelf/Rack-N/A';var v=String(raw).trim();if(!v)return'Shelf/Rack-N/A';
        return /^shelf\/?rack/i.test(v)?v.replace(/\s+/g,' '):'Shelf/Rack-'+v;
    }
    function getDisplayShelfValue(primary,secondary) {
        var c=[primary,secondary].map(function(v){return coalesce(v).toString().trim();}).filter(function(v){if(!v)return false;var u=v.toUpperCase();return u!=='N/A'&&u!=='SHELF/RACK-N/A'&&u!=='NULL';});
        if(!c.length)return'N/A';return c[0].replace(/^(Shelf\/Rack[-:\s]*)/i,'').trim()||'N/A';
    }
    function pickFirstNonEmpty(vals) {
        if(!Array.isArray(vals))return'';for(var i=0;i<vals.length;i++){var c=vals[i];if(c===undefined||c===null)continue;var n=c.toString().trim();if(n!=='')return n;}return'';
    }
    function getFileNumberSortKey(p) {
        if(!p||typeof p!=='object')return'';return pickFirstNonEmpty([p.primaryFileNumber,p.primary_number,p.np_file_number,p.fileNumber,p.file_number,p.originalFileNumber]).toUpperCase();
    }
    function sortByFileNumber(list) {
        if(!Array.isArray(list)||list.length<2)return list?list.slice():[];
        return list.map(function(item,i){return{item:item,i:i,key:getFileNumberSortKey(item)};})
            .sort(function(a,b){if(a.key&&!b.key)return-1;if(!a.key&&b.key)return 1;if(!a.key&&!b.key)return a.i-b.i;
                try{var c=a.key.localeCompare(b.key,undefined,{numeric:true,sensitivity:'base'});return c!==0?c:a.i-b.i;}catch(e){return a.key<b.key?-1:(a.key>b.key?1:a.i-b.i);}})
            .map(function(e){return e.item;});
    }
    function buildShelfDisplayLabel() {
        var flat=[];Array.prototype.slice.call(arguments).forEach(function(v){if(Array.isArray(v))v.forEach(function(x){flat.push(x);});else flat.push(v);});
        var c=flat.map(function(v){return coalesce(v).toString().trim();}).filter(function(v){if(!v)return false;var u=v.toUpperCase();return u!=='N/A'&&u!=='SHELF/RACK-N/A'&&u!=='NULL';});
        if(!c.length)return'';return c[0].replace(/^Shelf\/?Rack[-:\s]*/i,'').trim()||'';
    }
    function deriveFileNumbers(file) {
        if(!file||typeof file!=='object')return{primaryNumber:null,secondaryNumber:null,isSTContext:true};
        var idxFn=(file.file_number!=null)?String(file.file_number).trim():'';
        var np=(file.np_file_number!=null)?String(file.np_file_number).trim():'';
        var appFn=(file.application_file_number!=null)?String(file.application_file_number).trim():'';
        // Top line: new primary file number (np_fileno) when present; fall back to file_indexings file_number.
        var primary=np||idxFn||appFn||null;
        // Bottom line: application table's fileno (mother/sub) when different from the primary.
        // Fall back to file_indexings.file_number if no application linkage was found.
        var secondaryCandidate=appFn||idxFn||'';
        var secondary=null;
        if(primary&&secondaryCandidate&&primary.toUpperCase()!==secondaryCandidate.toUpperCase())secondary=secondaryCandidate;
        return{primaryNumber:primary,secondaryNumber:secondary,isSTContext:true};
    }

    function computePreparationSignature() {
        var ids=state.selectedFiles.map(function(v){return v!=null?v.toString():'';}).filter(Boolean).sort();
        return JSON.stringify({selectedIds:ids,fullLabel:state.fullLabel,selectedTemplate:state.selectedTemplate,orientation:state.orientation,stPrefix:state.stPrefix,stApplicationType:state.stApplicationType});
    }

    function buildPreparedEntriesFromItems(items) {
        if(!Array.isArray(items)||!items.length)return{records:[],baseEntries:[]};
        var byId=new Map(state.availableFiles.map(function(f){return[String(f.id),f];}));
        var byFn=new Map(state.availableFiles.map(function(f){var k=coalesce(f.file_number).trim();return k?[k,f]:null;}).filter(Boolean));
        var raw=items.map(function(item,idx){
            var ni=item?Object.assign({},item):{};
            if(ni.qr_code_data&&typeof ni.qr_code_data==='string'){try{ni.qr_code_data=JSON.parse(ni.qr_code_data);}catch(e){ni.qr_code_data={};}}
            var qd=(ni.qr_code_data&&typeof ni.qr_code_data==='object')?ni.qr_code_data:{};
            var rawId=coalesce(ni.file_indexing_id,ni.id,'batch-item-'+idx);
            var base=byId.get(String(rawId))||(ni.file_number?byFn.get(String(ni.file_number).trim()):undefined)||{};
            var combined=Object.assign({},base,ni);combined.id=rawId;
            var sc=coalesce(combined.shelf_label,combined.shelf_value,combined.shelf_location,base.shelf_label,base.shelf_value,base.shelf_location);
            var ns=sc?normalizeLocationValue(sc):'Shelf/Rack-N/A';
            var sv=getDisplayShelfValue(ns,sc);combined.shelf_value=coalesce(sv,combined.shelf_value);
            var tid=coalesce(combined.tracking_id,qd.tracking_id,base.tracking_id);
            var qv=coalesce(combined.qr_value,qd.tracking_id,tid,combined.file_number,base.file_number);
            return Object.assign({},combined,{shelf_label:ns,shelf_value:sv||'N/A',tracking_id:tid,qr_value:qv,qr_code_data:qd});
        });
        var records=sortByFileNumber(raw);
        var baseEntries=records.map(function(rec){
            var d=deriveFileNumbers(rec);var tid=coalesce(rec.tracking_id,rec.qr_value,rec.file_number).toString();
            return{id:rec.id,fileNumber:d.primaryNumber||rec.file_number||null,primaryFileNumber:d.primaryNumber||rec.file_number||null,
                secondaryFileNumber:d.secondaryNumber||null,originalFileNumber:rec.file_number||null,isSTFile:true,
                shelfLabel:rec.shelf_label||'Shelf/Rack-N/A',shelfValue:rec.shelf_value||'N/A',
                trackingId:tid,fileTitle:rec.file_title||'',qrValue:rec.qr_value||tid||rec.file_number||''};
        });
        return{records:records,baseEntries:baseEntries};
    }

    async function preparePreviewDataIfNeeded(force) {
        if(state.reprintMode&&state.currentBatchId&&state.preparedBatchResponse)return state.preparedBatchResponse;
        var sig=computePreparationSignature();
        if(!force&&state.previewPrepared&&state.preparedSignature===sig&&Array.isArray(state.preparedBaseEntries)&&state.preparedBaseEntries.length)return state.preparedBatchResponse;
        if(state.previewInFlight&&state.previewPromise)return state.previewPromise;
        state.previewInFlight=true;
        var promise=persistBatchForPrinting().then(function(result){
            var li=(result&&result.data&&Array.isArray(result.data.label_items))?result.data.label_items:[];
            var built=buildPreparedEntriesFromItems(li);
            state.preparedRecords=built.records;state.preparedBaseEntries=built.baseEntries;
            state.previewPrepared=true;state.preparedSignature=sig;state.preparedBatchResponse=result;
            state.labelStatistics=(result&&result.data&&result.data.label_statistics)?result.data.label_statistics:null;
            var rd=result&&result.data?result.data:null;
            if(rd&&rd.rack_label_status){state.rackLabelStatus=rd.rack_label_status;updateRackLabelStatusDisplay();}
            state.shelvesUsed=(rd&&Array.isArray(rd.shelves_used))?rd.shelves_used:[];
            updateSpilloverNotice();
            state.previewInFlight=false;state.previewPromise=null;
            if(rd&&rd.batch_id)state.currentBatchId=rd.batch_id;
            if(rd&&rd.source!=='existing')state.reprintMode=false;
            return result;
        }).catch(function(err){state.previewInFlight=false;state.previewPromise=null;resetPreparedState();throw err;});
        state.previewPromise=promise;return promise;
    }

    function refreshPreview(force) {
        if(!state.selectedFiles.length&&!state.reprintMode){updatePreview();return;}
        preparePreviewDataIfNeeded(!!force).then(function(){updatePreview();}).catch(function(e){console.error('Preview failed:',e);showError(e.message||'Preview failed.');updatePreview();});
    }

    // ---- ST file loading ----
    function applicationTypeLabel(type){
        switch((type||'').toLowerCase()){
            case 'primary': return 'Primary Application';
            case 'pua':     return 'PUA';
            case 'sua':     return 'SUA';
            default:        return type||'';
        }
    }
    async function loadStFiles() {
        var appType=state.stApplicationType;
        if(!appType){showError('Please select an ST application type.');return;}
        var label=applicationTypeLabel(appType);
        showLoading('Loading ST files...');
        try{
            var params=new URLSearchParams({prefix:ST_PREFIX,application_type:appType,search:state.searchTerm||''});
            if(state.excludeAssignedFromBatch)params.append('exclude_assigned','true');
            var r=await fetch(API.files+'?'+params);
            if(!r.ok){var t=await r.text();throw new Error('Server responded with '+r.status+'. '+t.substring(0,120));}
            var data=await r.json();if(!data.success)throw new Error(data.message||'Unable to fetch files.');
            resetPreparedState();state.loadedFromBatch=true;
            var files=(data.data&&Array.isArray(data.data.files))?data.data.files:[];
            state.availableFiles=files;state.selectedFiles=files.map(function(f){return f.id;});
            renderFileList();updateCounts();updateSelectAllCheckbox();
            hideLoading();
            if(files.length===0)showError('No indexed ST files found for application type '+label+'.');
            else showSuccess('Loaded '+files.length+' file'+(files.length===1?'':'s')+' for application type '+label+'.');
            if(state.activeTab==='preview')refreshPreview(true);
        }catch(err){hideLoading();showError('Failed to load files: '+(err.message||err));}
    }

    // ---- Batch persistence ----
    async function persistBatchForPrinting() {
        if(state.reprintMode&&state.currentBatchId&&state.preparedBatchResponse)return Promise.resolve(state.preparedBatchResponse);
        var sel=getSelectedFilesData();if(!sel.length)throw new Error('Please select at least one file before printing.');
        var payload={prefix:ST_PREFIX,application_type:state.stApplicationType,file_ids:sel.map(function(r){return Number(r.id);}),
            full_label:state.fullLabel||updateFullLabelDisplay(),rack_primary:state.rackPrimary,
            rack_secondary:state.rackSecondary||null,shelf_number:parseInt(state.shelfNumber,10),
            label_format:state.selectedTemplate,orientation:state.orientation};
        showLoading('Saving batch details...');
        try{
            var ctrl=new AbortController();var tid=setTimeout(function(){ctrl.abort();},60000);
            var r=await fetch(API.createBatch,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken()},body:JSON.stringify(payload),signal:ctrl.signal});
            clearTimeout(tid);if(!r.ok){var t=await r.text();throw new Error('Server error ('+r.status+'): '+t.substring(0,200));}
            var data=await r.json();hideLoading();if(!data.success)throw new Error(data.message||'Unable to create batch.');return data;
        }catch(err){hideLoading();if(err.name==='AbortError')throw new Error('Request timed out.');throw err;}
    }

    function getSelectedFilesData() {
        return state.selectedFiles.map(function(id){return state.availableFiles.find(function(f){return f.id===id;});}).filter(Boolean);
    }
    function collectBaseLabelEntries() {
        if(Array.isArray(state.preparedBaseEntries)&&state.preparedBaseEntries.length)return sortByFileNumber(state.preparedBaseEntries.map(function(e){return Object.assign({},e);}));
        var entries=[],files=getSelectedFilesData(),fl=(state.fullLabel||updateFullLabelDisplay()||'').toString().trim(),fnS=fl?normalizeLocationValue(fl):';';
        files.forEach(function(file){
            var sL=fnS||normalizeLocationValue(file.shelf_location||'')||'Shelf/Rack-N/A';
            var sV=fl||getDisplayShelfValue(sL,file.shelf_location)||'N/A';
            var tid=coalesce(file.tracking_id,'').toString();if(!tid)tid=file.id?('IDX-'+file.id):('IDX-'+Math.random().toString(36).slice(2,8));
            var d=deriveFileNumbers(file);
            entries.push({id:file.id,fileNumber:d.primaryNumber||file.file_number||'',primaryFileNumber:d.primaryNumber||file.file_number||null,
                secondaryFileNumber:d.secondaryNumber||null,originalFileNumber:file.file_number,isSTFile:true,shelfLabel:sL,shelfValue:sV,trackingId:tid,fileTitle:file.file_title||'',qrValue:tid});
        });
        return sortByFileNumber(entries);
    }
    function buildLabelPayload() {
        var base=collectBaseLabelEntries(),copies=Math.max(1,parseInt(state.copies||1,10)),labels=[];
        base.forEach(function(e){for(var c=0;c<copies;c++){labels.push({file_number:e.fileNumber,primary_number:e.primaryFileNumber||e.fileNumber,secondary_number:e.secondaryFileNumber||null,is_st:true,shelf_label:e.shelfLabel,shelf_value:e.shelfValue,file_title:e.fileTitle,tracking_id:e.trackingId,qr_value:e.qrValue,copy_number:c+1,copy_total:copies});}});
        var tS=document.getElementById('labelTemplate'),sS=document.getElementById('labelSize');
        var tT=tS?tS.selectedOptions[0].text.split(' - ')[0]:state.selectedTemplate;
        var sT=sS?sS.selectedOptions[0].text:state.labelSize;
        var pages=labels.length===0?0:Math.ceil(labels.length/30);
        return{baseEntries:base,labels:labels,previewEntries:base.slice(0,12),
            summary:{files:base.length,copies:copies,totalLabels:labels.length,pages:pages,templateLabel:tT,sizeLabel:sT,formatLabel:state.labelFormat==='barcode'?'Barcode':'QR Code'},
            meta:{template:state.selectedTemplate,format:state.labelFormat,orientation:state.orientation,stFilter:true,batchMode:true,batchId:state.currentBatchId||null,generatedAt:new Date().toISOString(),labelStatistics:state.labelStatistics}};
    }

    function embedQrImages(payload) {
        if(!payload||!Array.isArray(payload.labels)||!payload.labels.length)return payload;
        if(payload.meta&&payload.meta.qrImagesEmbedded)return payload;
        if(typeof QRious==='undefined')return payload;
        payload.labels.forEach(function(label){if(label.qr_image)return;var qv=label.qr_value;if(qv&&typeof qv!=='string')qv=String(qv);if(!qv)return;
            try{var qs=String(qv).trim();if(!qs)return;var qr=new QRious({value:qs,size:240,level:'M',background:'#ffffff',foreground:'#000000'});label.qr_image=qr.toDataURL();}catch(e){console.warn('QR error',label.file_number,e);}});
        if(payload.meta)payload.meta.qrImagesEmbedded=true;return payload;
    }

    // ---- Preview rendering ----
    function renderEmptyPreview(el) {
        el.innerHTML='<div class="preview-empty"><div class="preview-empty-icon"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M8 6v4"/></svg></div><p class="text-sm">No files selected yet.</p><p class="text-xs text-slate-500">Pick some files and configure the settings to see the labels here.</p></div>';
    }
    function renderPreview(payload) {
        var el=document.getElementById('previewContent');if(!el)return;
        if(!payload.baseEntries.length){renderEmptyPreview(el);return;}
        el.innerHTML='';var grid=document.createElement('div');grid.className='label-preview-grid';
        payload.previewEntries.forEach(function(entry,idx){
            var card=document.createElement('div');card.className='label-preview-card';
            var qw=document.createElement('div');qw.className='label-preview-qr';var canvas=document.createElement('canvas');canvas.width=180;canvas.height=180;qw.appendChild(canvas);
            var meta=document.createElement('div');meta.className='label-preview-meta';
            var fn=document.createElement('div');fn.className='label-preview-file';fn.textContent=entry.primaryFileNumber||entry.fileNumber||'\u2014';meta.appendChild(fn);
            if(entry.secondaryFileNumber){
                var fnSec=document.createElement('div');
                fnSec.className='label-preview-file label-preview-file--secondary';
                fnSec.style.fontSize='0.78em';
                fnSec.style.fontWeight='500';
                fnSec.style.color='#475569';
                fnSec.textContent=entry.secondaryFileNumber;
                meta.appendChild(fnSec);
            }
            var sd=buildShelfDisplayLabel(entry.shelfLabel,entry.shelfValue);
            var sh=document.createElement('div');sh.className='label-preview-location';sh.textContent=sd?'Shelf/Rack: '+sd:'Shelf/Rack: N/A';meta.appendChild(sh);
            card.appendChild(qw);card.appendChild(meta);grid.appendChild(card);
            setTimeout(function(){if(typeof QRious!=='undefined'){try{new QRious({element:canvas,value:String(entry.qrValue||'').trim(),size:180,level:'M',background:'#ffffff',foreground:'#111827'});}catch(e){var ctx=canvas.getContext('2d');ctx.fillStyle='#f3f4f6';ctx.fillRect(0,0,180,180);ctx.fillStyle='#9ca3af';ctx.textAlign='center';ctx.font='12px Arial';ctx.fillText('QR error',90,95);}}},40+idx*20);
        });
        el.appendChild(grid);
        if(payload.baseEntries.length>payload.previewEntries.length){var ov=document.createElement('p');ov.className='text-xs text-gray-500 mt-3 text-center';ov.textContent='Showing '+payload.previewEntries.length+' of '+payload.baseEntries.length+' labels. All labels will print.';el.appendChild(ov);}
    }
    function updateRackStatisticsDisplay(stats) {
        var h=document.getElementById('rackStatsHeadline'),b=document.getElementById('rackStatsContent');if(!h||!b)return;
        if(!stats||typeof stats!=='object'){h.textContent='';b.innerHTML='<p class="text-sm text-slate-500">Rack usage statistics will appear once labels are generated.</p>';return;}
        var u=Number(coalesce(stats.total_used,0)),c=Number(coalesce(stats.total_capacity,0)),r=Number(coalesce(stats.total_remaining,0));
        if(c>0)h.textContent='Used '+u+' of '+c+' slots, '+r+' remaining';
    }
    function updatePrintSummaryCard(payload) {
        var el=document.getElementById('printSummary');if(!el)return;
        if(!payload.baseEntries.length){el.innerHTML='<p class="preview-note">Once you select files, we\'ll summarise the labels, copies, and template details here.</p>';return;}
        var s=payload.summary;
        el.innerHTML='<div class="flex justify-between"><span class="text-sm">Labels selected:</span><span class="text-sm font-medium">'+s.files+'</span></div>'+
            '<div class="flex justify-between"><span class="text-sm">Copies per label:</span><span class="text-sm font-medium">'+s.copies+'</span></div>'+
            '<div class="flex justify-between"><span class="text-sm">Total labels to print:</span><span class="text-sm font-medium">'+s.totalLabels+'</span></div>'+
            '<div class="flex justify-between"><span class="text-sm">Expected pages:</span><span class="text-sm font-medium">'+(s.pages||1)+'</span></div>'+
            '<div class="flex justify-between"><span class="text-sm">Template:</span><span class="text-sm font-medium">'+s.templateLabel+'</span></div>'+
            '<div class="flex justify-between"><span class="text-sm">Label size:</span><span class="text-sm font-medium">'+s.sizeLabel+'</span></div>'+
            '<div class="flex justify-between"><span class="text-sm">Format:</span><span class="text-sm font-medium">'+s.formatLabel+'</span></div>'+
            '<div class="flex justify-between"><span class="text-sm">Prepared on:</span><span class="text-sm font-medium">'+new Date().toLocaleString()+'</span></div>';
    }

    // ---- File list rendering ----
    function renderFileList() {
        var el=document.getElementById('fileListContent');
        if(state.availableFiles.length===0){
            el.innerHTML='<div class="p-8 text-center text-gray-500"><div class="mb-2"><i data-lucide="file-text" class="h-8 w-8 mx-auto text-gray-400"></i></div><p>No files loaded yet.</p><p class="text-xs text-gray-400 mt-1">Select an application type and click Load Records.</p></div>';
            lucide.createIcons();return;
        }
        var filtered=filterFiles();
        el.innerHTML=filtered.map(function(file){
            var tb=file.tracking_id?'<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">Tracking: '+file.tracking_id+'</span>':'';
            var lb=file.land_use_type?'<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">'+file.land_use_type+'</span>':'';
            var bb=file.batch_no?'<span class="text-xs text-gray-500">Batch: '+file.batch_no+'</span>':'';
            var det=[tb,bb].filter(Boolean).join(' ');
            var sd=file.shelf_location?buildShelfDisplayLabel(file.shelf_location):'';
            var sb=sd?'<span class="text-xs text-gray-500">Shelf: '+sd+'</span>':'';
            var d=deriveFileNumbers(file);
            var primary=d.primaryNumber||file.file_number||'No file number';
            var secondaryLine=d.secondaryNumber?'<p class="text-xs text-slate-500 mt-0.5">'+d.secondaryNumber+'</p>':'';
            return'<div class="flex items-center p-4"><input type="checkbox" id="'+file.id+'" class="file-checkbox mr-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" '+(state.selectedFiles.includes(file.id)?'checked':'')+'><div class="flex flex-1 items-center gap-3"><i data-lucide="file-text" class="h-8 w-8 text-blue-500"></i><div class="flex-1"><div class="flex items-center gap-2"><p class="font-medium text-blue-600">'+primary+'</p>'+lb+'</div>'+secondaryLine+'<div class="flex flex-wrap items-center gap-2 mt-1">'+det+' '+sb+'</div></div></div></div>';
        }).join('');
        lucide.createIcons();
        document.querySelectorAll('.file-checkbox').forEach(function(cb){
            cb.addEventListener('change',function(){
                var fid=parseInt(this.id);
                if(this.checked){if(!state.selectedFiles.includes(fid))state.selectedFiles.push(fid);}
                else{state.selectedFiles=state.selectedFiles.filter(function(id){return id!==fid;});}
                resetPreparedState();updateCounts();updateSelectAllCheckbox();
            });
        });
    }

    // ---- Batch list & pagination ----
    function fetchGeneratedBatches(status,page,opts) {
        status=status||'';page=page||1;var silent=(opts&&opts.silent)===true;
        if(!silent)showLoading('Loading batches...');
        var params=new URLSearchParams({status:status,page:page,per_page:20,search:state.batchSearchQuery});
        fetch(API.batches+'?'+params).then(function(r){return r.json();}).then(function(data){
            if(data.success){state.generatedBatches=data.data;if(data.pagination)state.batchPagination=data.pagination;renderBatchList();renderBatchPagination();}else showError(data.message);
            if(!silent)hideLoading();
        }).catch(function(e){showError('Failed to fetch batches: '+e.message);if(!silent)hideLoading();});
    }
    function markBatchAsPrinted(batchId) {
        showLoading('Marking batch as printed...');
        fetch(API.batchBase+batchId+'/print',{method:'PATCH',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken()}})
            .then(function(r){return r.json();}).then(function(d){if(d.success){showSuccess('Batch marked as printed');fetchGeneratedBatches();}else showError(d.message);hideLoading();})
            .catch(function(e){showError('Failed: '+e.message);hideLoading();});
    }
    function deleteBatch(batchId) {
        if(!confirm('Are you sure you want to delete this batch?'))return;
        showLoading('Deleting batch...');
        fetch(API.batchBase+batchId,{method:'DELETE',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken()}})
            .then(function(r){return r.json();}).then(function(d){if(d.success){showSuccess('Batch deleted');fetchGeneratedBatches();}else showError(d.message);hideLoading();})
            .catch(function(e){showError('Failed: '+e.message);hideLoading();});
    }
    function renderBatchPagination() {
        var info=document.getElementById('batchPaginationInfo'),ctrl=document.getElementById('batchPaginationControls'),p=state.batchPagination;
        var from=p.total===0?0:((p.current_page-1)*p.per_page)+1,to=Math.min(p.current_page*p.per_page,p.total);
        info.textContent='Showing '+from+' to '+to+' of '+p.total+' results';
        if(p.last_page<=1){ctrl.innerHTML='';return;}
        var h='<button onclick="fetchGeneratedBatches(document.getElementById(\'statusFilter\').value,'+(p.current_page-1)+')" class="px-3 py-1 border border-gray-300 rounded-md text-sm '+(p.current_page===1?'opacity-50 cursor-not-allowed':'hover:bg-gray-50')+'" '+(p.current_page===1?'disabled':'')+'>Previous</button>';
        for(var i=1;i<=p.last_page;i++){h+='<button onclick="fetchGeneratedBatches(document.getElementById(\'statusFilter\').value,'+i+')" class="px-3 py-1 border border-gray-300 rounded-md text-sm '+(i===p.current_page?'bg-blue-500 text-white':'hover:bg-gray-50')+'">'+i+'</button>';}
        h+='<button onclick="fetchGeneratedBatches(document.getElementById(\'statusFilter\').value,'+(p.current_page+1)+')" class="px-3 py-1 border border-gray-300 rounded-md text-sm '+(p.current_page===p.last_page?'opacity-50 cursor-not-allowed':'hover:bg-gray-50')+'" '+(p.current_page===p.last_page?'disabled':'')+'>Next</button>';
        ctrl.innerHTML=h;
    }
    function renderBatchList() {
        var el=document.getElementById('batchListContent');
        if(!state.generatedBatches.length){el.innerHTML='<div class="p-8 text-center text-gray-500"><div class="mb-2"><i data-lucide="package" class="h-8 w-8 mx-auto text-gray-400"></i></div><p>No batches generated yet</p><p class="text-sm">Create your first batch in the "Select Files" tab</p></div>';lucide.createIcons();return;}
        var colors={pending:'bg-yellow-100 text-yellow-800',generated:'bg-blue-100 text-blue-800',printed:'bg-green-100 text-green-800',completed:'bg-gray-100 text-gray-800'};
        var appTypeColors={primary:'bg-emerald-100 text-emerald-800',pua:'bg-sky-100 text-sky-800',sua:'bg-amber-100 text-amber-800'};
        var html='';
        state.generatedBatches.forEach(function(b){
            var sc=colors[b.status]||'bg-gray-100 text-gray-800';var dt=new Date(b.created_at).toLocaleDateString();var cn=b.creator?b.creator.name:'Unknown';
            var fc=b.batch_items_count||(b.batch_items?b.batch_items.length:0)||b.generated_count||0;
            var at=(b.application_type||'').toLowerCase();
            var atLabel=at?applicationTypeLabel(at):'—';
            var atCls=appTypeColors[at]||'bg-gray-100 text-gray-700';
            var atCell=at?'<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '+atCls+'">'+atLabel+'</span>':'<span class="text-xs text-gray-400">—</span>';
            html+='<div class="p-3 grid grid-cols-8 gap-4 hover:bg-gray-50"><div class="font-medium">'+b.batch_number+'</div><div>'+atCell+'</div><div class="text-sm">'+dt+'</div><div class="text-sm">'+fc+'</div><div class="text-sm">QR Code</div><div><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '+sc+'">'+(b.status.charAt(0).toUpperCase()+b.status.slice(1))+'</span></div><div class="text-sm">'+cn+'</div><div class="flex gap-1"><button onclick="viewBatchDetails('+b.id+')" class="p-1 text-blue-600 hover:text-blue-800" title="View"><i data-lucide="eye" class="h-4 w-4"></i></button>';
            if(b.status==='generated')html+='<button onclick="printBatchLabels('+b.id+')" class="p-1 text-green-600 hover:text-green-800" title="Print"><i data-lucide="printer" class="h-4 w-4"></i></button>';
            if(b.status!=='printed'&&b.status!=='completed')html+='<button onclick="deleteBatch('+b.id+')" class="p-1 text-red-600 hover:text-red-800" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>';
            html+='</div></div>';
        });
        el.innerHTML=html;lucide.createIcons();
    }
    function viewBatchDetails(batchId) {
        showLoading('Loading batch details...');
        fetch(API.batchBase+batchId+'/print').then(function(r){return r.json();}).then(function(d){if(d.success)displayBatchDetailsModal(d.data);else showError(d.message);hideLoading();}).catch(function(e){showError('Failed: '+e.message);hideLoading();});
    }
    function displayBatchDetailsModal(bd) {
        var batch=bd.batch,files=bd.files||[];
        var h='<div id="batchDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"><div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white"><div class="mt-3">'
            +'<div class="flex items-center justify-between mb-4"><h3 class="text-lg font-medium text-gray-900">Batch Details: '+batch.batch_number+'</h3><button onclick="closeBatchDetailsModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button></div>'
            +'<div class="grid grid-cols-2 gap-4 mb-6">'
            +'<div><p class="text-sm font-medium text-gray-500">Batch Number</p><p class="text-sm text-gray-900">'+batch.batch_number+'</p></div>'
            +'<div><p class="text-sm font-medium text-gray-500">Status</p><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">'+(batch.status.charAt(0).toUpperCase()+batch.status.slice(1))+'</span></div>'
            +'<div><p class="text-sm font-medium text-gray-500">Prefix</p><p class="text-sm text-gray-900">'+(batch.prefix||'\u2014')+'</p></div>'
            +'<div><p class="text-sm font-medium text-gray-500">Application Type</p><p class="text-sm text-gray-900">'+(batch.application_type?applicationTypeLabel(batch.application_type):'\u2014')+'</p></div>'
            +'<div><p class="text-sm font-medium text-gray-500">Created</p><p class="text-sm text-gray-900">'+new Date(batch.created_at).toLocaleDateString()+'</p></div>'
            +'<div><p class="text-sm font-medium text-gray-500">Files Count</p><p class="text-sm text-gray-900">'+files.length+'</p></div>'
            +'<div><p class="text-sm font-medium text-gray-500">Shelf Label</p><p class="text-sm text-gray-900">'+(batch.full_label||'\u2014')+'</p></div>'
            +'</div>';
        if(files.length){
            h+='<div class="mb-4"><h4 class="text-md font-medium text-gray-900 mb-2">Files in this Batch</h4><div class="max-h-60 overflow-y-auto border rounded-md"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">File Number</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tracking ID</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">';
            files.forEach(function(f){h+='<tr><td class="px-4 py-2 text-sm font-medium text-gray-900">'+(f.file_number||'\u2014')+'</td><td class="px-4 py-2 text-sm text-gray-500">'+(f.shelf_location||'N/A')+'</td><td class="px-4 py-2 text-sm text-gray-500">'+(f.tracking_id||'\u2014')+'</td></tr>';});
            h+='</tbody></table></div></div>';
        }
        h+='<div class="flex justify-end space-x-3">';
        if(batch.status==='generated'){h+='<button onclick="printBatchLabels('+batch.id+');closeBatchDetailsModal();" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">Print Labels</button>';h+='<button onclick="markBatchAsPrinted('+batch.id+');closeBatchDetailsModal();" class="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-md hover:bg-yellow-700">Mark as Printed</button>';}
        if(batch.status!=='printed'&&batch.status!=='completed')h+='<button onclick="deleteBatch('+batch.id+');closeBatchDetailsModal();" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">Delete Batch</button>';
        h+='<button onclick="closeBatchDetailsModal()" class="px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400">Close</button></div></div></div></div>';
        document.body.insertAdjacentHTML('beforeend',h);lucide.createIcons();
    }
    function closeBatchDetailsModal(){var m=document.getElementById('batchDetailsModal');if(m)m.remove();}
    function printBatchLabels(batchId) {
        showLoading('Loading batch for printing...');
        fetch(API.batchBase+batchId+'/print').then(function(r){return r.json();}).then(function(data){
            if(!data.success){showError(data.message);hideLoading();return;}
            var batch=data.data.batch,files=data.data.files;
            resetPreparedState({preserveBatchId:true,preserveReprint:true});
            state.availableFiles=files;state.selectedFiles=files.map(function(f){return f.id;});
            var sig=computePreparationSignature(),built=buildPreparedEntriesFromItems(files);
            state.preparedRecords=built.records;state.preparedBaseEntries=built.baseEntries;
            state.previewPrepared=true;state.preparedSignature=sig;
            state.preparedBatchResponse={success:true,data:{batch_id:batchId,batch_number:batch.batch_number,file_count:files.length,source:'existing',label_items:files}};
            state.currentBatchId=batchId;state.reprintMode=true;
            switchTab('preview');showSuccess('Loaded batch '+batch.batch_number+' with '+files.length+' files for printing');hideLoading();
        }).catch(function(e){showError('Failed: '+e.message);hideLoading();});
    }

    // Global exports
    window.viewBatchDetails=viewBatchDetails;window.printBatchLabels=printBatchLabels;
    window.markBatchAsPrinted=markBatchAsPrinted;window.deleteBatch=deleteBatch;
    window.closeBatchDetailsModal=closeBatchDetailsModal;window.fetchGeneratedBatches=fetchGeneratedBatches;

    // ---- UI update functions ----
    function updateCounts() {
        var el=document.getElementById('selectedFilesCount');if(el)el.textContent=state.selectedFiles.length;
        var ss=document.getElementById('selectionStatus');if(ss)ss.textContent=state.selectedFiles.length+' of '+(state.availableFiles.length||0)+' selected';
        updateButtonStates();
    }
    function updateButtonStates() {
        var v=state.selectedFiles.length>=1;
        ['printBtn','continueToSettingsBtn'].forEach(function(id){var b=document.getElementById(id);if(!b)return;b.disabled=!v;if(v){b.classList.remove('opacity-50','cursor-not-allowed');b.classList.add('hover:bg-blue-700');}else{b.classList.add('opacity-50','cursor-not-allowed');b.classList.remove('hover:bg-blue-700');}});
        updateTabAccessibility();if(state.activeTab==='preview')refreshPreview(true);
    }
    function filterFiles() {
        return state.availableFiles.filter(function(f){if(!state.searchTerm)return true;var t=state.searchTerm.toLowerCase();return(f.file_number&&f.file_number.toLowerCase().includes(t))||(f.tracking_id&&f.tracking_id.toLowerCase().includes(t));});
    }
    function updateTabAccessibility() {
        var has=state.selectedFiles.length>=1;
        ['settings','preview'].forEach(function(tab){var el=document.querySelector('[data-tab="'+tab+'"]');if(!el)return;if(has){el.classList.remove('opacity-50','cursor-not-allowed');el.style.pointerEvents='auto';}else{el.classList.add('opacity-50','cursor-not-allowed');el.style.pointerEvents='none';}});
    }
    function updateSelectAllCheckbox() {
        var cb=document.getElementById('selectAll'),ff=filterFiles();cb.checked=state.selectedFiles.length===ff.length&&ff.length>0;
    }
    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active','border-blue-500','text-blue-600');b.classList.add('border-transparent','text-gray-500');});
        var ab=document.querySelector('[data-tab="'+tabName+'"]');if(ab){ab.classList.add('active','border-blue-500','text-blue-600');ab.classList.remove('border-transparent','text-gray-500');}
        document.querySelectorAll('.tab-content').forEach(function(c){c.classList.remove('active');});
        var te=document.getElementById(tabName+'-tab');if(te)te.classList.add('active');
        state.activeTab=tabName;if(tabName==='preview')refreshPreview();else if(tabName==='generated')fetchGeneratedBatches();
    }
    function updatePreview() {
        var desc=document.getElementById('previewDescription');var payload=buildLabelPayload();
        if(desc){if(!payload.baseEntries.length)desc.textContent='No files selected yet. Choose files and configure settings to generate a preview.';
            else{var w=payload.summary;desc.textContent='Previewing '+Math.min(payload.previewEntries.length,w.files)+' label'+(w.files===1?'':'s')+' ('+w.copies+' cop'+(w.copies===1?'y':'ies')+' each). Printing will cover '+(w.pages||1)+' page'+(w.pages===1?'':'s')+'.';}}
        renderPreview(payload);updatePrintSummaryCard(payload);updateRackStatisticsDisplay(state.labelStatistics);return payload;
    }

    // ---- Print window ----
    function openPrintWindowWithPayload(payload) {
        var payloadKey='st-printlabel-'+Date.now();
        try{localStorage.setItem(payloadKey,JSON.stringify(payload));}catch(e){console.warn('Unable to persist print payload',e);}
        var printUrl=PRINT_TEMPLATE_URL+'?payloadKey='+encodeURIComponent(payloadKey);
        var pw=null;try{pw=window.open('','_blank','width=980,height=720,scrollbars=1');}catch(e){}
        if(!pw){Swal.fire({icon:'info',title:'Enable pop-ups',text:'Please allow pop-ups for this site and click Print again.',confirmButtonColor:'#3b82f6'});return null;}
        try{pw.document.write('<!DOCTYPE html><html><head><title>Preparing labels\u2026</title><style>body{font-family:Inter,Segoe UI,Arial,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc;color:#475569;}</style></head><body><div>Preparing label print view\u2026</div></body></html>');pw.document.close();}catch(e){}
        try{pw.focus();}catch(e){}
        setTimeout(function(){try{pw.location.replace(printUrl);}catch(e){try{pw.location.href=printUrl;}catch(e2){}}},25);
        var msg={type:'print-labels',payload:payload};
        var send=function(){if(pw.closed)return;try{pw.postMessage(msg,window.location.origin);}catch(e){}};
        setTimeout(send,500);var att=0;var timer=setInterval(function(){att++;if(att>5||pw.closed){clearInterval(timer);return;}send();},800);
        return pw;
    }
    async function printLabels(event) {
        if(event&&typeof event.preventDefault==='function')event.preventDefault();
        var br;try{br=await preparePreviewDataIfNeeded();}catch(err){hideLoading();showError(err.message||'Unable to prepare batch.');return;}
        var payload=buildLabelPayload();if(!payload.baseEntries.length){showError('Nothing to print. Select files first.');return;}
        payload.autoPrint=true;payload.notice='Generated via ST Print Labels';
        payload.meta.copies=payload.summary.copies;payload.meta.templateLabel=payload.summary.templateLabel;
        payload.meta.sizeLabel=payload.summary.sizeLabel;payload.meta.formatLabel=payload.summary.formatLabel;
        payload.meta.batchId=state.currentBatchId||payload.meta.batchId||null;
        var bd=br&&br.data?br.data:null;if(bd&&bd.batch_number)payload.meta.batchNumber=bd.batch_number;
        embedQrImages(payload);updateRackStatisticsDisplay(state.labelStatistics);
        var pw=openPrintWindowWithPayload(payload);if(!pw)return;
        var msg=bd&&bd.batch_number?'Batch '+bd.batch_number+' prepared. Print window ready.':'Print window prepared. Use your browser\'s print dialog to finish printing.';
        showSuccess(msg);fetchGeneratedBatches('',1,{silent:true});
    }
    window.addEventListener('message',function(event){
        if(event.origin!==window.location.origin)return;if(!event.data||typeof event.data!=='object')return;
        if(event.data.type==='print-labels:afterprint'){if(state.currentBatchId){setTimeout(function(){if(confirm('Have you successfully printed the labels? This will mark the batch as printed.')){markBatchAsPrinted(state.currentBatchId);}state.currentBatchId=null;},250);}}
    });

    // ---- Event listeners ----
    updateButtonStates();renderFileList();updateFullLabelDisplay();fetchRackLabelStatus(state.fullLabel);
    document.querySelectorAll('.tab-btn').forEach(function(btn){btn.addEventListener('click',function(){switchTab(this.dataset.tab);});});
    document.getElementById('searchInput').addEventListener('input',function(){state.searchTerm=this.value;renderFileList();updateCounts();});
    document.getElementById('selectAll').addEventListener('change',function(){
        var ff=filterFiles();if(this.checked){state.selectedFiles=ff.map(function(f){return f.id;});}else{var fids=ff.map(function(f){return f.id;});state.selectedFiles=state.selectedFiles.filter(function(id){return!fids.includes(id);});}
        resetPreparedState();renderFileList();updateCounts();
    });

    // ST application type selector (Primary | PUA | SUA)
    function initApplicationTypeSelector() {
        var sel=document.getElementById('stApplicationTypeSelect');
        if(!sel)return;
        sel.addEventListener('change', function(){
            state.stApplicationType = this.value;
            state.availableFiles=[]; state.selectedFiles=[];
            resetPreparedState();
            renderFileList(); updateCounts(); updateSelectAllCheckbox();
            // Capacity depends on the application type — refresh the shelf status readout.
            fetchRackLabelStatus(state.fullLabel);
        });

        // Optionally pull live counts and decorate option labels.
        fetch(API.applicationTypes).then(function(r){return r.json();}).then(function(d){
            if(!d||!d.success||!Array.isArray(d.data))return;
            var counts={};
            d.data.forEach(function(row){counts[row.type]=row.count;});
            Array.prototype.forEach.call(sel.options, function(opt){
                if(!opt.value)return;
                var c=counts[opt.value];
                if(typeof c==='number') opt.textContent = opt.dataset.baseLabel ? opt.dataset.baseLabel : opt.textContent;
                opt.dataset.baseLabel = opt.dataset.baseLabel || opt.textContent;
                if(typeof c==='number') opt.textContent = opt.dataset.baseLabel + ' (' + c + ')';
            });
        }).catch(function(e){console.warn('Application-type counts load error:',e);});
    }

    initApplicationTypeSelector();
    var excludeToggle=document.getElementById('excludeAssignedToggle');
    if(excludeToggle){excludeToggle.addEventListener('change',function(){state.excludeAssignedFromBatch=this.checked;});}
    var rackSelect=document.getElementById('rackPrimarySelect');
    if(rackSelect){rackSelect.addEventListener('change',function(){state.rackPrimary=(this.value||'A').toUpperCase();var l=updateFullLabelDisplay();fetchRackLabelStatus(l);if(state.activeTab==='preview')refreshPreview(true);});}
    var rackSecSelect=document.getElementById('rackSecondarySelect');
    if(rackSecSelect){rackSecSelect.addEventListener('change',function(){state.rackSecondary=(this.value||'').toUpperCase();var l=updateFullLabelDisplay();fetchRackLabelStatus(l);if(state.activeTab==='preview')refreshPreview(true);});}
    var shelfSelect=document.getElementById('shelfNumberSelect');
    if(shelfSelect){shelfSelect.addEventListener('change',function(){state.shelfNumber=(this.value||'1').toString();var l=updateFullLabelDisplay();fetchRackLabelStatus(l);if(state.activeTab==='preview')refreshPreview(true);});}

    document.getElementById('generateBatchBtn').addEventListener('click',function(){
        var btn=this;btn.disabled=true;btn.classList.add('opacity-50','cursor-not-allowed');
        loadStFiles().finally(function(){btn.disabled=false;btn.classList.remove('opacity-50','cursor-not-allowed');});
    });
    document.getElementById('resetBtn').addEventListener('click',function(){
        resetPreparedState();state.selectedFiles=[];state.copies=1;state.stApplicationType='';
        state.rackPrimary='A';state.shelfNumber='1';state.fullLabel='A1';state.availableFiles=[];
        state.excludeAssignedFromBatch=false;state.rackLabelStatus=null;state.loadedFromBatch=false;
        document.getElementById('copies').value=1;

        var appSel = document.getElementById('stApplicationTypeSelect');
        if (appSel) { appSel.value = ''; }
        if(rackSelect)rackSelect.value='A';if(shelfSelect)shelfSelect.value='1';
        if(excludeToggle)excludeToggle.checked=false;
        updateFullLabelDisplay();updateRackLabelStatusDisplay();fetchRackLabelStatus(state.fullLabel);
        renderFileList();updateCounts();updateSelectAllCheckbox();
        if(state.activeTab==='preview')refreshPreview(true);
    });
    document.querySelectorAll('.label-format-option').forEach(function(opt){opt.addEventListener('click',function(){document.querySelectorAll('.label-format-option').forEach(function(o){o.classList.remove('selected');});this.classList.add('selected');state.labelFormat=this.dataset.format;updateTabAccessibility();});});
    document.querySelectorAll('.orientation-option').forEach(function(opt){opt.addEventListener('click',function(){if(this.dataset.disabled==='true')return;document.querySelectorAll('.orientation-option').forEach(function(o){o.classList.remove('selected');});this.classList.add('selected');state.orientation=this.dataset.orientation;if(!state.reprintMode)resetPreparedState();if(state.activeTab==='preview')refreshPreview(true);var r=document.querySelector('input[value="'+this.dataset.orientation+'"]');if(r)r.checked=true;});});
    var advToggle=document.getElementById('advancedToggle');
    if(advToggle){advToggle.addEventListener('click',function(){state.showAdvancedOptions=!state.showAdvancedOptions;document.getElementById('advancedOptions').style.display=state.showAdvancedOptions?'block':'none';this.textContent=state.showAdvancedOptions?'Hide Advanced':'Show Advanced';});}
    document.getElementById('copies').addEventListener('change',function(){state.copies=parseInt(this.value);});
    document.getElementById('labelTemplate').addEventListener('change',function(){state.selectedTemplate=this.value;if(!state.reprintMode)resetPreparedState();if(state.activeTab==='preview')refreshPreview(true);});
    document.getElementById('labelSize').addEventListener('change',function(){state.labelSize=this.value;updateTabAccessibility();});
    document.getElementById('continueToSettingsBtn').addEventListener('click',function(){if(this.disabled)return;if(state.selectedFiles.length<1){showError('Please select at least 1 file to continue.');return;}switchTab('settings');});
    document.getElementById('backToFilesBtn').addEventListener('click',function(){switchTab('files');});
    document.getElementById('continueToPreviewBtn').addEventListener('click',function(){switchTab('preview');});
    document.getElementById('backToSettingsBtn').addEventListener('click',function(){switchTab('settings');});
    var dupBtn=document.getElementById('duplicateBtn');
    if(dupBtn){dupBtn.addEventListener('click',function(){state.copies++;document.getElementById('copies').value=state.copies;Swal.fire({icon:'info',title:'Copies Updated',text:'Now printing '+state.copies+' copies of each label.',timer:2000,showConfirmButton:false});});}
    document.getElementById('printBtn').addEventListener('click',function(){if(this.disabled)return;if(state.selectedFiles.length<1){showError('Please select at least one file before previewing.');return;}switchTab('preview');});
    var batchSearchInput=document.getElementById('batchSearchInput');
    if(batchSearchInput){var bst;batchSearchInput.addEventListener('input',function(){clearTimeout(bst);state.batchSearchQuery=this.value.trim();bst=setTimeout(function(){fetchGeneratedBatches(document.getElementById('statusFilter').value,1);},500);});}
    var statusFilter=document.getElementById('statusFilter');if(statusFilter)statusFilter.addEventListener('change',function(){fetchGeneratedBatches(this.value,1);});
    var refreshBtn=document.getElementById('refreshBatchesBtn');
    if(refreshBtn){refreshBtn.addEventListener('click',function(){state.batchSearchQuery='';if(batchSearchInput)batchSearchInput.value='';fetchGeneratedBatches('',1);});}
    document.getElementById('finalPrintBtn').addEventListener('click',printLabels);
    updateCounts();updateTabAccessibility();
});
</script>
