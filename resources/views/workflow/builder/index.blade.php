<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Flow Builder — {{ optional($version->flowDefinition)->flow_code }} v{{ $version->version_no }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    {{-- React 18 UMD --}}
    <script src="https://cdn.jsdelivr.net/npm/react@18.3.1/umd/react.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/react-dom@18.3.1/umd/react-dom.production.min.js"></script>
    {{-- ReactFlow v11 — stable dengan React 18, UMD proven --}}
    <script src="https://cdn.jsdelivr.net/npm/reactflow@11.11.4/dist/umd/index.js"></script>
    <link  href="https://cdn.jsdelivr.net/npm/reactflow@11.11.4/dist/style.css" rel="stylesheet">

    <style>
        /* ===== THEME TOKENS ===== dark = default, light via html[data-theme=light] */
        :root{
            --bg:#0d1117; --surface:#161b22; --raised:#21262d;
            --border:#21262d; --border2:#30363d;
            --fg:#e6edf3; --fg2:#8b949e; --fg3:#6e7681; --fg4:#484f58;
            --accent:#58a6ff; --rf-dots:#21262d; --rf-mask:rgba(13,17,23,.7);
            --overlay:rgba(13,17,23,.92);
            /* warna chip node (dark) */
            --n-start-bg:#0d4429; --n-start-bd:#238636; --n-start-tx:#3fb950;
            --n-appr-bg:#1c1c3a;  --n-appr-bd:#6e40c9;  --n-appr-tx:#a371f7;
            --n-dec-bg:#2d1d00;   --n-dec-bd:#9e6a03;   --n-dec-tx:#d29922;
            --n-end-bg:#161b22;   --n-end-bd:#30363d;   --n-end-tx:#6e7681;
            --n-title:#e6edf3;    --n-sub:#6e7681;
        }
        html[data-theme="light"]{
            --bg:#f6f8fa; --surface:#ffffff; --raised:#eaeef2;
            --border:#d0d7de; --border2:#d8dee4;
            --fg:#1f2328; --fg2:#57606a; --fg3:#6e7781; --fg4:#8c959f;
            --accent:#0969da; --rf-dots:#d0d7de; --rf-mask:rgba(255,255,255,.6);
            --overlay:rgba(246,248,250,.92);
            /* warna chip node (light) */
            --n-start-bg:#dafbe1; --n-start-bd:#2da44e; --n-start-tx:#1a7f37;
            --n-appr-bg:#f3e8ff;  --n-appr-bd:#8250df;  --n-appr-tx:#6639ba;
            --n-dec-bg:#fff8c5;   --n-dec-bd:#bf8700;   --n-dec-tx:#9a6700;
            --n-end-bg:#eaeef2;   --n-end-bd:#afb8c1;   --n-end-tx:#57606a;
            --n-title:#1f2328;    --n-sub:#57606a;
        }

        *{box-sizing:border-box}
        html,body{margin:0;padding:0;height:100%;overflow:hidden;background:var(--bg);
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px;color:var(--fg)}
        #app{display:flex;flex-direction:column;height:100vh}

        /* TOOLBAR */
        #tb{display:flex;align-items:center;gap:6px;padding:6px 12px;background:var(--surface);
            border-bottom:1px solid var(--border);flex-shrink:0;flex-wrap:wrap}
        .ftitle{font-weight:700;font-size:14px;color:var(--fg)}
        .bdg{padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600}
        .bv{background:#1f6feb22;color:#58a6ff;border:1px solid #1f6feb}
        .bDRAFT{background:var(--raised);color:var(--fg2);border:1px solid var(--border2)}
        .bACTIVE{background:#1a4731;color:#3fb950;border:1px solid #238636}
        .bINACTIVE{background:#3d1c02;color:#d29922;border:1px solid #9e6a03}
        .sep{width:1px;height:22px;background:var(--border);margin:0 2px}
        .btn{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;
            border:none;cursor:pointer;font-size:12px;font-weight:600;transition:opacity .15s}
        .btn:disabled{opacity:.35;cursor:not-allowed}
        .btn:hover:not(:disabled){opacity:.8}
        .bback{background:var(--raised);color:var(--fg2)}
        .bsave{background:#1f6feb;color:#fff}
        .bval{background:#9e6a03;color:#fff}
        .bdep{background:#238636;color:#fff}
        .bcln{background:#6e40c9;color:#fff}
        .bthm{background:var(--raised);color:var(--fg2);width:30px;justify-content:center;padding:4px 0}
        .spc{flex:1}
        #si{font-size:11px;color:var(--fg3)}

        /* MAIN */
        #main{display:flex;flex:1;overflow:hidden}

        /* PALETTE */
        #pal{width:148px;background:var(--surface);border-right:1px solid var(--border);
            padding:10px 8px;flex-shrink:0;overflow-y:auto}
        .ptitle{font-size:10px;font-weight:700;text-transform:uppercase;
            letter-spacing:.08em;color:var(--fg4);margin-bottom:8px}
        .pnode{display:flex;align-items:center;gap:6px;padding:7px 9px;border-radius:6px;
            cursor:grab;font-size:12px;font-weight:600;user-select:none;margin-bottom:6px;
            border:1px solid transparent;transition:transform .1s}
        .pnode:hover{transform:translateX(3px)}
        .pnS{background:var(--n-start-bg);border-color:var(--n-start-bd);color:var(--n-start-tx)}
        .pnA{background:var(--n-appr-bg);border-color:var(--n-appr-bd);color:var(--n-appr-tx)}
        .pnD{background:var(--n-dec-bg);border-color:var(--n-dec-bd);color:var(--n-dec-tx)}
        .pnE{background:var(--n-end-bg);border-color:var(--n-end-bd);color:var(--n-end-tx)}
        .psep{height:1px;background:var(--border);margin:8px 0}
        .phint{font-size:10px;color:var(--fg4);line-height:1.5}

        /* CANVAS */
        #cw{flex:1;position:relative;overflow:hidden;background:var(--bg)}
        #rf{width:100%;height:100%}
        .react-flow{background:var(--bg)}
        .react-flow__edge-path{stroke:var(--fg4);stroke-width:2}
        .react-flow__edge.selected .react-flow__edge-path{stroke:var(--accent);stroke-width:2.5}
        .react-flow__arrowhead polygon{fill:var(--fg4)}
        .react-flow__controls{background:var(--surface);border:1px solid var(--border);border-radius:6px}
        .react-flow__controls-button{background:var(--surface);border-bottom:1px solid var(--border);color:var(--fg2);fill:var(--fg2)}
        .react-flow__controls-button:hover{background:var(--raised)}
        .react-flow__minimap{background:var(--bg);border:1px solid var(--border)}

        #lkbanner{position:absolute;top:12px;left:50%;transform:translateX(-50%);
            background:#3d1c02;border:1px solid #da3633;border-radius:8px;
            padding:8px 16px;font-size:12px;color:#ff7b72;z-index:50;
            display:flex;align-items:center;gap:8px;white-space:nowrap}
        #loading{position:absolute;inset:0;background:var(--overlay);
            display:flex;align-items:center;justify-content:center;
            flex-direction:column;gap:10px;z-index:99}
        .spin{width:32px;height:32px;border:3px solid var(--border);border-top-color:var(--accent);
            border-radius:50%;animation:sp .7s linear infinite}
        @keyframes sp{to{transform:rotate(360deg)}}
        #cctrls{position:absolute;bottom:14px;left:14px;display:flex;gap:6px;z-index:10}
        .ccb{width:30px;height:30px;border-radius:6px;background:var(--surface);border:1px solid var(--border);
            color:var(--fg2);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center}
        .ccb:hover{background:var(--raised);color:var(--fg)}

        /* RIGHT PANEL */
        #rp{width:270px;background:var(--surface);border-left:1px solid var(--border);
            display:flex;flex-direction:column;flex-shrink:0;overflow:hidden;transition:width .2s}
        #rp.cl{width:0;border:none;overflow:hidden}
        #rph{padding:8px 10px;background:var(--raised);font-size:12px;font-weight:700;
            display:flex;align-items:center;justify-content:space-between;flex-shrink:0;color:var(--fg)}
        #rph button{background:none;border:none;color:var(--fg3);cursor:pointer;font-size:17px;line-height:1}
        #rpb{flex:1;overflow-y:auto;padding:10px}
        .ps{margin-bottom:12px}
        .pst{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
            color:var(--fg4);margin-bottom:6px;cursor:pointer;user-select:none;display:flex;align-items:center}
        .pst::after{content:'▾';margin-left:auto}
        .pst.col::after{transform:rotate(-90deg);display:inline-block}
        .psc.col{display:none}
        .fg{margin-bottom:8px}
        .fg label{display:block;font-size:11px;color:var(--fg2);margin-bottom:3px}
        .fg input,.fg select,.fg textarea{width:100%;padding:5px 7px;background:var(--bg);
            border:1px solid var(--border);border-radius:5px;color:var(--fg);font-size:12px;outline:none}
        .fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--accent)}
        .fg textarea{resize:vertical;min-height:60px;font-family:monospace}
        .fg small{font-size:10px;color:var(--fg4);margin-top:2px;display:block}
        .cbr{display:flex;align-items:center;gap:6px;padding:3px 0;font-size:12px}
        .cbr input[type=checkbox]{width:auto;cursor:pointer}
        .cbr label{cursor:pointer;margin:0}
        .ri{background:var(--bg);border:1px solid var(--border);border-radius:5px;
            padding:7px;margin-bottom:6px;position:relative}
        .rrm{position:absolute;top:5px;right:5px;background:none;border:none;
            color:#da3633;cursor:pointer;font-size:14px;padding:0;line-height:1}
        .badd{width:100%;padding:5px;border-radius:5px;border:1px dashed var(--border);
            background:transparent;color:var(--fg3);font-size:11px;cursor:pointer;margin-top:4px}
        .badd:hover{border-color:var(--fg4);color:var(--fg2)}
        .arow{display:flex;gap:6px;margin-top:12px;border-top:1px solid var(--border);padding-top:10px}
        .bap{flex:1;padding:6px;border-radius:5px;border:none;background:#1f6feb;
            color:#fff;font-size:12px;font-weight:600;cursor:pointer}
        .bde{flex:1;padding:6px;border-radius:5px;border:none;background:#3d1c02;
            color:#ff7b72;font-size:12px;font-weight:600;cursor:pointer}

        /* BOTTOM */
        #bot{background:var(--bg);border-top:1px solid var(--border);flex-shrink:0;max-height:155px;overflow-y:auto}
        #bh{display:flex;align-items:center;gap:8px;padding:5px 12px;background:var(--surface);
            border-bottom:1px solid var(--border);font-size:11px;font-weight:700;cursor:pointer;user-select:none;color:var(--fg)}
        #bh .spc{flex:1}
        #vo{padding:6px 12px}
        .ve{color:#ff7b72;font-size:11px;padding:1px 0}
        .vw{color:#d29922;font-size:11px;padding:1px 0}
        .vok{color:#3fb950;font-size:11px;padding:1px 0}
        .vmt{color:var(--fg4);font-size:11px;font-style:italic;padding:3px 0}

        /* TOAST */
        #tw{position:fixed;bottom:20px;right:20px;display:flex;flex-direction:column;gap:6px;z-index:9999}
        .toast{padding:9px 14px;border-radius:7px;font-size:12px;font-weight:600;
            display:flex;align-items:center;gap:7px;animation:tIn .25s ease;max-width:340px}
        @keyframes tIn{from{transform:translateX(100%);opacity:0}to{transform:none;opacity:1}}
        .tok{background:#0d4429;border:1px solid #238636;color:#3fb950}
        .ter{background:#3d1c02;border:1px solid #da3633;color:#ff7b72}
        .tin{background:#1c1c3a;border:1px solid #6e40c9;color:#a371f7}

        /* MODAL */
        #mdl{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;
            align-items:center;justify-content:center;z-index:9998}
        #mdl.show{display:flex}
        .mbox{background:var(--surface);border:1px solid var(--border);border-radius:10px;
            padding:20px;max-width:360px;width:90%}
        .mbox h5{margin:0 0 6px;font-size:14px;color:var(--fg)}
        .mbox p{margin:0 0 14px;font-size:12px;color:var(--fg2)}
        .mbtns{display:flex;gap:8px;justify-content:flex-end}

        ::-webkit-scrollbar{width:4px}
        ::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
    </style>
    <script>
        // Terapkan tema sedini mungkin (cegah flash) — default 'dark'.
        (function(){
            try {
                var t = localStorage.getItem('builder_theme') || 'dark';
                document.documentElement.setAttribute('data-theme', t);
            } catch(e){}
        })();
        function applyThemeIcon(){
            try {
                var t  = document.documentElement.getAttribute('data-theme') || 'dark';
                var ic = document.getElementById('bthm-ic');
                if (ic) ic.className = (t === 'light') ? 'bi bi-sun' : 'bi bi-moon-stars';
            } catch(e){}
        }
        function toggleTheme(){
            var cur  = document.documentElement.getAttribute('data-theme') || 'dark';
            var next = (cur === 'light') ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('builder_theme', next); } catch(e){}
            applyThemeIcon();
        }
        document.addEventListener('DOMContentLoaded', applyThemeIcon);
    </script>
</head>
<body>
<div id="app">

    <div id="tb">
        <button class="btn bback" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        <div class="sep"></div>
        <span class="ftitle">{{ optional($version->flowDefinition)->flow_code }}</span>
        <span class="bdg bv">v{{ $version->version_no }} — {{ $version->version_name }}</span>
        <span class="bdg b{{ $version->status }}" id="stbdg">{{ $version->status }}</span>
        <div class="sep"></div>
        <button class="btn bsave" id="bsave" onclick="doSave()" {{ $isLocked?'disabled':'' }}>
            <i class="bi bi-floppy"></i> Save
        </button>
        <button class="btn bval" onclick="doValidate()">
            <i class="bi bi-check-circle"></i> Validate
        </button>
        <button class="btn bdep" id="bdep" onclick="doDeploy()" {{ $isLocked?'disabled':'' }}>
            <i class="bi bi-rocket"></i> Deploy
        </button>
        <button class="btn bcln" onclick="doClone()">
            <i class="bi bi-copy"></i> Clone
        </button>
        <button class="btn" style="background:#0d4429;color:#3fb950;border:1px solid #238636" onclick="showHelp()">
            <i class="bi bi-question-circle"></i> Panduan
        </button>
        <div class="spc"></div>
        <button class="btn bthm" id="bthm" onclick="toggleTheme()" title="Ganti tema terang/gelap">
            <i class="bi bi-moon-stars" id="bthm-ic"></i>
        </button>
        <span id="si">memuat...</span>
    </div>

    <div id="main">
        <div id="pal">
            <div class="ptitle">Node Types</div>
            <div class="pnode pnS" draggable="true" ondragstart="palDrag(event,'START')">▶ START</div>
            <div class="pnode pnA" draggable="true" ondragstart="palDrag(event,'APPROVAL')">✓ APPROVAL</div>
            <div class="pnode pnD" draggable="true" ondragstart="palDrag(event,'DECISION')">◆ DECISION</div>
            <div class="pnode pnE" draggable="true" ondragstart="palDrag(event,'END')">■ END</div>
            <div class="psep"></div>
            <div class="ptitle">Tips</div>
            <div class="phint">
                Drag node ke canvas<br><br>
                Sambungkan node dengan menarik titik konektor<br><br>
                Klik node/edge untuk edit di panel kanan<br><br>
                Tekan Del untuk hapus
            </div>
        </div>

        <div id="cw" ondrop="canvasDrop(event)" ondragover="event.preventDefault()">
            <div id="rf"></div>

            @if($isLocked)
            <div id="lkbanner">
                <i class="bi bi-lock-fill"></i>
                Flow LOCKED — sudah digunakan. Gunakan Clone untuk mengubah.
            </div>
            @endif

            <div id="loading">
                <div class="spin"></div>
                <span style="color:#6e7681;font-size:12px">Memuat canvas...</span>
            </div>

            <div id="cctrls">
                <button class="ccb" onclick="rfCtrl('fit')"  title="Fit View"><i class="bi bi-fullscreen"></i></button>
                <button class="ccb" onclick="rfCtrl('in')"   title="Zoom In"><i class="bi bi-zoom-in"></i></button>
                <button class="ccb" onclick="rfCtrl('out')"  title="Zoom Out"><i class="bi bi-zoom-out"></i></button>
            </div>
        </div>

        <div id="rp" class="cl">
            <div id="rph">
                <span id="rphtitle"><i class="bi bi-info-circle"></i> Properties</span>
                <button onclick="closePanel()">×</button>
            </div>
            <div id="rpb"><div class="vmt">Pilih node atau edge di canvas.</div></div>
        </div>
    </div>

    <div id="bot">
        <div id="bh" onclick="togBot()">
            <i class="bi bi-terminal"></i> Validation
            <span class="spc"></span>
            <span id="vsum" style="color:#6e7681">—</span>
            <span id="bico" style="margin-left:6px">▼</span>
        </div>
        <div id="vo" style="display:none"><div class="vmt">Belum dijalankan. Klik Validate.</div></div>
    </div>
</div>

<div id="tw"></div>

<div id="mdl">
    <div class="mbox">
        <h5 id="mtitle">Konfirmasi</h5>
        <p  id="mmsg"></p>
        <div class="mbtns">
            <button class="btn bback" id="mno">Batal</button>
            <button class="btn bsave" id="myes">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL PANDUAN PENGGUNAAN
     ============================================================ -->
<div id="help-modal" style="
    position:fixed;inset:0;background:rgba(0,0,0,.85);
    display:none;align-items:flex-start;justify-content:center;
    z-index:10000;padding:20px;overflow-y:auto">
    <div style="
        background:#161b22;border:1px solid #21262d;border-radius:12px;
        width:100%;max-width:780px;margin:auto;padding:28px 32px;
        position:relative">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;border-bottom:1px solid #21262d;padding-bottom:16px">
            <div>
                <div style="font-size:18px;font-weight:700;color:#e6edf3">
                    <i class="bi bi-diagram-3" style="color:#3fb950"></i>
                    Panduan Membuat Workflow Approval
                </div>
                <div style="font-size:12px;color:#6e7681;margin-top:4px">
                    Ikuti langkah-langkah berikut untuk membuat alur persetujuan dokumen
                </div>
            </div>
            <button onclick="hideHelp()" style="
                background:none;border:none;color:#6e7681;cursor:pointer;
                font-size:22px;line-height:1;padding:4px 8px">×</button>
        </div>

        <!-- Intro -->
        <div style="background:#0d1117;border-radius:8px;padding:14px 16px;margin-bottom:24px;border-left:3px solid #388bfd">
            <div style="font-size:12px;color:#8b949e;line-height:1.7">
                <strong style="color:#58a6ff">Apa itu Workflow Approval?</strong><br>
                Workflow adalah <em>alur perjalanan dokumen</em> mulai dari dikirim, melewati satu atau beberapa orang
                yang harus menyetujui, sampai akhirnya selesai (disetujui atau ditolak).<br><br>
                Contoh sederhana: Surat cuti harus disetujui oleh HRD → Manager → Direktur.
                Di sini Anda bisa merancang alur tersebut secara visual.
            </div>
        </div>

        <!-- KONSEP DASAR -->
        <div style="margin-bottom:24px">
            <div style="font-size:13px;font-weight:700;color:#e6edf3;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <span style="background:#1f6feb;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0">1</span>
                Pahami 4 Jenis Node (Kotak di Canvas)
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div style="background:#0d4429;border:1px solid #238636;border-radius:8px;padding:12px">
                    <div style="font-size:13px;font-weight:700;color:#3fb950;margin-bottom:6px">▶ START</div>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Titik awal workflow.</strong><br>
                        Setiap flow <em>wajib</em> punya tepat <strong>1 node START</strong>.
                        Dari sinilah dokumen masuk ke sistem saat pertama kali diajukan.<br>
                        <span style="color:#3fb950">→ Tidak perlu diisi apapun, cukup ada.</span>
                    </div>
                </div>
                <div style="background:#1c1c3a;border:1px solid #6e40c9;border-radius:8px;padding:12px">
                    <div style="font-size:13px;font-weight:700;color:#a371f7;margin-bottom:6px">✓ APPROVAL</div>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Langkah persetujuan.</strong><br>
                        Dokumen akan berhenti di sini menunggu seseorang untuk menyetujui atau menolak.
                        Anda tentukan <em>siapa</em> yang harus approve via <strong>Assignee Rule</strong>.<br>
                        <span style="color:#a371f7">→ Bisa dibuat banyak sesuai jumlah level persetujuan.</span>
                    </div>
                </div>
                <div style="background:#2d1d00;border:1px solid #9e6a03;border-radius:8px;padding:12px">
                    <div style="font-size:13px;font-weight:700;color:#d29922;margin-bottom:6px">◆ DECISION</div>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Persimpangan otomatis.</strong><br>
                        Sistem akan memilih jalur secara otomatis berdasarkan data dokumen.
                        Contoh: kalau nilai &gt; 10 juta → ke Direktur, kalau tidak → langsung selesai.<br>
                        <span style="color:#d29922">→ Gunakan ini untuk percabangan berdasarkan kondisi.</span>
                    </div>
                </div>
                <div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:12px">
                    <div style="font-size:13px;font-weight:700;color:#6e7681;margin-bottom:6px">■ END</div>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Titik akhir workflow.</strong><br>
                        Dokumen selesai diproses (disetujui, ditolak, atau dibatalkan).
                        Flow <em>wajib</em> punya minimal <strong>1 node END</strong>.<br>
                        <span style="color:#6e7681">→ Tidak perlu diisi apapun, cukup ada.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- LANGKAH 1: TAMBAH NODE -->
        <div style="margin-bottom:24px;background:#0d1117;border-radius:8px;padding:16px">
            <div style="font-size:13px;font-weight:700;color:#e6edf3;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                <span style="background:#1f6feb;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0">2</span>
                Tambahkan Node ke Canvas
            </div>
            <div style="font-size:12px;color:#8b949e;line-height:1.8;margin-bottom:10px">
                Di sebelah kiri ada <strong style="color:#e6edf3">Node Palette</strong> — berisi 4 jenis node.
                Cara menambahkan node ke canvas:
            </div>
            <div style="display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 12px;background:#161b22;border-radius:6px">
                    <span style="font-size:16px;flex-shrink:0">🖱️</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Drag & Drop</strong> — klik node di palette kiri, tahan, lalu lepas di area canvas (area gelap tengah).
                        Node akan muncul di lokasi Anda melepasnya.
                    </div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 12px;background:#161b22;border-radius:6px">
                    <span style="font-size:16px;flex-shrink:0">📝</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Edit node</strong> — klik node yang sudah ada di canvas.
                        Panel <em>Properties</em> akan muncul di sebelah kanan. Isi nama dan konfigurasi node, lalu klik <strong>Apply</strong>.
                    </div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 12px;background:#161b22;border-radius:6px">
                    <span style="font-size:16px;flex-shrink:0">↔️</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Pindahkan node</strong> — klik dan tahan node, lalu geser ke posisi yang diinginkan.
                    </div>
                </div>
            </div>

            <!-- Field penjelasan APPROVAL -->
            <div style="margin-top:12px;padding:10px 12px;background:#1c1c3a;border-radius:6px;border-left:3px solid #6e40c9">
                <div style="font-size:11px;font-weight:700;color:#a371f7;margin-bottom:8px">
                    Penjelasan field untuk node APPROVAL:
                </div>
                <div style="display:grid;grid-template-columns:1fr;gap:6px;font-size:11px;color:#8b949e;line-height:1.6">
                    <div><strong style="color:#e6edf3">Node Code</strong> — kode singkat unik, huruf kapital, contoh: <code style="background:#0d1117;padding:1px 5px;border-radius:3px">BMH</code>, <code style="background:#0d1117;padding:1px 5px;border-radius:3px">RRM</code>, <code style="background:#0d1117;padding:1px 5px;border-radius:3px">DIREKTUR</code></div>
                    <div><strong style="color:#e6edf3">Node Name</strong> — nama tampilan, contoh: <em>"Persetujuan Branch Manager"</em></div>
                    <div><strong style="color:#e6edf3">Approval Mode</strong> — cara approval saat ada lebih dari 1 approver:
                        <div style="margin-top:4px;margin-left:12px">
                            <div>• <strong style="color:#3fb950">ANY</strong> — cukup salah satu yang approve (paling umum)</div>
                            <div>• <strong style="color:#d29922">ALL</strong> — semua harus approve</div>
                            <div>• <strong style="color:#58a6ff">SEQUENTIAL</strong> — harus urut sesuai priority</div>
                        </div>
                    </div>
                    <div><strong style="color:#e6edf3">SLA Hours</strong> — batas waktu dalam jam (opsional). Contoh: isi <code style="background:#0d1117;padding:1px 5px;border-radius:3px">24</code> berarti harus direspon dalam 24 jam. Kosongkan jika tidak ada batas waktu.</div>
                </div>
            </div>
        </div>

        <!-- LANGKAH 3: ASSIGNEE RULE -->
        <div style="margin-bottom:24px;background:#0d1117;border-radius:8px;padding:16px">
            <div style="font-size:13px;font-weight:700;color:#e6edf3;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                <span style="background:#1f6feb;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0">3</span>
                Tentukan Siapa yang Approve (Assignee Rule)
            </div>
            <div style="font-size:12px;color:#8b949e;line-height:1.7;margin-bottom:10px">
                Untuk setiap node <strong style="color:#a371f7">APPROVAL</strong>, Anda <em>wajib</em> menentukan siapa yang harus approve.
                Klik node APPROVAL → di panel kanan ada bagian <strong style="color:#e6edf3">Assignee Rules</strong> → klik <strong>+ Tambah Assignee Rule</strong>.
            </div>
            <div style="padding:10px 12px;background:#161b22;border-radius:6px;font-size:11px;color:#8b949e;line-height:1.7">
                <strong style="color:#e6edf3">Pilihan Type (assignee_type):</strong>
                <div style="margin-top:6px;display:flex;flex-direction:column;gap:4px">
                    <div style="padding:5px 8px;background:#0d1117;border-radius:4px">
                        <strong style="color:#3fb950">USER</strong> → approver adalah 1 orang tertentu.
                        Isi Value dengan <strong>user_ref</strong> (NPK karyawan), contoh: <code style="background:#161b22;padding:1px 5px;border-radius:3px">EMP001</code>
                    </div>
                    <div style="padding:5px 8px;background:#0d1117;border-radius:4px">
                        <strong style="color:#58a6ff">ROLE</strong> → semua user dengan role tertentu bisa approve.
                        Isi Value dengan nama role, contoh: <code style="background:#161b22;padding:1px 5px;border-radius:3px">APPROVER</code>
                    </div>
                    <div style="padding:5px 8px;background:#0d1117;border-radius:4px">
                        <strong style="color:#a371f7">GROUP</strong> → pool approver (grup yang sudah dibuat di Master → Approval Group).
                        Isi Value dengan kode group, contoh: <code style="background:#161b22;padding:1px 5px;border-radius:3px">GRP_FINANCE</code>
                    </div>
                    <div style="padding:5px 8px;background:#0d1117;border-radius:4px">
                        <strong style="color:#d29922">POSITION</strong> → semua user dengan jabatan tertentu.
                        Isi Value dengan kode jabatan, contoh: <code style="background:#161b22;padding:1px 5px;border-radius:3px">BM</code> (Branch Manager)
                    </div>
                    <div style="padding:5px 8px;background:#0d1117;border-radius:4px">
                        <strong style="color:#ff7b72">SUPERIOR</strong> → atasan langsung dari orang yang mengajukan dokumen (otomatis, tidak perlu isi Value).
                        Syarat: field <em>Atasan</em> di Master → User harus sudah diisi.
                    </div>
                    <div style="padding:5px 8px;background:#0d1117;border-radius:4px">
                        <strong style="color:#6e7681">FIELD_USER</strong> → nama approver ditentukan oleh aplikasi asal saat mengirim dokumen.
                        Isi Value dengan nama field di data dokumen, contoh: <code style="background:#161b22;padding:1px 5px;border-radius:3px">pic_manager</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- LANGKAH 4: EDGE -->
        <div style="margin-bottom:24px;background:#0d1117;border-radius:8px;padding:16px">
            <div style="font-size:13px;font-weight:700;color:#e6edf3;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                <span style="background:#1f6feb;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0">4</span>
                Hubungkan Node dengan Panah (Edge)
            </div>
            <div style="font-size:12px;color:#8b949e;line-height:1.7;margin-bottom:10px">
                Panah menentukan <em>ke mana dokumen pergi selanjutnya</em> setelah sebuah aksi dilakukan.
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
                <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 12px;background:#161b22;border-radius:6px">
                    <span style="font-size:16px;flex-shrink:0">🔗</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Cara membuat panah</strong> — arahkan mouse ke tepi kanan node asal sampai muncul titik biru,
                        lalu klik-tahan dan seret ke node tujuan. Lepas di atas node tujuan.
                    </div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 12px;background:#161b22;border-radius:6px">
                    <span style="font-size:16px;flex-shrink:0">✏️</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        <strong style="color:#e6edf3">Edit panah</strong> — klik panah yang sudah ada, panel Properties akan muncul di kanan.
                        Field terpenting adalah <strong>action_code</strong>.
                    </div>
                </div>
            </div>
            <div style="padding:10px 12px;background:#161b22;border-radius:6px;font-size:11px;color:#8b949e;line-height:1.7">
                <strong style="color:#e6edf3">Penjelasan field Edge:</strong>
                <div style="margin-top:6px;display:flex;flex-direction:column;gap:5px">
                    <div><strong style="color:#e6edf3">action_code</strong> — <em>aksi apa yang memicu panah ini.</em> Pilihan standar:
                        <div style="margin-top:3px;margin-left:12px">
                            <div>• <strong style="color:#3fb950">SUBMIT</strong> — dokumen baru masuk (dipakai dari START)</div>
                            <div>• <strong style="color:#3fb950">APPROVE</strong> — approver menyetujui</div>
                            <div>• <strong style="color:#ff7b72">REJECT</strong> — approver menolak</div>
                            <div>• <strong style="color:#d29922">RETURN</strong> — approver mengembalikan untuk direvisi</div>
                            <div>• <strong style="color:#6e7681">AUTO</strong> — sistem otomatis meneruskan (dipakai dari DECISION)</div>
                        </div>
                    </div>
                    <div><strong style="color:#e6edf3">priority_no</strong> — urutan evaluasi. Angka lebih kecil = diperiksa lebih dulu. Pakai 100, 200, 300 dst agar mudah disisipkan.</div>
                    <div><strong style="color:#e6edf3">final_status</strong> — status akhir dokumen saat melewati panah ini dan mencapai END.
                        Contoh: isi <code style="background:#0d1117;padding:1px 5px;border-radius:3px">APPROVED</code> untuk panah APPROVE ke END,
                        dan <code style="background:#0d1117;padding:1px 5px;border-radius:3px">REJECTED</code> untuk panah REJECT ke END.
                    </div>
                    <div><strong style="color:#e6edf3">Default transition</strong> — centang ini pada salah satu panah jika Anda ingin ada jalur cadangan
                        ketika tidak ada kondisi yang cocok (terutama dari DECISION node).</div>
                    <div><strong style="color:#e6edf3">condition_json</strong> — isi hanya untuk DECISION node. Kosongkan untuk alur normal.
                        Contoh kondisi nilai &gt; 10 juta:<br>
                        <code style="background:#0d1117;padding:2px 6px;border-radius:3px;font-size:10px">{"op":"&gt;","field":"nilai_retur","value":10000000}</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- LANGKAH 5: SAVE VALIDATE DEPLOY -->
        <div style="margin-bottom:24px;background:#0d1117;border-radius:8px;padding:16px">
            <div style="font-size:13px;font-weight:700;color:#e6edf3;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <span style="background:#1f6feb;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0">5</span>
                Simpan → Validasi → Deploy
            </div>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;background:#1f6feb22;border:1px solid #1f6feb44;border-radius:6px">
                    <span style="background:#1f6feb;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;flex-shrink:0;margin-top:1px">SAVE</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        Simpan semua perubahan canvas ke database. <strong style="color:#e6edf3">Lakukan ini setiap selesai membuat perubahan.</strong>
                        Status akan tetap DRAFT — dokumen belum bisa menggunakan flow ini.
                    </div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;background:#9e6a0322;border:1px solid #9e6a0344;border-radius:6px">
                    <span style="background:#9e6a03;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;flex-shrink:0;margin-top:1px">VALIDATE</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        Sistem memeriksa apakah flow sudah benar: ada START & END, semua APPROVAL punya approver,
                        semua node terhubung, tidak ada jalur yang tergantung. <strong style="color:#e6edf3">Wajib VALID sebelum bisa Deploy.</strong>
                        Hasil error akan muncul di panel bawah.
                    </div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;background:#23863622;border:1px solid #23863644;border-radius:6px">
                    <span style="background:#238636;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;flex-shrink:0;margin-top:1px">DEPLOY</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        Aktifkan flow ini. Setelah Deploy, status menjadi <strong style="color:#3fb950">ACTIVE</strong> dan dokumen sudah bisa
                        menggunakan alur ini. <strong style="color:#ff7b72">Setelah ACTIVE dan sudah dipakai, flow tidak bisa diedit langsung.</strong>
                        Gunakan <strong>Clone</strong> untuk membuat versi baru jika perlu perubahan.
                    </div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;background:#6e40c922;border:1px solid #6e40c944;border-radius:6px">
                    <span style="background:#6e40c9;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;flex-shrink:0;margin-top:1px">CLONE</span>
                    <div style="font-size:11px;color:#8b949e;line-height:1.6">
                        Buat salinan flow ini sebagai versi baru (DRAFT). Gunakan jika ingin mengubah flow yang
                        sudah ACTIVE tanpa mengganggu dokumen yang sedang berjalan.
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTOH FLOW SEDERHANA -->
        <div style="margin-bottom:24px;background:#0d1117;border-radius:8px;padding:16px">
            <div style="font-size:13px;font-weight:700;color:#e6edf3;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <span style="background:#1f6feb;color:#fff;border-radius:50%;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0">💡</span>
                Contoh: Flow 2 Level Persetujuan
            </div>
            <div style="font-size:11px;color:#8b949e;line-height:1.8;margin-bottom:12px">
                Misalnya Anda ingin membuat flow: dokumen harus disetujui Manager dulu, lalu Direktur.
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;font-size:11px">
                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#161b22;border-radius:5px">
                    <span style="color:#3fb950;font-weight:700;flex-shrink:0">Step 1</span>
                    <span style="color:#484f58">→</span>
                    <span style="color:#8b949e">Drag node <strong style="color:#3fb950">START</strong> ke canvas, beri nama <em>"Mulai"</em></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#161b22;border-radius:5px">
                    <span style="color:#a371f7;font-weight:700;flex-shrink:0">Step 2</span>
                    <span style="color:#484f58">→</span>
                    <span style="color:#8b949e">Drag node <strong style="color:#a371f7">APPROVAL</strong> → Node Code: <code style="background:#0d1117;padding:1px 4px;border-radius:3px">MGR</code>, Nama: <em>"Persetujuan Manager"</em></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#161b22;border-radius:5px">
                    <span style="color:#a371f7;font-weight:700;flex-shrink:0">Step 3</span>
                    <span style="color:#484f58">→</span>
                    <span style="color:#8b949e">Di node MGR, tambah Assignee Rule: Type = <strong>POSITION</strong>, Value = <code style="background:#0d1117;padding:1px 4px;border-radius:3px">MANAGER</code></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#161b22;border-radius:5px">
                    <span style="color:#a371f7;font-weight:700;flex-shrink:0">Step 4</span>
                    <span style="color:#484f58">→</span>
                    <span style="color:#8b949e">Drag node <strong style="color:#a371f7">APPROVAL</strong> lagi → Node Code: <code style="background:#0d1117;padding:1px 4px;border-radius:3px">DIR</code>, Nama: <em>"Persetujuan Direktur"</em> → Assignee Rule: Type = <strong>USER</strong>, Value = NPK direktur</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#161b22;border-radius:5px">
                    <span style="color:#6e7681;font-weight:700;flex-shrink:0">Step 5</span>
                    <span style="color:#484f58">→</span>
                    <span style="color:#8b949e">Drag node <strong style="color:#6e7681">END</strong> → beri nama <em>"Selesai"</em></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#161b22;border-radius:5px">
                    <span style="color:#58a6ff;font-weight:700;flex-shrink:0">Step 6</span>
                    <span style="color:#484f58">→</span>
                    <span style="color:#8b949e">Hubungkan: <strong>START → MGR</strong> (action: SUBMIT), <strong>MGR → DIR</strong> (action: APPROVE), <strong>MGR → END</strong> (action: REJECT, final_status: REJECTED), <strong>DIR → END</strong> (action: APPROVE, final_status: APPROVED), <strong>DIR → END</strong> (action: REJECT, final_status: REJECTED)</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#0d4429;border:1px solid #238636;border-radius:5px">
                    <span style="color:#3fb950;font-weight:700;flex-shrink:0">Step 7</span>
                    <span style="color:#484f58">→</span>
                    <span style="color:#8b949e">Klik <strong style="color:#3fb950">Save</strong> → klik <strong style="color:#d29922">Validate</strong> → kalau VALID klik <strong style="color:#3fb950">Deploy</strong> ✓</span>
                </div>
            </div>
        </div>

        <!-- TIPS -->
        <div style="background:#161b22;border-radius:8px;padding:14px 16px;border:1px solid #21262d">
            <div style="font-size:12px;font-weight:700;color:#e6edf3;margin-bottom:10px">⌨️ Shortcut & Tips</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:11px;color:#8b949e;line-height:1.6">
                <div><kbd style="background:#0d1117;border:1px solid #30363d;border-radius:3px;padding:1px 5px;color:#e6edf3">Del</kbd> — hapus node/edge yang dipilih</div>
                <div><kbd style="background:#0d1117;border:1px solid #30363d;border-radius:3px;padding:1px 5px;color:#e6edf3">Scroll</kbd> — zoom in/out canvas</div>
                <div><kbd style="background:#0d1117;border:1px solid #30363d;border-radius:3px;padding:1px 5px;color:#e6edf3">Klik+Drag canvas</kbd> — geser tampilan</div>
                <div><kbd style="background:#0d1117;border:1px solid #30363d;border-radius:3px;padding:1px 5px;color:#e6edf3">⊞ Fit View</kbd> — tampilkan semua node</div>
                <div style="color:#ff7b72">⚠ Selalu klik Save setelah selesai mengedit</div>
                <div style="color:#ff7b72">⚠ Deploy hanya bisa dilakukan setelah Validate berhasil</div>
            </div>
        </div>

        <div style="text-align:center;margin-top:20px">
            <button onclick="hideHelp()" style="
                background:#238636;color:#fff;border:none;border-radius:8px;
                padding:10px 32px;font-size:13px;font-weight:600;cursor:pointer">
                <i class="bi bi-check-lg"></i> Mengerti, Mulai Buat Flow
            </button>
        </div>
    </div>
</div>

<script>
window.showHelp = function() {
    document.getElementById('help-modal').style.display = 'flex';
    document.getElementById('help-modal').scrollTop = 0;
};
window.hideHelp = function() {
    document.getElementById('help-modal').style.display = 'none';
    try { sessionStorage.setItem('builder_help_shown', '1'); } catch(e){}
};
// Auto-show dipindah ke bawah setelah G dideklarasikan

const CFG = {
    lk  : {{ $isLocked ? 'true' : 'false' }},
    csrf: '{{ csrf_token() }}',
    uD  : '{{ route("workflow.builder-api.data",     $version->idtblflow_version) }}',
    uS  : '{{ route("workflow.builder-api.save",     $version->idtblflow_version) }}',
    uV  : '{{ route("workflow.builder-api.validate", $version->idtblflow_version) }}',
    uDp : '{{ route("workflow.builder-api.deploy",   $version->idtblflow_version) }}',
    uCl : '{{ route("workflow.builder-api.clone",    $version->idtblflow_version) }}',
};

// ============================================================
// ReactFlow v11 — global window.ReactFlow
// ============================================================
const {
    ReactFlow, ReactFlowProvider, Background, Controls, MiniMap,
    useNodesState, useEdgesState, useReactFlow,
    addEdge, Handle, Position, MarkerType,
} = window.ReactFlow;

const h = React.createElement;
const { useState, useEffect, useCallback } = React;
const { createRoot } = ReactDOM;

// ============================================================
// CONSTANTS
// ============================================================
const NC = {
    START:    { bg:'var(--n-start-bg)', bd:'var(--n-start-bd)', tx:'var(--n-start-tx)', ic:'▶' },
    APPROVAL: { bg:'var(--n-appr-bg)',  bd:'var(--n-appr-bd)',  tx:'var(--n-appr-tx)',  ic:'✓' },
    DECISION: { bg:'var(--n-dec-bg)',   bd:'var(--n-dec-bd)',   tx:'var(--n-dec-tx)',   ic:'◆' },
    END:      { bg:'var(--n-end-bg)',   bd:'var(--n-end-bd)',   tx:'var(--n-end-tx)',   ic:'■' },
};
const ASSIGNEE_TYPES = ['USER','ROLE','GROUP','POSITION','SUPERIOR','FIELD_USER','FIELD_POSITION','JOBTITLE','API_RESOLVER'];

// ============================================================
// GLOBAL STATE
// ============================================================
let G = {
    setNodes: null, setEdges: null,
    getNodes: null, getEdges: null, rf: null,
    selNode: null, selEdge: null,
    dirty: false, botOpen: false, nc: 1,
    _nodes: [],   // snapshot terkini nodes — diupdate setiap render
    _edges: [],   // snapshot terkini edges — diupdate setiap render
};

// Auto-show panduan saat canvas kosong (pertama buka)
setTimeout(function() {
    try {
        var shown = sessionStorage.getItem('builder_help_shown');
        if (!shown && (G._nodes || []).length === 0) showHelp();
    } catch(e) {}
}, 2000);
// ============================================================
// CUSTOM NODE COMPONENT
// ============================================================
function FlowNode({ id, data, selected }) {
    const c = NC[data.step_type] || NC.APPROVAL;
    const hasErr = !!(data.validation_errors && data.validation_errors.length);
    const isD = data.step_type === 'DECISION';

    return h('div', {
        style:{
            background: c.bg,
            border: `2px solid ${hasErr?'#da3633':selected?'var(--accent)':c.bd}`,
            borderRadius: isD ? '2px' : '8px',
            transform: isD ? 'rotate(45deg)' : 'none',
            minWidth: '120px', cursor:'pointer',
            boxShadow: selected ? `0 0 0 3px var(--accent)` : 'none',
        }
    },
        h(Handle, { type:'target', position: Position.Left,
            style:{ background:c.bd, border:`2px solid ${c.tx}`, width:10, height:10, left:-6 } }),
        h('div', { style:{ transform: isD?'rotate(-45deg)':'none', padding:'8px 12px' } },
            h('div', { style:{ display:'flex', alignItems:'center', gap:6 } },
                h('span', { style:{ fontSize:15 } }, c.ic),
                h('div', {},
                    h('div', { style:{ fontSize:9, color:c.tx, fontWeight:700, textTransform:'uppercase', letterSpacing:'.04em' } }, data.step_type),
                    h('div', { style:{ fontSize:12, color:'var(--n-title)', fontWeight:600, maxWidth:100, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' } }, data.node_code||id),
                    h('div', { style:{ fontSize:10, color:'var(--n-sub)', maxWidth:100, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' } }, data.step_name),
                ),
            ),
            data.step_type==='APPROVAL' && h('div', { style:{ fontSize:10, color:c.tx, marginTop:2 } },
                (data.assignee_rules||[]).length + ' rule(s)'),
            hasErr && h('div', { style:{ fontSize:10, color:'#ff7b72', marginTop:2 } },
                '⚠ '+(data.validation_errors||[]).length+' error'),
        ),
        h(Handle, { type:'source', position: Position.Right,
            style:{ background:c.bd, border:`2px solid ${c.tx}`, width:10, height:10, right:-6 } }),
    );
}

const nodeTypes = {
    START: FlowNode, APPROVAL: FlowNode, DECISION: FlowNode, END: FlowNode,
};

// ============================================================
// MAIN APP COMPONENT
// ============================================================
function App() {
    const [nodes, setNodes, onNodesChange] = useNodesState([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState([]);
    const rfHook = useReactFlow();

    // Expose ke global
    G.setNodes = setNodes;
    G.setEdges = setEdges;
    G.getNodes = rfHook.getNodes;
    G.getEdges = rfHook.getEdges;
    G.rf = rfHook;

    // PENTING: simpan snapshot nodes/edges terkini ke G
    // karena rfHook.getNodes() dari luar React bisa stale
    G._nodes = nodes;
    G._edges = edges;

    useEffect(() => { loadData(); }, []);

    useEffect(() => {
        function onKey(e) {
            if (e.key !== 'Delete' && e.key !== 'Backspace') return;
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            delSelected();
        }
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    const onConnect = useCallback((params) => {
        const nodes2 = rfHook.getNodes();
        const src = nodes2.find(n => n.id === params.source);
        const tgt = nodes2.find(n => n.id === params.target);
        const defAct = { START:'SUBMIT', APPROVAL:'APPROVE', DECISION:'AUTO' };
        const act = defAct[src && src.type] || 'APPROVE';
        const fc = (src && src.data && src.data.node_code) || 'FROM';
        const tc = (tgt && tgt.data && tgt.data.node_code) || 'TO';
        const allEdges = rfHook.getEdges();
        const newEdge = Object.assign({}, params, {
            id: 'edge_new_' + Date.now(),
            idtblflow_transition: null,
            label: act,
            type: 'smoothstep',
            markerEnd: { type: MarkerType.ArrowClosed, color: '#484f58' },
            style: { stroke:'#484f58', strokeWidth:2 },
            data: {
                transition_code: fc+'_TO_'+tc,
                transition_name: '',
                transition_type: 'NORMAL',
                action_code: act,
                priority_no: allEdges.filter(function(e){ return e.source===params.source; }).length * 100 + 100,
                is_default: false, is_active: true,
                final_status: '', condition_json: null,
            },
        });
        setEdges(function(eds) { return addEdge(newEdge, eds); });
        markDirty();
    }, [rfHook, setEdges]);

    const onNodeClick = useCallback((_, node) => {
        G.selNode = node; G.selEdge = null; showNodePanel(node);
    }, []);

    const onEdgeClick = useCallback((_, edge) => {
        G.selEdge = edge; G.selNode = null; showEdgePanel(edge);
    }, []);

    const onPaneClick = useCallback(() => {
        G.selNode = null; G.selEdge = null; closePanel();
    }, []);

    const onNodesExt = useCallback((changes) => {
        onNodesChange(changes);
        var hasDrop = changes.some(function(c){ return c.type==='position' && c.dragging===false; });
        if (hasDrop) markDirty();
    }, [onNodesChange]);

    return h(ReactFlowProvider, {},
        h(ReactFlow, {
            nodes, edges, nodeTypes,
            onNodesChange: onNodesExt,
            onEdgesChange: function(ch){ onEdgesChange(ch); markDirty(); },
            onConnect,
            onNodeClick, onEdgeClick, onPaneClick,
            fitView: true,
            deleteKeyCode: null,
            nodesDraggable: !CFG.lk,
            nodesConnectable: !CFG.lk,
            elementsSelectable: true,
            defaultEdgeOptions: {
                type: 'smoothstep',
                markerEnd: { type: MarkerType.ArrowClosed, color:'#484f58' },
                style: { stroke:'#484f58', strokeWidth:2 },
            },
            style: { width:'100%', height:'100%', background:'var(--bg)' },
        },
            h(Background, { color:'var(--rf-dots)', gap:24, size:1 }),
            h(Controls, {}),
            h(MiniMap, {
                style:{ background:'var(--bg)', border:'1px solid var(--border)' },
                nodeColor: function(n){
                    // SVG minimap tak bisa resolve var() → pakai hex konkret per tema
                    var light = document.documentElement.getAttribute('data-theme') === 'light';
                    var m = {
                        START:    light ? '#2da44e' : '#238636',
                        APPROVAL: light ? '#8250df' : '#6e40c9',
                        DECISION: light ? '#bf8700' : '#9e6a03',
                        END:      light ? '#afb8c1' : '#30363d',
                    };
                    return m[n.type] || m.APPROVAL;
                },
                maskColor: 'var(--rf-mask)',
            }),
        )
    );
}

// ============================================================
// MOUNT — PENTING: ReactFlowProvider harus wrap App
// ============================================================
var rfRoot = createRoot(document.getElementById('rf'));
rfRoot.render(h(ReactFlowProvider, {}, h(App)));

// ============================================================
// LOAD DATA
// ============================================================
// Helper: beri warna per action_code + offset edge paralel (same source+target)
// supaya REJECT/APPROVE_END/dst tidak tumpang tindih.
function styleEdges(rfEdges) {
    var COLOR = {
        APPROVE:'#56d364', AUTO_APPROVE:'#56d364',
        REJECT:'#ff7b72', RETURN:'#d29922',
        CANCEL:'#8b949e', SUBMIT:'#79c0ff', AUTO:'#7d8590'
    };
    rfEdges.forEach(function(e){
        var ac = ((e.data && e.data.action_code) || '').toUpperCase();
        var c  = COLOR[ac] || '#484f58';
        e.style = { stroke:c, strokeWidth:2 };
        if (ac === 'REJECT') e.style.strokeDasharray = '6 4';
        e.markerEnd = { type: MarkerType.ArrowClosed, color:c };
        e.labelStyle = { fontSize: 11, fontWeight: 600, fill: c };
        e.labelBgStyle = { fill: '#0d1117', fillOpacity: 0.85 };
    });

    var groups = {};
    rfEdges.forEach(function(e){
        var k = e.source + '|' + e.target;
        (groups[k] = groups[k] || []).push(e);
    });
    Object.keys(groups).forEach(function(k){
        var arr = groups[k];
        if (arr.length <= 1) return;
        arr.forEach(function(e, i){
            var off = (i - (arr.length - 1) / 2) * 40;
            e.pathOptions = { offset: off, borderRadius: 16 };
        });
    });
}

async function loadData() {
    try {
        var res  = await fetch(CFG.uD, { headers:{ 'X-CSRF-TOKEN': CFG.csrf } });
        var data = await res.json();
        if (!data.nodes) throw new Error(data.message || 'Response tidak valid');

        var rfNodes = data.nodes.map(function(n) {
            return {
                id: n.id, type: n.type, position: n.position,
                idtblflow_step: n.idtblflow_step,
                data: Object.assign({}, n.data, {
                    node_code: n.node_code, label: n.label,
                    idtblflow_step: n.idtblflow_step,
                    validation_errors: n.validation_errors || [],
                }),
            };
        });

        var rfEdges = data.edges.map(function(e) {
            return {
                id: e.id, source: e.source, target: e.target, label: e.label,
                type: 'smoothstep',
                markerEnd: { type: MarkerType.ArrowClosed, color:'#484f58' },
                style: { stroke:'#484f58', strokeWidth:2 },
                idtblflow_transition: e.idtblflow_transition,
                data: Object.assign({}, e.data, {
                    idtblflow_transition: e.idtblflow_transition,
                    validation_errors: e.validation_errors || [],
                }),
            };
        });
        styleEdges(rfEdges);

        G.setNodes(rfNodes);
        G.setEdges(rfEdges);

        if (data.viewport && G.rf) {
            setTimeout(function(){ G.rf.setViewport(data.viewport); }, 300);
        }
        setTimeout(function(){
            document.getElementById('loading').style.display = 'none';
            setSI('Tersimpan');
        }, 400);
    } catch(err) {
        document.getElementById('loading').innerHTML =
            '<div style="color:#ff7b72;text-align:center">Gagal memuat: '+err.message+'</div>';
    }
}

// ============================================================
// LOAD DATA — simpan viewport agar tidak loncat setelah save
// ============================================================
async function loadDataKeepViewport(viewport) {
    try {
        var res  = await fetch(CFG.uD, { headers:{ 'X-CSRF-TOKEN': CFG.csrf } });
        var data = await res.json();
        if (!data.nodes) throw new Error(data.message || 'Response tidak valid');

        var rfNodes = data.nodes.map(function(n) {
            return {
                id: n.id, type: n.type, position: n.position,
                idtblflow_step: n.idtblflow_step,
                data: Object.assign({}, n.data, {
                    node_code: n.node_code, label: n.label,
                    idtblflow_step: n.idtblflow_step,
                    validation_errors: n.validation_errors || [],
                }),
            };
        });

        var rfEdges = data.edges.map(function(e) {
            return {
                id: e.id, source: e.source, target: e.target, label: e.label,
                type: 'smoothstep',
                markerEnd: { type: MarkerType.ArrowClosed, color:'#484f58' },
                style: { stroke:'#484f58', strokeWidth:2 },
                idtblflow_transition: e.idtblflow_transition,
                data: Object.assign({}, e.data, {
                    idtblflow_transition: e.idtblflow_transition,
                    validation_errors: e.validation_errors || [],
                }),
            }; 
        });
        styleEdges(rfEdges);

        G.setNodes(rfNodes);
        G.setEdges(rfEdges);

        // Restore viewport ke posisi sebelum save — bukan dari DB
        if (viewport && G.rf) {
            setTimeout(function(){
                G.rf.setViewport(viewport, { duration: 0 });
            }, 50);
        }
    } catch(err) {
        toast('Gagal reload: '+err.message, 'er');
    }
}
var _dragType = null;
window.palDrag = function(e, type) { _dragType = type; e.dataTransfer.effectAllowed = 'move'; };

window.canvasDrop = function(e) {
    e.preventDefault();
    if (!_dragType || CFG.lk) return;
    var rect = document.getElementById('cw').getBoundingClientRect();
    var x = Math.round(e.clientX - rect.left - 65);
    var y = Math.round(e.clientY - rect.top  - 25);
    var type = _dragType; _dragType = null;
    var cnt = ++G.nc;
    var code = (type==='START'||type==='END') ? type : type+'_'+cnt;
    G.setNodes(function(ns) {
        return ns.concat([{
            id: 'node_new_'+Date.now(), type: type,
            position:{ x:x, y:y }, idtblflow_step: null,
            data:{
                node_code:code, step_name:code, step_type:type,
                gateway_type: type==='DECISION'?'EXCLUSIVE':'NONE',
                approval_mode:'ANY', sla_hours:null, instruction:null,
                condition_json:null, assignee_rules:[],
                idtblflow_step:null, validation_errors:[],
            },
        }]);
    });
    markDirty();
    toast('Node '+code+' ditambahkan.','in');
};

// ============================================================
// PANEL HELPERS
// ============================================================
function openPanel(title) {
    document.getElementById('rp').classList.remove('cl');
    document.getElementById('rphtitle').innerHTML = title;
}
window.closePanel = function() { document.getElementById('rp').classList.add('cl'); };

window.togSec = function(el) {
    el.classList.toggle('col');
    var sc = el.nextElementSibling;
    if (sc) sc.classList.toggle('col');
};

// XSS-safe string escape untuk interpolasi ke innerHTML
function esc(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;');
}

function lkBan() {
    return CFG.lk
        ? '<div style="background:#3d1c02;border-radius:5px;padding:5px 8px;font-size:11px;color:#ff7b72;margin-bottom:8px"><i class="bi bi-lock-fill"></i> Read-only (locked)</div>'
        : '';
}

function selOpts(opts, val) {
    return opts.map(function(o){ return '<option'+(o===val?' selected':'')+'>'+o+'</option>'; }).join('');
}

// Isi value numerik dari data-val setelah innerHTML diset
// Diperlukan karena browser menolak string expression di value="..."
function fillDataVals(container) {
    var els = (container || document).querySelectorAll('[data-val]');
    els.forEach(function(el) {
        var v = el.getAttribute('data-val');
        if (v !== null && v !== '') {
            el.value = v;
        }
    });
}

// ============================================================
// NODE PANEL
// ============================================================
function showNodePanel(node) {
    openPanel('<i class="bi bi-circle-square"></i> Node');
    var d  = node.data || {};
    var ro = CFG.lk ? 'readonly' : '';
    var di = CFG.lk ? 'disabled' : '';
    var isA = d.step_type === 'APPROVAL';
    var isD = d.step_type === 'DECISION';

    document.getElementById('rpb').innerHTML =
    lkBan() +
    '<div class="ps">'+
        '<div class="pst" onclick="togSec(this)">Basic Info</div>'+
        '<div class="psc">'+
            '<div class="fg"><label>Node Code *</label><input id="pnc" value="'+esc(d.node_code||'')+'" '+ro+' oninput="markDirty()"></div>'+
            '<div class="fg"><label>Node Name *</label><input id="psn" value="'+esc(d.step_name||'')+'" '+ro+' oninput="markDirty()"></div>'+
            '<div class="fg"><label>Node Type</label><input value="'+esc(d.step_type)+'" readonly style="opacity:.5"></div>'+
            (isD ? '<div class="fg"><label>Gateway Type</label><select id="pgw" '+di+' onchange="markDirty()">'+selOpts(['EXCLUSIVE','INCLUSIVE','PARALLEL'],d.gateway_type)+'</select></div>' : '')+
            (isA ? '<div class="fg"><label>Approval Mode</label><select id="pam" '+di+' onchange="markDirty()">'+selOpts(['ANY','ALL','SEQUENTIAL'],d.approval_mode)+'</select></div>' : '')+
            (isA ? '<div class="fg"><label>SLA Hours</label><input id="psh" type="number" data-val="'+( d.sla_hours !== null && d.sla_hours !== undefined ? d.sla_hours : '' )+'" placeholder="kosong=tidak ada" '+ro+' oninput="markDirty()"></div>' : '')+
            '<div class="fg"><label>Instruction</label><textarea id="pins" '+ro+' oninput="markDirty()">'+esc(d.instruction||'')+'</textarea></div>'+
        '</div>'+
    '</div>'+
    '<div class="ps">'+
        '<div class="pst col" onclick="togSec(this)">Condition JSON</div>'+
        '<div class="psc col">'+
            '<div class="fg"><label>condition_json (guard opsional)</label>'+
                '<textarea id="pcj" style="font-family:monospace;min-height:70px" '+ro+' oninput="markDirty()"></textarea>'+
            '</div>'+
            '<div style="display:flex;gap:5px;margin-top:3px">'+
                '<button class="badd" onclick="bfJ(\'pcj\')">Beautify</button>'+
                '<button class="badd" onclick="vlJ(\'pcj\')">Validate</button>'+
            '</div>'+
            '<div id="pch"></div>'+
            '<button class="badd" onclick="condH(\'pcj\',\'pch\')">+ Add Condition</button>'+
        '</div>'+
    '</div>'+
    (isA ?
    '<div class="ps">'+
        '<div class="pst" onclick="togSec(this)">Assignee Rules</div>'+
        '<div class="psc">'+
            '<div id="arl">'+renderRules(d.assignee_rules||[])+'</div>'+
            (!CFG.lk ? '<button class="badd" onclick="addRule()">+ Tambah Assignee Rule</button>' : '')+
        '</div>'+
    '</div>' : '')+
    (!CFG.lk ?
    '<div class="arow">'+
        '<button class="bap" onclick="applyNode()"><i class="bi bi-check-lg"></i> Apply</button>'+
        '<button class="bde" onclick="askDel(\'node\')"><i class="bi bi-trash"></i> Delete</button>'+
    '</div>' : '')+
    ((d.validation_errors||[]).length ?
        '<div style="margin-top:8px">'+(d.validation_errors||[]).map(function(e){ return '<div class="ve">⚠ '+esc(e)+'</div>'; }).join('')+'</div>'
        : '');
    // Isi nilai numerik (data-val → value) setelah innerHTML diset
    fillDataVals(document.getElementById('rpb'));
    // Set condition_json via textContent (bukan innerHTML) untuk cegah XSS (#21)
    var pcjEl = document.getElementById('pcj');
    if (pcjEl) pcjEl.textContent = d.condition_json ? JSON.stringify(d.condition_json,null,2) : '';
    autoResolveJTLabels();
}

// ============================================================
// EDGE PANEL
// ============================================================
function showEdgePanel(edge) {
    openPanel('<i class="bi bi-arrow-right-circle"></i> Edge');
    var d  = edge.data || {};
    var ro = CFG.lk ? 'readonly' : '';
    var di = CFG.lk ? 'disabled' : '';

    document.getElementById('rpb').innerHTML =
    lkBan() +
    '<div class="ps">'+
        '<div class="pst" onclick="togSec(this)">Basic Info</div>'+
        '<div class="psc">'+
            '<div class="fg"><label>action_code *</label>'+
                '<input id="eac" value="'+esc(d.action_code||'')+'" placeholder="SUBMIT/APPROVE/REJECT/AUTO" '+ro+' oninput="markDirty()">'+
                '<small>SUBMIT · APPROVE · REJECT · RETURN · AUTO</small></div>'+
            '<div class="fg"><label>transition_code</label><input id="etc" value="'+esc(d.transition_code||'')+'" '+ro+' oninput="markDirty()"></div>'+
            '<div class="fg"><label>transition_name</label><input id="etn" value="'+esc(d.transition_name||'')+'" '+ro+' oninput="markDirty()"></div>'+
            '<div class="fg"><label>priority_no</label>'+
                '<input id="epn" type="number" data-val="'+(d.priority_no !== null && d.priority_no !== undefined ? d.priority_no : 100)+'" '+ro+' oninput="markDirty()">'+
                '<small>Lebih kecil = dievaluasi duluan</small></div>'+
            '<div class="fg"><label>final_status (opsional)</label>'+
                '<input id="efs" value="'+esc(d.final_status||'')+'" placeholder="APPROVED/REJECTED" '+ro+' oninput="markDirty()"></div>'+
            '<div class="fg">'+
                '<div class="cbr"><input type="checkbox" id="edf" '+(d.is_default?'checked':'')+' '+di+' onchange="markDirty()"><label for="edf">Default transition</label></div>'+
                '<div class="cbr"><input type="checkbox" id="eac2" '+(d.is_active!==false?'checked':'')+' '+di+' onchange="markDirty()"><label for="eac2">Aktif</label></div>'+
            '</div>'+
        '</div>'+
    '</div>'+
    '<div class="ps">'+
        '<div class="pst col" onclick="togSec(this)">Condition JSON</div>'+
        '<div class="psc col">'+
            '<div class="fg"><label>condition_json (kosong=always true)</label>'+
                '<textarea id="ecj" style="font-family:monospace;min-height:80px" '+ro+' oninput="markDirty()"></textarea>'+
                '<small>{"op":"&gt;","field":"nilai","value":10000000}</small></div>'+
            '<div style="display:flex;gap:5px;margin-top:3px">'+
                '<button class="badd" onclick="bfJ(\'ecj\')">Beautify</button>'+
                '<button class="badd" onclick="vlJ(\'ecj\')">Validate</button>'+
            '</div>'+
            '<div id="ech"></div>'+
            '<button class="badd" onclick="condH(\'ecj\',\'ech\')">+ Add Condition</button>'+
        '</div>'+
    '</div>'+
    (!CFG.lk ?
    '<div class="arow">'+
        '<button class="bap" onclick="applyEdge()"><i class="bi bi-check-lg"></i> Apply</button>'+
        '<button class="bde" onclick="askDel(\'edge\')"><i class="bi bi-trash"></i> Delete</button>'+
    '</div>' : '');
    // Isi nilai numerik (data-val → value) setelah innerHTML diset
    fillDataVals(document.getElementById('rpb'));
    // Set condition_json via textContent (#21)
    var ecjEl = document.getElementById('ecj');
    if (ecjEl) ecjEl.textContent = d.condition_json ? JSON.stringify(d.condition_json,null,2) : '';
}

// ============================================================
// APPLY CHANGES
// ============================================================
window.applyNode = function() {
    if (!G.selNode) return;
    var id = G.selNode.id;
    var rules = collectRules();

    console.log('[applyNode] id='+id+' rules='+JSON.stringify(rules));

    var newData = {
        node_code:      (g('pnc') && g('pnc').value.trim().toUpperCase()) || G.selNode.data.node_code,
        step_name:      (g('psn') && g('psn').value.trim()) || G.selNode.data.step_name,
        gateway_type:   (g('pgw') && g('pgw').value)        || G.selNode.data.gateway_type,
        approval_mode:  (g('pam') && g('pam').value)        || G.selNode.data.approval_mode,
        sla_hours:      parseInt(g('psh') && g('psh').value) || null,
        instruction:    (g('pins') && g('pins').value)       || null,
        condition_json: parseJ('pcj'),
        assignee_rules: rules,
    };

    // 1. Update React state
    G.setNodes(function(ns) {
        return ns.map(function(n) {
            if (n.id !== id) return n;
            return Object.assign({}, n, { data: Object.assign({}, n.data, newData) });
        });
    });

    // 2. Update G.selNode SEKARANG (sync) agar doSave bisa pakai data terbaru
    G.selNode = Object.assign({}, G.selNode, {
        data: Object.assign({}, G.selNode.data, newData)
    });

    // 3. Update G._nodes sekarang juga — jangan tunggu React render
    if (G._nodes) {
        G._nodes = G._nodes.map(function(n) {
            if (n.id !== id) return n;
            return Object.assign({}, n, { data: Object.assign({}, n.data, newData) });
        });
    }

    markDirty();
    toast('Node diperbarui — klik \uD83D\uDCAE Save untuk menyimpan.', 'ok');
};

window.applyEdge = function() {
    if (!G.selEdge) return;
    var id = G.selEdge.id;
    var ac = (g('eac') && g('eac').value.trim().toUpperCase()) || G.selEdge.data.action_code;
    G.setEdges(function(es) {
        return es.map(function(e) {
            if (e.id !== id) return e;
            return Object.assign({}, e, { label: ac, data: Object.assign({}, e.data, {
                action_code:     ac,
                transition_code: (g('etc') && g('etc').value.trim()) || e.data.transition_code,
                transition_name: (g('etn') && g('etn').value.trim()) || '',
                priority_no:     parseInt(g('epn') && g('epn').value) || 100,
                final_status:    (g('efs') && g('efs').value.trim()) || null,
                is_default:      g('edf')  ? g('edf').checked  : false,
                is_active:       g('eac2') ? g('eac2').checked : true,
                condition_json:  parseJ('ecj'),
            }) });
        });
    });
    markDirty(); toast('Edge diperbarui.','ok');
};

// ============================================================
// ASSIGNEE RULES
// ============================================================
const ASSIGNEE_HINTS = {
    'USER':           'user_ref / NPK. Contoh: 11110247',
    'ROLE':           'role_code. Contoh: APPROVER',
    'GROUP':          'group_code dari Master → Approval Group',
    'POSITION':       'position_code dari Master → Position',
    'SUPERIOR':       '(kosong) — otomatis ke atasan submitter',
    'FIELD_USER':     'nama field di context_json. Contoh: _computed.bmh_user_ref',
    'FIELD_POSITION': 'nama field di context_json.',
    'JOBTITLE':       'cari nama jabatan di bawah',
    'API_RESOLVER':   'URL endpoint resolver',
};
function renderRules(rules) {
    if (!rules.length) return '<div class="vmt">Belum ada rule.</div>';
    return rules.map(function(r){ return ruleHtml(r); }).join('');
}
function ruleHtml(r) {
    var type = r.assignee_type || 'USER';
    var isJT = type === 'JOBTITLE';
    var isSup = type === 'SUPERIOR';
    var hint = ASSIGNEE_HINTS[type] || '';
    var typeOpts = ASSIGNEE_TYPES.map(function(t){
        return '<option value="'+t+'"'+(type===t?' selected':'')+'>'+t+'</option>';
    }).join('');
    var valueHtml = '';
    if (isJT) {
        var savedId = esc(r.assignee_value||'');
        var savedLabel = esc(r.assignee_value_label || r.assignee_value || '');
        valueHtml =
            '<div class="fg" style="position:relative">'+
                '<label>Jabatan <span style="color:#484f58;font-size:10px">(ketik untuk cari, simpan ID)</span></label>'+
                '<input class="rv-jt-search" type="text" autocomplete="off"'+
                    ' placeholder="Ketik nama jabatan..." value="'+savedLabel+'"'+
                    ' data-saved-id="'+savedId+'"'+
                    ' oninput="onJTSearch(this)" style="margin-bottom:2px">'+
                '<input class="rv" type="hidden" value="'+savedId+'">'+
                '<div class="rv-jt-results" style="display:none;position:absolute;z-index:999;'+
                    'background:#161b22;border:1px solid #30363d;border-radius:5px;'+
                    'max-height:180px;overflow-y:auto;width:100%;font-size:11px;'+
                    'box-shadow:0 4px 12px rgba(0,0,0,.5);top:100%;left:0"></div>'+
                '<div class="rv-jt-disp" style="font-size:10px;color:#3fb950;margin-top:2px">'+
                    (savedId ? '\u2713 ID tersimpan: '+savedId : '')+
                '</div>'+
            '</div>';
    } else if (isSup) {
        valueHtml = '<div class="fg"><label>Value</label>'+
            '<input class="rv" value="" placeholder="(otomatis)" readonly style="opacity:.4"></div>';
    } else {
        valueHtml = '<div class="fg"><label>Value</label>'+
            '<input class="rv" value="'+esc(r.assignee_value||'')+'"'+
            ' placeholder="'+esc(hint)+'" oninput="markDirty()">'+
            (hint ? '<div style="font-size:10px;color:#484f58;margin-top:2px">'+esc(hint)+'</div>' : '')+
            '</div>';
    }
    return '<div class="ri" data-rid="'+(r.idtblstep_assignee_rule||'')+'" style="position:relative">'+
        '<button class="rrm" onclick="rmRule(this)">x</button>'+
        '<div class="fg"><label>Type</label>'+
            '<select class="rt" onchange="onRuleTypeChange(this)">'+typeOpts+'</select>'+
        '</div>'+
        valueHtml+
        '<div class="fg"><label>Priority</label>'+
            '<input class="rp" type="number" data-val="'+(r.priority_no!=null?r.priority_no:1)+'" oninput="markDirty()">'+
        '</div>'+
        '<div class="cbr">'+
            '<input type="checkbox" class="rreq" '+(r.is_required!==false?'checked':'')+' onchange="markDirty()"><label>Required</label>'+
            '<input type="checkbox" class="ract" '+(r.is_active!==false?'checked':'')+' style="margin-left:8px" onchange="markDirty()"><label>Aktif</label>'+
        '</div>'+
    '</div>';
}
window.onRuleTypeChange = function(sel) {
    var ri = sel.closest('.ri');
    var type = sel.value;
    var ruleData = {
        idtblstep_assignee_rule: ri.dataset.rid ? parseInt(ri.dataset.rid) : null,
        assignee_type: type, assignee_value: '',
        priority_no: parseInt((ri.querySelector('.rp')||{}).value)||1,
        is_required: !!(ri.querySelector('.rreq')||{}).checked,
        is_active:   !!(ri.querySelector('.ract')||{}).checked,
    };
    var tmp = document.createElement('div');
    tmp.innerHTML = ruleHtml(ruleData);
    ri.parentNode.replaceChild(tmp.firstElementChild, ri);
    fillDataVals(document.getElementById('arl'));
    markDirty();
};
var _jtTimer = null;
window.onJTSearch = function(inp) {
    clearTimeout(_jtTimer);
    var q = inp.value.trim();
    var ri = inp.closest('.ri');
    var resDiv = ri.querySelector('.rv-jt-results');
    var hidInp = ri.querySelector('.rv');
    var disp   = ri.querySelector('.rv-jt-disp');
    hidInp.value = ''; if(disp) disp.textContent = ''; markDirty();
    if (!q) { resDiv.style.display='none'; return; }
    _jtTimer = setTimeout(function() {
        fetch('{{ route("workflow.builder-api.jobtitle-search") }}?q='+encodeURIComponent(q),
            {headers:{'X-CSRF-TOKEN':CFG.csrf}})
        .then(function(r){return r.json();})
        .then(function(data){
            if (!data.success||!data.data.length) {
                resDiv.innerHTML='<div style="padding:8px 10px;color:#6e7681">Tidak ditemukan</div>';
            } else {
                resDiv.innerHTML = data.data.map(function(jt){
                    return '<div class="jt-opt" data-id="'+esc(jt.id)+'" data-name="'+esc(jt.name)+'"'+
                        ' style="padding:7px 10px;cursor:pointer;border-bottom:1px solid #21262d"'+
                        ' onmouseover="this.style.background=\'#21262d\'"'+
                        ' onmouseout="this.style.background=\'\'"'+
                        ' onclick="selectJT(this)">'+
                        '<span style="color:#e6edf3">'+esc(jt.name)+'</span>'+
                        ' <span style="color:#484f58;font-size:10px">'+esc(jt.id)+' \u2022 '+jt.count+' org</span>'+
                    '</div>';
                }).join('');
            }
            resDiv.style.display='';
        }).catch(function(){resDiv.style.display='none';});
    }, 350);
};
window.selectJT = function(opt) {
    var ri = opt.closest('.ri');
    var id=opt.dataset.id, name=opt.dataset.name;
    ri.querySelector('.rv-jt-search').value = name;
    ri.querySelector('.rv').value = id;
    ri.querySelector('.rv-jt-results').style.display='none';
    var d=ri.querySelector('.rv-jt-disp'); if(d) d.innerHTML='<span style="color:#3fb950">\u2713 ID tersimpan: '+esc(id)+'</span>';
    markDirty();
};

// Auto-resolve label nama jabatan dari jobtitleid saat panel dibuka
// Dipanggil setelah showNodePanel render selesai
function autoResolveJTLabels() {
    var searches = document.querySelectorAll('.rv-jt-search[data-saved-id]');
    searches.forEach(function(inp) {
        var savedId = inp.getAttribute('data-saved-id');
        if (!savedId) return;
        // Jika label sudah ada (sama dengan ID) atau sudah nama panjang, fetch
        var curVal = inp.value.trim();
        // Jika value sudah berupa nama (bukan kode JT...) skip
        if (curVal && !curVal.match(/^JT\d+$/i)) return;
        // Fetch nama dari server
        fetch('{{ route("workflow.builder-api.jobtitle-search") }}?q='+encodeURIComponent(savedId),
            {headers:{'X-CSRF-TOKEN':CFG.csrf}})
        .then(function(r){return r.json();})
        .then(function(data){
            if (data.success && data.data && data.data.length) {
                var found = data.data.find(function(jt){ return jt.id === savedId; });
                if (found) {
                    inp.value = found.name;
                    var disp = inp.closest('.ri').querySelector('.rv-jt-disp');
                    if (disp) disp.innerHTML = '<span style="color:#3fb950">\u2713 ID tersimpan: '+esc(savedId)+'</span>';
                }
            }
        }).catch(function(){});
    });
}
document.addEventListener('click', function(e){
    if(!e.target.closest('.rv-jt-search')&&!e.target.closest('.rv-jt-results')){
        document.querySelectorAll('.rv-jt-results').forEach(function(el){el.style.display='none';});
    }
});
window.addRule = function() {
    var list = document.getElementById('arl'); if (!list) return;
    var cnt = list.querySelectorAll('.ri').length;
    var el = document.createElement('div');
    el.innerHTML = ruleHtml({ assignee_type:'USER', assignee_value:'', priority_no:cnt+1, is_required:true, is_active:true });
    list.appendChild(el.firstElementChild);
    fillDataVals(list);
    markDirty();
};
window.rmRule = function(btn) { btn.closest('.ri').remove(); markDirty(); };
function collectRules() {
    return Array.from(document.querySelectorAll('#arl .ri')).map(function(el) {
        return {
            idtblstep_assignee_rule: el.dataset.rid ? parseInt(el.dataset.rid) : null,
            assignee_type:  (el.querySelector('.rt')&&el.querySelector('.rt').value)||'USER',
            assignee_value: (el.querySelector('.rv')&&el.querySelector('.rv').value)||'',
            priority_no:    parseInt((el.querySelector('.rp')||{}).value)||1,
            is_required:    !!(el.querySelector('.rreq')||{}).checked,
            is_active:      !!(el.querySelector('.ract')||{}).checked,
        };
    });
}

// ============================================================
// CONDITION HELPER
// ============================================================
window.condH = function(tid, hid) {
    var el = document.getElementById(hid); if (!el) return;
    el.innerHTML =
    '<div style="background:#0d1117;border:1px solid #21262d;border-radius:5px;padding:8px;margin-top:4px">'+
    '<div style="font-size:10px;color:#6e7681;margin-bottom:4px">Quick add:</div>'+
    '<input id="chf'+tid+'" placeholder="field name" style="width:100%;padding:4px 6px;font-size:11px;background:#0d1117;border:1px solid #21262d;border-radius:4px;color:#e6edf3;margin-bottom:4px">'+
    '<select id="cho'+tid+'" style="width:100%;padding:4px;font-size:11px;background:#0d1117;border:1px solid #21262d;border-radius:4px;color:#e6edf3;margin-bottom:4px">'+
    ['=','!=','>','>=','<','<=','IN','NOT_IN','BETWEEN','CONTAINS'].map(function(op){ return '<option>'+op+'</option>'; }).join('')+
    '</select>'+
    '<input id="chv'+tid+'" placeholder="value (IN: a,b,c | BETWEEN: 1,2)" style="width:100%;padding:4px 6px;font-size:11px;background:#0d1117;border:1px solid #21262d;border-radius:4px;color:#e6edf3;margin-bottom:6px">'+
    '<button class="badd" onclick="apCondH(\''+tid+'\')">Insert JSON</button>'+
    '</div>';
};
window.apCondH = function(tid) {
    var f = document.getElementById('chf'+tid); if (!f) return;
    var o = document.getElementById('cho'+tid);
    var v = document.getElementById('chv'+tid);
    if (!f.value.trim()||!v.value.trim()) { toast('Field dan value wajib.','er'); return; }
    var op = o ? o.value : '=';
    var fv = f.value.trim(), vv = v.value.trim();
    var val = isNaN(vv) ? vv : Number(vv);
    if (op==='IN'||op==='NOT_IN') val = vv.split(',').map(function(s){ return s.trim(); });
    if (op==='BETWEEN') val = vv.split(',').map(Number);
    var ta = document.getElementById(tid);
    if (ta) { ta.value = JSON.stringify({op:op,field:fv,value:val},null,2); markDirty(); }
};
window.bfJ = function(id) {
    var el = document.getElementById(id); if (!el||!el.value.trim()) return;
    try { el.value = JSON.stringify(JSON.parse(el.value),null,2); }
    catch(e) { toast('JSON tidak valid','er'); }
};
window.vlJ = function(id) {
    var el = document.getElementById(id);
    if (!el||!el.value.trim()) { toast('JSON kosong = always-true','in'); return; }
    try { JSON.parse(el.value); toast('JSON valid ✓','ok'); }
    catch(e) { toast('JSON tidak valid: '+e.message,'er'); }
};

// ============================================================
// DELETE
// ============================================================
var _delTgt = null;
window.askDel = function(type) {
    _delTgt = type;
    var nm = type==='node'
        ? (G.selNode && G.selNode.data && G.selNode.data.node_code)
        : (G.selEdge && G.selEdge.data && G.selEdge.data.action_code);
    showModal(
        type==='node' ? 'Hapus Node?' : 'Hapus Edge?',
        type==='node'
            ? 'Node "'+nm+'" akan dihapus. Pastikan edge yang terhubung sudah dihapus terlebih dahulu.'
            : 'Edge "'+nm+'" akan dihapus.'
    );
};
document.getElementById('myes').onclick = function() {
    closeModal();
    if (_delTgt==='node' && G.selNode) {
        G.setNodes(function(ns){ return ns.filter(function(n){ return n.id!==G.selNode.id; }); });
        closePanel(); G.selNode=null; markDirty(); toast('Node dihapus.','in');
    } else if (_delTgt==='edge' && G.selEdge) {
        G.setEdges(function(es){ return es.filter(function(e){ return e.id!==G.selEdge.id; }); });
        closePanel(); G.selEdge=null; markDirty(); toast('Edge dihapus.','in');
    }
};
document.getElementById('mno').onclick = closeModal;
function delSelected() {
    if (G.selNode) window.askDel('node');
    else if (G.selEdge) window.askDel('edge');
}

// ============================================================
// SAVE
// ============================================================
window.doSave = async function() {
    if (CFG.lk) { toast('Canvas locked.','er'); return; }
    setSI('Menyimpan...');

    // G._nodes diupdate oleh applyNode() secara sinkron DAN tiap React render
    var nodes = (G._nodes && G._nodes.length) ? G._nodes : (G.getNodes ? G.getNodes() : []);
    var edges = (G._edges && G._edges.length) ? G._edges : (G.getEdges ? G.getEdges() : []);

    console.log('[doSave] nodes='+nodes.length+' edges='+edges.length);
    nodes.forEach(function(n){
        if (n.data && n.data.assignee_rules && n.data.assignee_rules.length) {
            console.log('  node:', n.data.node_code, 'rules:', JSON.stringify(n.data.assignee_rules));
        }
    });

    // Simpan viewport saat ini agar bisa di-restore setelah reload
    var currentViewport = G.rf ? G.rf.getViewport() : { x:0, y:0, zoom:1 };

    var payload = {
        nodes: nodes.map(function(n,i){
            return {
                id: n.id,
                idtblflow_step: n.idtblflow_step || (n.data && n.data.idtblflow_step) || null,
                node_code: n.data && n.data.node_code,
                position: {
                    x: (n.position && typeof n.position.x === 'number') ? Math.round(n.position.x) : 100,
                    y: (n.position && typeof n.position.y === 'number') ? Math.round(n.position.y) : 100,
                },
                data: Object.assign({}, n.data, { step_order:(i+1)*10 }),
            };
        }),
        edges: edges.map(function(e){
            return {
                id: e.id,
                idtblflow_transition: e.idtblflow_transition || (e.data && e.data.idtblflow_transition) || null,
                source: e.source, target: e.target,
                data: e.data || {},
            };
        }),
        deleted_node_ids: [], deleted_edge_ids: [],
        diagram_json: { viewport: currentViewport },
    };

    try {
        var res  = await post(CFG.uS, payload);
        var data = await res.json();
        if (data.success) {
            G.dirty = false; setSI('Tersimpan '+new Date().toLocaleTimeString('id'));
            toast('Berhasil disimpan.','ok');
            await loadDataKeepViewport(currentViewport);
        } else { toast('Gagal: '+(data.message||'error'),'er'); setSI('Gagal'); }
    } catch(e) { toast('Error: '+e.message,'er'); setSI('Error'); }
};

// ============================================================
// VALIDATE
// ============================================================
window.doValidate = async function() {
    setSI('Validating...');
    try {
        var res  = await post(CFG.uV, {});
        var data = await res.json();
        var vo = document.getElementById('vo');
        var vs = document.getElementById('vsum');
        if (data.is_valid) {
            vs.textContent = '✓ VALID'; vs.style.color = '#3fb950';
            vo.innerHTML = '<div class="vok">✓ VALID — '+(data.checks&&data.checks.length||0)+' checks lulus.</div>'+
                (data.warnings||[]).map(function(w){ return '<div class="vw">⚠ '+esc(w)+'</div>'; }).join('');
            toast('VALID!','ok');
        } else {
            vs.textContent = '✗ '+(data.errors&&data.errors.length||0)+' error'; vs.style.color='#ff7b72';
            vo.innerHTML = (data.errors||[]).map(function(e){ return '<div class="ve">✗ '+esc(e)+'</div>'; }).join('')+
                (data.warnings||[]).map(function(w){ return '<div class="vw">⚠ '+esc(w)+'</div>'; }).join('');
            toast('INVALID: '+(data.errors&&data.errors.length)+' error.','er');
        }
        if (data.error_node_codes && data.error_node_codes.length) {
            G.setNodes(function(ns) {
                return ns.map(function(n) {
                    return Object.assign({}, n, { data: Object.assign({}, n.data, {
                        validation_errors: data.error_node_codes.indexOf(n.data&&n.data.node_code)>=0
                            ? (data.errors||[]).filter(function(e){ return e.indexOf(n.data.node_code)>=0; })
                            : [],
                    }) });
                });
            });
        }
        setSI(data.is_valid ? '✓ Valid' : '✗ Invalid');
        if (!G.botOpen) { G.botOpen=true; document.getElementById('vo').style.display=''; document.getElementById('bico').textContent='▲'; }
    } catch(e) { toast('Error validasi: '+e.message,'er'); }
};

// ============================================================
// DEPLOY
// ============================================================
window.doDeploy = async function() {
    if (CFG.lk) { toast('Locked.','er'); return; }
    var ok = await confirm2('Deploy Flow?','Flow menjadi ACTIVE dan tidak bisa diedit langsung setelah itu.');
    if (!ok) return;
    try {
        var res  = await post(CFG.uDp, {});
        var data = await res.json();
        if (data.success) { toast(data.message,'ok'); setTimeout(function(){ location.reload(); },1500); }
        else toast('Gagal: '+data.message,'er');
    } catch(e) { toast('Error: '+e.message,'er'); }
};

// ============================================================
// CLONE
// ============================================================
window.doClone = async function() {
    var ok = await confirm2('Clone Version?','Akan dibuat version baru (DRAFT). Lanjutkan?');
    if (!ok) return;
    try {
        var res  = await post(CFG.uCl, {});
        var data = await res.json();
        if (data.success) { toast(data.message,'ok'); setTimeout(function(){ window.location.href=data.builder_url; },1500); }
        else toast('Gagal: '+data.message,'er');
    } catch(e) { toast('Error: '+e.message,'er'); }
};

// ============================================================
// CANVAS CONTROLS
// ============================================================
window.rfCtrl = function(a) {
    if (!G.rf) return;
    if (a==='fit') G.rf.fitView({ padding:.1 });
    else if (a==='in') G.rf.zoomIn();
    else if (a==='out') G.rf.zoomOut();
};
window.goBack = function() {
    if (G.dirty && !confirm('Ada perubahan belum disimpan. Keluar?')) return;
    history.back();
};

// ============================================================
// MISC HELPERS
// ============================================================
window.togBot = function() {
    G.botOpen = !G.botOpen;
    document.getElementById('vo').style.display   = G.botOpen ? '' : 'none';
    document.getElementById('bico').textContent   = G.botOpen ? '▲' : '▼';
};
function markDirty() { G.dirty=true; setSI('Belum disimpan*'); }
function setSI(t) { var e=document.getElementById('si'); if(e) e.textContent=t; }
function g(id) { return document.getElementById(id); }
function numVal(v, def) {
    // Helper: pastikan nilai numerik valid untuk input[type=number]
    var n = parseFloat(v);
    return isNaN(n) ? (def !== undefined ? def : '') : n;
}
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function parseJ(id) {
    var el=document.getElementById(id);
    if (!el||!el.value.trim()) return null;
    try { return JSON.parse(el.value); } catch(e) { return null; }
}
function toast(msg, type) {
    type = type||'in';
    var el=document.createElement('div');
    el.className='toast t'+type;
    el.textContent=msg;
    document.getElementById('tw').appendChild(el);
    setTimeout(function(){ el.remove(); }, 4000);
}
async function post(url, body) {
    return fetch(url, {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CFG.csrf, 'Accept':'application/json' },
        body: JSON.stringify(body),
    });
}
function showModal(title, msg) {
    document.getElementById('mtitle').textContent = title;
    document.getElementById('mmsg').textContent   = msg;
    document.getElementById('mdl').classList.add('show');
}
function closeModal() { document.getElementById('mdl').classList.remove('show'); }
var _mRes = null;
function confirm2(title, msg) {
    return new Promise(function(resolve) {
        showModal(title, msg);
        document.getElementById('myes').onclick = function() { closeModal(); resolve(true); };
        document.getElementById('mno').onclick  = function() { closeModal(); resolve(false); };
    });
}
window.addEventListener('beforeunload', function(e) {
    if (G.dirty) { e.preventDefault(); e.returnValue=''; }
});
</script>
</body>
</html>
