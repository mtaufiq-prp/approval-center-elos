<?php echo $__env->make('partials._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php
    $existingSchema = old('form_schema', isset($item) && $item->form_schema
        ? json_encode($item->form_schema, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '');
    $existingSample = old('sample_context_json', isset($item) && $item->sample_context_json
        ? json_encode($item->sample_context_json, JSON_UNESCAPED_UNICODE) : '');
?>


<div class="row g-3 mb-3">
    <div class="col-md-5">
        <label class="form-label">Source App <span class="text-danger">*</span></label>
        <select name="idtblsource_app" class="form-select" required <?php echo e(isset($item)?'disabled':''); ?>>
            <option value="">-- pilih --</option>
            <?php $__currentLoopData = $sourceApps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($sa->idtblsource_app); ?>"
                    <?php echo e((string)old('idtblsource_app',$item->idtblsource_app??'') === (string)$sa->idtblsource_app ? 'selected':''); ?>>
                    <?php echo e($sa->app_code); ?> — <?php echo e($sa->app_name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php if(isset($item)): ?><input type="hidden" name="idtblsource_app" value="<?php echo e($item->idtblsource_app); ?>"><?php endif; ?>
    </div>
    <div class="col-md-4">
        <label class="form-label">Doc Name <span class="text-danger">*</span></label>
        <input type="text" name="doc_name" required maxlength="150" class="form-control"
               value="<?php echo e(old('doc_name',$item->doc_name??'')); ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label">Doc Code <span class="text-danger">*</span></label>
        <input type="text" name="doc_code" required maxlength="50" class="form-control"
               value="<?php echo e(old('doc_code',$item->doc_code??'')); ?>" <?php echo e(isset($item)?'readonly':''); ?>>
    </div>
    <div class="col-md-1">
        <label class="form-label">&nbsp;</label>
        <div class="form-check mt-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
                   <?php echo e(old('is_active',$item->is_active??true) ? 'checked':''); ?>>
            <label for="ia" class="form-check-label small">Aktif</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?php echo e(old('description',$item->description??'')); ?></textarea>
    </div>
</div>

<hr class="my-3">


<div class="d-flex align-items-center gap-2 mb-2">
    <span class="fw-semibold"><i class="bi bi-braces text-warning"></i> Sample Context JSON</span>
    <span class="badge bg-warning text-dark">Langkah 1</span>
    <span class="text-muted small">— Paste contoh JSON dari aplikasi sumber, lalu klik Analisis</span>
</div>
<div class="row g-2 mb-3">
    <div class="col-md-9">
        <textarea id="smp-ta" class="form-control font-monospace"
                  style="height:140px;font-size:12px;resize:vertical"
                  placeholder='Paste context_json di sini...'
                  oninput="document.getElementById('smp-hid').value=this.value"><?php echo e($existingSample); ?></textarea>
        <input type="hidden" name="sample_context_json" id="smp-hid" value="<?php echo e($existingSample); ?>">
    </div>
    <div class="col-md-3 d-flex flex-column gap-2">
        <button type="button" class="btn btn-warning btn-sm" id="btn-parse">
            <i class="bi bi-magic"></i> Analisis JSON → Field
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-beautify">
            <i class="bi bi-code-slash"></i> Beautify JSON
        </button>
        <button type="button" class="btn btn-outline-info btn-sm" id="btn-load-sfa">
            <i class="bi bi-download"></i> Load Contoh SFA Retur
        </button>
    </div>
</div>


<div class="d-flex align-items-center gap-2 mb-2">
    <span class="fw-semibold"><i class="bi bi-grid-1x2 text-primary"></i> Schema Builder</span>
    <span class="badge bg-primary">Langkah 2</span>
    <span class="badge bg-secondary" id="fcount">0 field</span>
    <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-success" id="btn-add-sep">
            <i class="bi bi-layout-split"></i> + Separator
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-preview">
            <i class="bi bi-eye"></i> Preview Tampilan
        </button>
        <button type="button" class="btn btn-sm btn-outline-dark" id="btn-raw">
            <i class="bi bi-code"></i> Raw JSON
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-clear">
            <i class="bi bi-trash"></i> Clear
        </button>
    </div>
</div>


<div class="row g-0" style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;min-height:460px">

    
    <div class="col-md-4 border-end d-flex flex-column" style="background:#f8f9fa">
        <div class="px-3 py-2 border-bottom bg-white d-flex align-items-center justify-content-between">
            <span class="small fw-semibold text-uppercase text-muted">Field Tersedia</span>
            <span class="small text-muted" id="avail-count">—</span>
        </div>
        <div id="avail-list" class="p-2 flex-fill overflow-auto" style="max-height:380px">
            <div class="text-muted small text-center pt-4" id="avail-empty">
                <i class="bi bi-arrow-up-circle d-block fs-3 mb-1 opacity-25"></i>
                Parse JSON dulu di Langkah 1
            </div>
        </div>
        <div class="p-2 border-top bg-white">
            <div class="small text-muted mb-1">Tambah field manual:</div>
            <div class="input-group input-group-sm">
                <input type="text" id="mf-inp" class="form-control font-monospace"
                       placeholder="nama_field">
                <button class="btn btn-outline-primary" id="btn-mf">+</button>
            </div>
        </div>
    </div>

    
    <div class="col-md-8 d-flex flex-column" style="background:#fff">
        <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2">
            <span class="small fw-semibold text-uppercase text-muted">Canvas Layout</span>
            <small class="text-muted">— Drag field dari kiri, atau klik 2x pada chip field</small>
        </div>
        <div id="canvas"
             ondragover="event.preventDefault();this.classList.add('canvas-hover')"
             ondragleave="this.classList.remove('canvas-hover')"
             ondrop="onDropCanvas(event)"
             class="p-3 flex-fill overflow-auto"
             style="min-height:400px;position:relative">
            <div id="canvas-ph" class="text-center text-muted small pt-5">
                <i class="bi bi-arrow-left-circle d-block fs-2 mb-2 opacity-25"></i>
                Drag field dari kiri ke sini
            </div>
        </div>
    </div>
</div>


<div id="raw-wrap" class="mt-2 d-none">
    <textarea id="raw-ed" class="form-control font-monospace mt-1"
              style="height:140px;font-size:12px"
              oninput="syncFromRaw()"><?php echo e($existingSchema); ?></textarea>
</div>
<input type="hidden" name="form_schema" id="fs-hid" value="<?php echo e($existingSchema); ?>">

<hr>
<div class="d-flex justify-content-end gap-2">
    <a href="<?php echo e(route('master.document-type.index')); ?>" class="btn btn-light">Batal</a>
    <button class="btn btn-primary"><i class="bi bi-floppy"></i> Simpan</button>
</div>


<div class="modal fade" id="preview-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-eye"></i> Preview Tampilan Form Approval</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="preview-body">
                <div class="text-muted text-center py-4">Belum ada field.</div>
            </div>
            <div class="modal-footer py-2">
                <small class="text-muted me-auto">Data ditampilkan menggunakan nilai dari Sample JSON</small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


<style>
.avail-chip{display:flex;align-items:center;gap:6px;padding:5px 8px;margin-bottom:4px;
    background:#fff;border:1px solid #dee2e6;border-radius:5px;cursor:grab;
    font-size:12px;user-select:none;transition:all .12s}
.avail-chip:hover{border-color:#0d6efd;box-shadow:0 1px 4px rgba(13,110,253,.15);transform:translateX(2px)}
.avail-chip.used{opacity:.45;background:#f8f9fa}
.avail-chip .cf{font-family:monospace;color:#0d6efd;font-size:11px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.avail-chip .ct{font-size:10px;padding:1px 5px;border-radius:10px;background:#e9ecef;color:#495057;flex-shrink:0}
.avail-chip .cg{font-size:10px;color:#adb5bd;flex-shrink:0}
.grp-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
    color:#6c757d;padding:4px 4px 2px;margin-top:6px}
.cc{background:#fff;border:1px solid #dee2e6;border-radius:6px;margin-bottom:6px;
    padding:7px 8px;display:flex;align-items:flex-start;gap:7px;cursor:default;
    transition:box-shadow .12s}
.cc:hover{box-shadow:0 2px 6px rgba(0,0,0,.08)}
.cc.sep{background:#f0f4ff;border-color:#c5d5ff}
.cc.drag-src{opacity:.35}
.cc .dh{cursor:grab;color:#adb5bd;font-size:16px;flex-shrink:0;padding-top:1px;line-height:1}
.cc .cb{flex:1;min-width:0}
.cc .ctrl{display:flex;flex-direction:column;gap:3px;flex-shrink:0}
.cc .ctrl .btn{padding:1px 5px;font-size:11px}
.canvas-hover{background:#f0f6ff!important}
.drop-line{height:2px;background:#0d6efd;border-radius:1px;margin:2px 0}

/* Preview modal */
.pv-label{font-size:11px;color:#6c757d;margin-bottom:2px}
.pv-val{font-size:13px;padding-bottom:6px;border-bottom:1px solid #f0f0f0;min-height:22px}
.pv-sep{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
    color:#0d6efd;border-bottom:2px solid #0d6efd;padding-bottom:3px;margin:14px 0 8px}
.pv-cur{font-weight:600;color:#198754}
.pv-tbl{font-size:11px;width:100%;border-collapse:collapse}
.pv-tbl th{background:#e9ecef;padding:4px 6px;font-weight:600;text-align:left}
.pv-tbl td{padding:3px 6px;border-bottom:1px solid #f0f0f0}
</style>


<script>
(function() {
// ── State ────────────────────────────────────────────────────────
let fields   = [];   // [{field,label,type,width,...}]
let avail    = [];   // [{key,type,value,group,columns?,col_labels?}]
let flatData = {};   // key → sample value (untuk preview)
let dragSrc  = null; // index card sedang di-drag di canvas
let dragFromAvail = null; // key chip sedang di-drag dari avail

const TYPES = ['text','number','currency','date','datetime','badge','textarea','image','table','list','separator'];
const TC = {text:'#6c757d',number:'#0d6efd',currency:'#198754',date:'#fd7e14',
    datetime:'#e67e22',badge:'#6f42c1',textarea:'#20c997',image:'#dc3545',
    table:'#0dcaf0',list:'#ffc107',separator:'#6c757d'};
const WO = [['half','50%'],['full','100%'],['third','33%']];

// SFA Retur built-in sample
const SFA_SAMPLE = {"header":[{"tipe_tagihan":"GANTI BARANG/KLAIM","shipto":"1200041403","idtblemployee":"11170317","idtblcustomer":"1200041403","budget_from":"01 Jan 2023","budget_to":"13 Nov 2025","nilai_omset":"699707000.00","nilai_retur":"12141300.00","nilai_persen":"1.74","status":"PRGR MEMBUTUHKAN REVISI SALESMAN","create_time":"2025-11-14 09:33:47.000000","jenis_product":"Non Trading","branch_name":"SEMARANG","alasan_retur":"KEMASAN RUSAK","customer_name":"TC. BEN JOYO/SOEPENGNO B","employee_name":"MOCHAMAD SUMARDI","idtbltakingorder":"2263202511140932101"}],"detail":[{"status":"h","product_name":"IMPRA WS-162 B CANDY YELLOW-1L","qty":"1","uom":"PC","value_retur":85211,"value_retur_ori":85211,"value_potong_budget":76767,"kemasan_produk":"RUSAK","kualitas_produk":"BAGUS","isi_produk":"100","alasan_retur":"KEMASAN RUSAK","disc":"7.50+6+","total_disc":"12789","mts_mto":"MTS","rilis_date":"31/01/2023","real_batch":"WV5X5HUVQE","no_batch":"23010939","pricelist":"98000","detail_kemasan":" - Penyok","foto_all":"1200041403_371_093350_foto.jpg"}],"history":[{"status":"PERMOHONAN DI SETUJUI BMH","xcreated_date":"18/11/25 09:12","employee_name":"AGUNG KARTONO","jobtitlename":"BRANCH MARKET SUB DEPARTMENT HEAD","notes":"barang pengganti sama"}],"billing":{"period":"2023-02","total_billing":"33279321255.00","budget":"133117000"},"retur":{"total_retur":"1005515283"},"detail_budget_retur":[{"total":"98000","percent":"1.000000","disc":"12789","all":"85211"}]};

// ── Helpers ──────────────────────────────────────────────────────
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function lbl(k){ return k.split(/[_\.]/).map(w=>w.charAt(0).toUpperCase()+w.slice(1)).join(' '); }
function detectType(k,v){
    const kl=k.toLowerCase();
    if(/foto|image|img|photo/.test(kl)) return 'image';
    if(/nilai|value|harga|total|budget|amount|retur|disc|billing|omset|price|pricelist/.test(kl)) return 'currency';
    if(/create_time|datetime/.test(kl)) return 'datetime';
    if(/tgl|tanggal|date|rilis/.test(kl)) return 'date';
    if(/status|kondisi|kategori|tipe|jenis|flag/.test(kl)) return 'badge';
    if(/persen|percent|qty|jumlah/.test(kl)||typeof v==='number') return 'number';
    if(typeof v==='string'&&v.length>80) return 'textarea';
    return 'text';
}

// ── Flatten JSON untuk avail ──────────────────────────────────────
function flatten(obj, prefix, outArr, outFlat){
    if(obj===null||obj===undefined) return;
    if(Array.isArray(obj)){
        const gk=prefix||'items';
        if(obj.length>0&&typeof obj[0]==='object'&&obj[0]!==null){
            const cols=Object.keys(obj[0]);
            outArr.push({key:gk,type:'table',value:obj,group:null,columns:cols,col_labels:cols.map(lbl)});
            outFlat[gk]=obj;
            cols.forEach(c=>{
                const fk=(prefix?prefix+'.':'')+c;
                const tv=obj[0][c];
                if(typeof tv!=='object'){
                    outArr.push({key:fk,type:detectType(c,tv),value:tv,group:prefix});
                    outFlat[fk]=tv;
                }
            });
        }
        return;
    }
    if(typeof obj==='object'){
        Object.entries(obj).forEach(([k,v])=>{
            const fk=prefix?prefix+'.'+k:k;
            if(Array.isArray(v)){flatten(v,fk,outArr,outFlat);}
            else if(v!==null&&typeof v==='object'){flatten(v,fk,outArr,outFlat);}
            else{outArr.push({key:fk,type:detectType(k,v),value:v,group:prefix||null});outFlat[fk]=v;}
        });
        return;
    }
    outArr.push({key:prefix,type:detectType(prefix,obj),value:obj,group:null});
    outFlat[prefix]=obj;
}

// ── Render Available ─────────────────────────────────────────────
function renderAvail(){
    const el=document.getElementById('avail-list');
    const empEl=document.getElementById('avail-empty');
    document.getElementById('avail-count').textContent=avail.length+' field';
    if(!avail.length){
        el.innerHTML='';
        if(empEl) el.appendChild(empEl);
        if(empEl) empEl.style.display='';
        return;
    }
    if(empEl) empEl.style.display='none';
    // Group
    const groups={};
    avail.forEach(f=>{const g=f.group||'__root__';if(!groups[g])groups[g]=[];groups[g].push(f);});
    let html='';
    Object.entries(groups).forEach(([g,flds])=>{
        if(g!=='__root__') html+=`<div class="grp-label">${esc(g)}</div>`;
        flds.forEach(f=>{
            const used=fields.some(s=>s.field===f.key);
            const c=TC[f.type]||'#6c757d';
            html+=`<div class="avail-chip${used?' used':''}" draggable="true"
                data-key="${esc(f.key)}"
                title="Double-click = tambah/hapus\nField: ${esc(f.key)}\nSample: ${esc(String(f.value||'').substring(0,60))}">
                <span class="cf" title="${esc(f.key)}">${esc(f.key)}</span>
                <span class="ct" style="background:${c}22;color:${c}">${f.type}</span>
                ${f.group?`<span class="cg">${esc(f.group)}</span>`:''}
            </div>`;
        });
    });
    el.innerHTML=html;
    // Bind events
    el.querySelectorAll('.avail-chip').forEach(chip=>{
        chip.addEventListener('dragstart',e=>{
            dragFromAvail=chip.dataset.key;
            e.dataTransfer.effectAllowed='copy';
            e.dataTransfer.setData('text/plain','avail:'+chip.dataset.key);
        });
        chip.addEventListener('dblclick',()=>toggleField(chip.dataset.key));
    });
}

function toggleField(key){
    const idx=fields.findIndex(s=>s.field===key);
    if(idx>=0){fields.splice(idx,1);}
    else{
        const f=avail.find(a=>a.key===key);
        if(f) addFromAvail(f);
    }
    renderCanvas(); renderAvail();
}

function addFromAvail(a){
    const f={field:a.key,label:lbl(a.key.split('.').pop()),type:a.type,
        width:a.type==='table'||a.type==='textarea'?'full':'half'};
    if(a.type==='table'&&a.columns){f.columns=a.columns;f.col_labels=a.col_labels||a.columns.map(lbl);}
    if(a.type==='currency') f.prefix='Rp ';
    if(a.type==='badge') f.colors={};
    fields.push(f);
}

// ── Render Canvas ────────────────────────────────────────────────
function renderCanvas(){
    const canvas=document.getElementById('canvas');
    const ph=document.getElementById('canvas-ph');
    document.getElementById('fcount').textContent=fields.length+' field';

    // Kosongkan canvas tapi JANGAN hapus canvas-ph dari DOM
    // Hapus hanya elemen .cc
    Array.from(canvas.querySelectorAll('.cc')).forEach(el=>el.remove());

    if(!fields.length){
        if(ph) ph.style.display='';
        syncHidden(); return;
    }
    if(ph) ph.style.display='none';

    fields.forEach((f,i)=>{
        const div=document.createElement('div');
        div.innerHTML=cardHtml(f,i);
        const card=div.firstElementChild;
        if(card) canvas.appendChild(card);
    });

    // Bind drag events pada cards
    canvas.querySelectorAll('.cc').forEach((card,i)=>{
        card.addEventListener('dragstart',e=>{
            dragSrc=i; dragFromAvail=null;
            e.dataTransfer.effectAllowed='move';
            e.dataTransfer.setData('text/plain','card:'+i);
            setTimeout(()=>card.classList.add('drag-src'),0);
        });
        card.addEventListener('dragend',()=>{
            card.classList.remove('drag-src');
            dragSrc=null;
        });
        card.addEventListener('dragover',e=>{
            if(dragSrc===null) return;
            e.preventDefault(); e.stopPropagation();
        });
        card.addEventListener('drop',e=>{
            e.preventDefault(); e.stopPropagation();
            if(dragSrc===null||dragSrc===i) return;
            const item=fields.splice(dragSrc,1)[0];
            fields.splice(i,0,item);
            dragSrc=null;
            renderCanvas(); renderAvail();
        });
    });

    syncHidden();
}

function cardHtml(f,i){
    const isSep=f.type==='separator';
    if(isSep) return `
    <div class="cc sep" draggable="true">
        <span class="dh" title="Drag reorder">⣿</span>
        <div class="cb">
            <span class="small text-muted fw-semibold">— SEPARATOR —</span>
            <input type="text" class="form-control form-control-sm mt-1" placeholder="Judul section..."
                   value="${esc(f.label||'')}" onchange="W.updF(${i},'label',this.value)">
        </div>
        <div class="ctrl">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="W.remF(${i})">×</button>
        </div>
    </div>`;

    const typeOpts=TYPES.filter(t=>t!=='separator').map(t=>`<option value="${t}"${f.type===t?' selected':''}>${t}</option>`).join('');
    const widthOpts=WO.map(([v,l])=>`<option value="${v}"${(f.width||'half')===v?' selected':''}>${l}</option>`).join('');

    let extra='';
    if(f.type==='badge'){
        extra=`<div class="mt-1"><input type="text" class="form-control form-control-sm font-monospace"
            placeholder='{"RUSAK":"danger","BAIK":"success"}'
            value="${esc(f.colors?JSON.stringify(f.colors):'')}"
            onchange="W.updFJ(${i},'colors',this.value)"></div>`;
    }
    if(f.type==='table'){
        extra=`<div class="row g-1 mt-1">
            <div class="col-6"><input type="text" class="form-control form-control-sm font-monospace"
                placeholder="Kolom: kode,nama,qty" value="${esc((f.columns||[]).join(','))}"
                onchange="W.updFA(${i},'columns',this.value)"></div>
            <div class="col-6"><input type="text" class="form-control form-control-sm"
                placeholder="Label: Kode,Nama,Qty" value="${esc((f.col_labels||[]).join(','))}"
                onchange="W.updFA(${i},'col_labels',this.value)"></div>
        </div>`;
    }
    if(f.type==='currency'){
        extra=`<div class="mt-1"><input type="text" class="form-control form-control-sm"
            placeholder="Prefix: Rp " style="max-width:120px" value="${esc(f.prefix||'Rp ')}"
            onchange="W.updF(${i},'prefix',this.value)"></div>`;
    }

    return `
    <div class="cc" draggable="true">
        <span class="dh" title="Drag untuk reorder">⣿</span>
        <div class="cb">
            <div class="row g-1">
                <div class="col-3">
                    <input type="text" class="form-control form-control-sm font-monospace"
                           value="${esc(f.field||'')}" placeholder="field_name"
                           title="Nama field di context_json"
                           onchange="W.updF(${i},'field',this.value)">
                </div>
                <div class="col-3">
                    <input type="text" class="form-control form-control-sm"
                           value="${esc(f.label||'')}" placeholder="Label tampil"
                           onchange="W.updF(${i},'label',this.value)">
                </div>
                <div class="col-3">
                    <select class="form-select form-select-sm"
                            onchange="W.updFType(${i},this.value)">
                        ${typeOpts}
                    </select>
                </div>
                <div class="col-3">
                    <select class="form-select form-select-sm"
                            onchange="W.updF(${i},'width',this.value)">
                        ${widthOpts}
                    </select>
                </div>
            </div>
            ${extra}
        </div>
        <div class="ctrl">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="W.mvF(${i},-1)" ${i===0?'disabled':''}>↑</button>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="W.remF(${i})">×</button>
        </div>
    </div>`;
}

// ── Drop on canvas (from avail) ──────────────────────────────────
window.onDropCanvas=function(e){
    e.preventDefault();
    document.getElementById('canvas').classList.remove('canvas-hover');
    if(!dragFromAvail) return;
    const a=avail.find(x=>x.key===dragFromAvail);
    dragFromAvail=null;
    if(!a) return;
    if(!fields.some(s=>s.field===a.key)) addFromAvail(a);
    renderCanvas(); renderAvail();
};

// ── Sync hidden input ────────────────────────────────────────────
function syncHidden(){
    const clean=fields.filter(f=>f.field||f.type==='separator');
    const json=JSON.stringify(clean);
    document.getElementById('fs-hid').value=json;
    const raw=document.getElementById('raw-ed');
    if(raw&&!document.getElementById('raw-wrap').classList.contains('d-none')){
        raw.value=JSON.stringify(clean,null,2);
    }
}

// ── Exposed API (W) ──────────────────────────────────────────────
// Dipanggil dari onclick di cardHtml — harus global
window.W={
    updF:function(i,k,v){fields[i][k]=v;syncHidden();},
    updFJ:function(i,k,v){try{fields[i][k]=JSON.parse(v);}catch{fields[i][k]=null;}syncHidden();},
    updFA:function(i,k,v){fields[i][k]=v.split(',').map(s=>s.trim()).filter(Boolean);syncHidden();},
    updFType:function(i,v){
        fields[i].type=v;
        if(v==='currency'&&!fields[i].prefix) fields[i].prefix='Rp ';
        if(v==='badge'&&!fields[i].colors) fields[i].colors={};
        if(v==='table'&&!fields[i].columns) fields[i].columns=[];
        // Update width default
        if(v==='table'||v==='textarea') fields[i].width='full';
        renderCanvas(); renderAvail();
    },
    remF:function(i){fields.splice(i,1);renderCanvas();renderAvail();},
    mvF:function(i,d){
        const j=i+d;if(j<0||j>=fields.length)return;
        [fields[i],fields[j]]=[fields[j],fields[i]];
        renderCanvas();
    },
};

// ── Raw JSON sync ────────────────────────────────────────────────
window.syncFromRaw=function(){
    try{fields=JSON.parse(document.getElementById('raw-ed').value);renderCanvas();renderAvail();}catch{}
};

// ── Preview modal ────────────────────────────────────────────────
function buildPreview(){
    const ctx=flatData;
    if(!fields.length) return '<div class="text-muted text-center py-4">Belum ada field di canvas.</div>';
    let html='<div class="row g-3">';
    fields.forEach(f=>{
        const type=f.type||'text';
        const fld=f.field||'';
        const label=f.label||fld;
        const width=f.width||'half';
        const col=width==='full'?'col-12':width==='third'?'col-md-4':'col-md-6';
        const rv=fld?(ctx[fld]??null):null;
        const dv=rv!==null&&rv!==undefined&&rv!==''?rv:null;

        if(type==='separator'){
            html+=`<div class="col-12"><div class="pv-sep">${esc(label||'Section')}</div></div>`;
            return;
        }
        html+=`<div class="${col}"><div class="pv-label">${esc(label)}</div>`;
        if(type==='currency'){
            const p=f.prefix||'Rp ';
            html+=`<div class="pv-val pv-cur">${dv?(p+parseFloat(dv).toLocaleString('id-ID')):'<span class="text-muted">—</span>'}</div>`;
        } else if(type==='badge'){
            const colors=f.colors||{};
            const bc=colors[dv]||'secondary';
            html+=`<div class="pv-val">${dv?`<span class="badge bg-${bc} px-3 py-1">${esc(String(dv))}</span>`:'<span class="text-muted">—</span>'}</div>`;
        } else if(type==='number'){
            html+=`<div class="pv-val">${dv?(parseFloat(dv)).toLocaleString('id-ID'):'<span class="text-muted">—</span>'}</div>`;
        } else if(type==='date'){
            let disp='—';
            if(dv){try{disp=new Date(dv).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'});}catch{disp=dv;}}
            html+=`<div class="pv-val">${disp}</div>`;
        } else if(type==='datetime'){
            let disp='—';
            if(dv){try{disp=new Date(dv).toLocaleString('id-ID');}catch{disp=dv;}}
            html+=`<div class="pv-val">${disp}</div>`;
        } else if(type==='textarea'){
            html+=`<div class="pv-val" style="white-space:pre-wrap;background:#f8f9fa;padding:6px;border-radius:4px;min-height:40px">${dv?esc(String(dv)):'<span class="text-muted">—</span>'}</div>`;
        } else if(type==='image'){
            html+=`<div class="pv-val">${dv?`<span class="badge bg-info"><i class="bi bi-image"></i> ${esc(String(dv).substring(0,40))}</span>`:'<span class="text-muted">—</span>'}</div>`;
        } else if(type==='table'){
            const rows=Array.isArray(dv)?dv:(Array.isArray(rv)?rv:[]);
            const cols=f.columns||(rows[0]?Object.keys(rows[0]):[]);
            const clabels=f.col_labels||cols;
            html+=`<div class="pv-val p-0"><div class="table-responsive"><table class="pv-tbl">`;
            html+=`<thead><tr>${clabels.map(c=>`<th>${esc(c)}</th>`).join('')}</tr></thead><tbody>`;
            if(rows.length){
                rows.slice(0,5).forEach(row=>{html+=`<tr>${cols.map(c=>`<td>${esc(String(row[c]??''))}</td>`).join('')}</tr>`;});
                if(rows.length>5) html+=`<tr><td colspan="${cols.length}" class="text-center text-muted">... +${rows.length-5} baris lagi</td></tr>`;
            } else html+=`<tr><td colspan="${cols.length}" class="text-muted text-center">Tidak ada data</td></tr>`;
            html+=`</tbody></table></div></div>`;
        } else if(type==='list'){
            const items=Array.isArray(dv)?dv:[];
            html+=`<div class="pv-val">${items.length?items.slice(0,5).map(it=>`<span class="badge bg-light text-dark border me-1">${esc(String(it))}</span>`).join(''):'<span class="text-muted">—</span>'}</div>`;
        } else {
            html+=`<div class="pv-val">${dv?esc(String(dv)):'<span class="text-muted">—</span>'}</div>`;
        }
        html+=`</div>`;
    });
    html+='</div>';
    return html;
}

// ── Event bindings ───────────────────────────────────────────────
function bindEvents(){
    document.getElementById('btn-parse').addEventListener('click',function(){
        const raw=document.getElementById('smp-ta').value.trim();
        if(!raw){alert('Paste JSON dulu.');return;}
        let parsed;
        try{parsed=JSON.parse(raw);}catch(e){alert('JSON tidak valid: '+e.message);return;}
        document.getElementById('smp-hid').value=raw;
        avail=[]; flatData={};
        flatten(parsed,'',avail,flatData);
        renderAvail();
    });

    document.getElementById('btn-beautify').addEventListener('click',function(){
        const ta=document.getElementById('smp-ta');
        try{ta.value=JSON.stringify(JSON.parse(ta.value),null,2);}
        catch(e){alert('JSON tidak valid: '+e.message);}
    });

    document.getElementById('btn-load-sfa').addEventListener('click',function(){
        document.getElementById('smp-ta').value=JSON.stringify(SFA_SAMPLE,null,2);
        document.getElementById('smp-hid').value=JSON.stringify(SFA_SAMPLE);
        avail=[]; flatData={};
        flatten(SFA_SAMPLE,'',avail,flatData);
        renderAvail();
    });

    document.getElementById('btn-preview').addEventListener('click',function(){
        document.getElementById('preview-body').innerHTML=buildPreview();
        new bootstrap.Modal(document.getElementById('preview-modal')).show();
    });

    document.getElementById('btn-raw').addEventListener('click',function(){
        const wrap=document.getElementById('raw-wrap');
        wrap.classList.toggle('d-none');
        if(!wrap.classList.contains('d-none')){
            document.getElementById('raw-ed').value=
                JSON.stringify(fields.filter(f=>f.field||f.type==='separator'),null,2);
        }
    });

    document.getElementById('btn-clear').addEventListener('click',function(){
        if(confirm('Hapus semua field di canvas?')){fields=[];renderCanvas();renderAvail();}
    });

    document.getElementById('btn-add-sep').addEventListener('click',function(){
        fields.push({field:'',label:'',type:'separator',width:'full'});
        renderCanvas();
    });

    document.getElementById('btn-mf').addEventListener('click',addManual);
    document.getElementById('mf-inp').addEventListener('keydown',function(e){
        if(e.key==='Enter'){e.preventDefault();addManual();}
    });

    function addManual(){
        const v=document.getElementById('mf-inp').value.trim();
        if(!v)return;
        if(!fields.some(s=>s.field===v)){
            fields.push({field:v,label:lbl(v),type:'text',width:'half'});
            renderCanvas();renderAvail();
        }
        document.getElementById('mf-inp').value='';
    }
}

// ── Init ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',function(){
    // Load existing schema
    const rawSchema=document.getElementById('fs-hid').value.trim();
    if(rawSchema){try{fields=JSON.parse(rawSchema);}catch{}}

    // Load existing sample
    const rawSample=document.getElementById('smp-hid').value.trim();
    if(rawSample){
        try{
            const p=JSON.parse(rawSample);
            flatten(p,'',avail,flatData);
        }catch{}
    }

    renderCanvas();
    renderAvail();
    bindEvents();
});

})(); // end IIFE
</script>
<?php /**PATH /var/www/html/approval_center/resources/views/master/document_type/_form.blade.php ENDPATH**/ ?>