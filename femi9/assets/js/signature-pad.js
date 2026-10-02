// Reusable "Draw" / "Type" signature widget for the CP/TP Agreement pages.
// Draw mode: freehand on a <canvas> (mouse + touch). Type mode: a text
// input rendered live in a cursive font onto a second canvas. Either way,
// the result is a PNG data URL written into a hidden <input>, so the form
// posts one plain string regardless of which mode was used.
function initSignaturePad(containerId, hiddenInputId) {
    var container = document.getElementById(containerId);
    if (!container) return;
    var hiddenInput = document.getElementById(hiddenInputId);

    var wrap = document.createElement('div');
    wrap.innerHTML =
        '<div class="sig-editor">' +
        '<div class="sig-tabs d-flex flex-wrap gap-2 mb-2">' +
            '<button type="button" class="sig-tab-btn sig-tab-draw active btn btn-dark btn-sm">Draw</button>' +
            '<button type="button" class="sig-tab-btn sig-tab-type btn btn-outline-secondary btn-sm">Type</button>' +
            '<button type="button" class="sig-clear-btn btn btn-outline-danger btn-sm ms-auto">Clear</button>' +
            '<button type="button" class="sig-save-btn btn btn-success btn-sm">Save Signature</button>' +
        '</div>' +
        '<div class="sig-draw-pane"><canvas class="sig-canvas" width="360" height="120" style="border:1px solid #d1d5db;border-radius:8px;background:#fff;touch-action:none;width:100%;max-width:360px;height:120px;cursor:crosshair;"></canvas></div>' +
        '<div class="sig-type-pane" style="display:none;">' +
            '<input type="text" class="sig-type-input form-control" placeholder="Type your name" style="margin-bottom:6px;">' +
            '<canvas class="sig-type-canvas" width="360" height="120" style="border:1px solid #d1d5db;border-radius:8px;background:#fff;width:100%;max-width:360px;height:120px;"></canvas>' +
        '</div>' +
        '</div>' +
        '<div class="sig-preview-pane d-none align-items-center gap-2">' +
            '<img class="sig-preview-img" style="border-bottom:1px solid #9ca3af;background:transparent;max-width:200px;max-height:44px;object-fit:contain;">' +
            '<button type="button" class="sig-edit-btn btn btn-outline-secondary btn-sm">Edit</button>' +
        '</div>';
    container.appendChild(wrap);

    var editorPane = wrap.querySelector('.sig-editor');
    var previewPane = wrap.querySelector('.sig-preview-pane');
    var previewImg = wrap.querySelector('.sig-preview-img');
    var saveBtn = wrap.querySelector('.sig-save-btn');
    var editBtn = wrap.querySelector('.sig-edit-btn');
    var drawPane = wrap.querySelector('.sig-draw-pane');
    var typePane = wrap.querySelector('.sig-type-pane');
    var drawTabBtn = wrap.querySelector('.sig-tab-draw');
    var typeTabBtn = wrap.querySelector('.sig-tab-type');
    var clearBtn = wrap.querySelector('.sig-clear-btn');
    var canvas = wrap.querySelector('.sig-canvas');
    var ctx = canvas.getContext('2d');
    var typeInput = wrap.querySelector('.sig-type-input');
    var typeCanvas = wrap.querySelector('.sig-type-canvas');
    var typeCtx = typeCanvas.getContext('2d');
    var activeMode = 'draw';
    var hasDrawing = false;

    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1f2937';

    function canvasPoint(e) {
        var rect = canvas.getBoundingClientRect();
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        var clientX = e.touches ? e.touches[0].clientX : e.clientX;
        var clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
    }

    var drawing = false;
    function startDraw(e) { drawing = true; hasDrawing = true; var p = canvasPoint(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
    function moveDraw(e) { if (!drawing) return; var p = canvasPoint(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
    function endDraw() { drawing = false; syncHiddenInput(); }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', moveDraw);
    window.addEventListener('mouseup', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', moveDraw, { passive: false });
    canvas.addEventListener('touchend', endDraw);

    function renderTypedSignature() {
        typeCtx.clearRect(0, 0, typeCanvas.width, typeCanvas.height);
        var text = typeInput.value || '';
        typeCtx.fillStyle = '#1f2937';
        typeCtx.font = "40px 'Brush Script MT', cursive";
        typeCtx.textBaseline = 'middle';
        typeCtx.fillText(text, 12, typeCanvas.height / 2);
        syncHiddenInput();
    }
    typeInput.addEventListener('input', renderTypedSignature);

    function syncHiddenInput() {
        if (activeMode === 'draw') {
            hiddenInput.value = hasDrawing ? canvas.toDataURL('image/png') : '';
        } else {
            hiddenInput.value = (typeInput.value || '').trim() ? typeCanvas.toDataURL('image/png') : '';
        }
    }

    drawTabBtn.addEventListener('click', function () {
        activeMode = 'draw';
        drawTabBtn.classList.add('active', 'btn-dark'); drawTabBtn.classList.remove('btn-outline-secondary');
        typeTabBtn.classList.remove('active', 'btn-dark'); typeTabBtn.classList.add('btn-outline-secondary');
        drawPane.style.display = ''; typePane.style.display = 'none';
        syncHiddenInput();
    });
    typeTabBtn.addEventListener('click', function () {
        activeMode = 'type';
        typeTabBtn.classList.add('active', 'btn-dark'); typeTabBtn.classList.remove('btn-outline-secondary');
        drawTabBtn.classList.remove('active', 'btn-dark'); drawTabBtn.classList.add('btn-outline-secondary');
        typePane.style.display = ''; drawPane.style.display = 'none';
        syncHiddenInput();
    });
    clearBtn.addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawing = false;
        typeInput.value = '';
        typeCtx.clearRect(0, 0, typeCanvas.width, typeCanvas.height);
        hiddenInput.value = '';
    });

    // "Save Signature" locks in whatever's currently drawn/typed and shows
    // it back as a plain preview image — confirms exactly what will be
    // submitted, instead of just trusting the live canvas. "Edit Signature"
    // returns to the Draw/Type editor without losing what was already
    // captured (the canvas/typed text are untouched underneath).
    saveBtn.addEventListener('click', function () {
        syncHiddenInput();
        if (!hiddenInput.value) {
            alert('Please draw or type a signature first.');
            return;
        }
        previewImg.src = hiddenInput.value;
        editorPane.style.display = 'none';
        previewPane.classList.remove('d-none');
        previewPane.classList.add('d-flex');
    });
    editBtn.addEventListener('click', function () {
        previewPane.classList.add('d-none');
        previewPane.classList.remove('d-flex');
        editorPane.style.display = '';
    });
}
