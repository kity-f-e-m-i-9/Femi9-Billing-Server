// Shared "Share to WhatsApp" for invoice print pages (TP + company).
// Converts the print page's invoice div into a PDF (html2pdf.js — CDN,
// loaded by each page that uses this) and hands it to the device's native
// share sheet via the Web Share API (navigator.share with files), which is
// the only way a browser can hand a file directly to the WhatsApp app —
// there is no wa.me parameter for attaching a file. Where that API/File
// support isn't available (most desktop browsers), it falls back to
// downloading the PDF and opening a WhatsApp chat with a text prompt asking
// the customer's shop to attach the file that was just downloaded.
//
// navigator.share() is deliberately restricted to actual mobile devices
// (see isMobileDevice() below), even on desktop browsers that technically
// support it (notably Safari/Chrome on macOS) — on those, navigator.share()
// hands off to the OS's own generic "Share" panel, which lists whatever
// apps are registered as system share-targets on that machine. Telegram
// registers a macOS share extension; WhatsApp's desktop app generally does
// not, so that panel would show Telegram (or nothing useful) instead of
// WhatsApp with no way for this page to control or influence that choice.
// The wa.me fallback path is deterministic and always targets WhatsApp
// specifically, so desktop always uses it regardless of API support.
function isMobileDevice() {
    return /Android|iPhone|iPad|iPod|Mobile|Silk|Opera Mini|BlackBerry|IEMobile/i.test(navigator.userAgent);
}

function shareInvoiceToWhatsApp(opts) {
    var doc = opts.doc || document;
    var el = doc.getElementById(opts.elementId || 'divToPrint');
    if (!el) {
        alert('Invoice content not found.');
        if (opts.preOpenedWindow && !opts.preOpenedWindow.closed) opts.preOpenedWindow.close();
        return;
    }

    var btn = opts.button || null;
    var originalLabel = btn ? btn.innerHTML : null;
    if (btn) { btn.disabled = true; btn.innerHTML = 'Preparing PDF…'; }

    if (typeof html2pdf === 'undefined') {
        alert('Could not load the PDF library — check your internet connection and try again.');
        if (btn) { btn.disabled = false; btn.innerHTML = originalLabel; }
        return;
    }

    var fileName = (opts.fileName || 'Invoice').replace(/[^a-z0-9_\-]/gi, '_') + '.pdf';
    var shareText = 'Invoice' + (opts.invoiceNumber ? ' #' + opts.invoiceNumber : '') +
        (opts.businessName ? ' from ' + opts.businessName : '');

    // Force html2canvas to capture the invoice at its own full content width
    // (not the browser's current viewport width). Without this, html2canvas
    // only rasterizes the visible viewport slice of a wide element — on a
    // desktop-width page that slice is narrower than the full invoice table,
    // so anything past the right edge (quantity/rate/total columns) never
    // makes it into the canvas and comes out cropped in the PDF. Pinning
    // width/windowWidth to the element's full scrollWidth guarantees the
    // whole table is captured, and html2pdf then scales that full-width
    // canvas down to fit the PDF page width.
    //
    // x/y/scrollX/scrollY must ALSO be pinned to 0 here. html2canvas
    // defaults scrollX/scrollY to the live page's current scroll position
    // when they're not given explicitly, then applies that as an offset
    // into the (now wider) capture — so on any page that happens to be
    // scrolled even slightly, the offset crops the LEFT edge of the
    // invoice by that many pixels (seller/buyer details, first table
    // column, etc.) instead of capturing from the element's true origin.
    var fullWidth = Math.max(el.scrollWidth, el.offsetWidth);

    // html2pdf's default .from(el) flow paginates against a fixed A4 page:
    // it slices the captured canvas into successive 297mm-tall chunks, so
    // an invoice that overflows A4 by even a few millimetres spills a
    // near-empty second page (just the declaration/signature footer).
    // Since this PDF exists to be shared/read on WhatsApp, not printed on
    // physical paper, one page whose height matches the content exactly is
    // far more useful than a "correct size" page 1 plus a near-empty page
    // 2 — so rather than fighting html2pdf's pagination, the PDF page
    // format itself is sized to the content (capped at A4 height so a
    // short invoice still gets a normal-looking A4 page, not a tiny sliver).
    var marginMm = 5;
    var pageWmm  = 210;
    var pageHmm  = 297;
    var availWmm = pageWmm - marginMm * 2;
    var availHmm = pageHmm - marginMm * 2;

    // html2pdf.bundle.min.js only exposes the top-level html2pdf() global —
    // html2canvas/jsPDF are bundled internally with no separate global —
    // so its own worker chain (.toCanvas()/.get()) is the only supported
    // way to reach the intermediate canvas.
    var html2canvasOpt = { scale: 2, useCORS: true, width: fullWidth, windowWidth: fullWidth, x: 0, y: 0, scrollX: 0, scrollY: 0 };
    var worker = html2pdf().set({ margin: marginMm, image: { type: 'jpeg', quality: 0.98 }, html2canvas: html2canvasOpt, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } }).from(el);

    worker.toCanvas().get('canvas').then(function (canvas) {
        var imgHmm = availWmm * canvas.height / canvas.width;
        var pageFormatHmm = Math.min(imgHmm, availHmm) + marginMm * 2;

        // Re-point the same worker at a page format sized to the content
        // height (capped at standard A4) instead of a fixed 297mm A4 page —
        // .toPdf() then places the already-captured canvas as one image
        // filling exactly that one page, so there is nothing left over to
        // spill onto a second page.
        worker.opt.jsPDF.format = [pageWmm, pageFormatHmm];
        return worker.toPdf().outputPdf('blob');
    }).then(function (pdfBlob) {
        if (btn) { btn.disabled = false; btn.innerHTML = originalLabel; }

        var pdfFile;
        try { pdfFile = new File([pdfBlob], fileName, { type: 'application/pdf' }); }
        catch (e) { pdfFile = null; }

        if (pdfFile && isMobileDevice() && navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
            if (opts.preOpenedWindow && !opts.preOpenedWindow.closed) opts.preOpenedWindow.close();
            navigator.share({ files: [pdfFile], title: fileName, text: shareText })
                .catch(function (err) {
                    if (err && err.name !== 'AbortError') {
                        whatsappShareFallback(pdfBlob, fileName, opts.mobile, shareText, opts.preOpenedWindow);
                    }
                });
        } else {
            whatsappShareFallback(pdfBlob, fileName, opts.mobile, shareText, opts.preOpenedWindow);
        }
    }).catch(function (err) {
        console.error('Invoice PDF generation failed:', err);
        alert('Could not generate the PDF. Please try Print instead.');
        if (btn) { btn.disabled = false; btn.innerHTML = originalLabel; }
        if (opts.preOpenedWindow && !opts.preOpenedWindow.closed) opts.preOpenedWindow.close();
    });
}

// Desktop / unsupported-browser path: no browser can attach a file to a
// wa.me link, so the PDF is downloaded and WhatsApp opens with a text
// message — the customer's shop has to attach the just-downloaded file
// manually. This is a real browser limitation, not something to work
// around from the web page itself.
function whatsappShareFallback(pdfBlob, fileName, mobile, shareText, preOpenedWindow) {
    var url = URL.createObjectURL(pdfBlob);
    var a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 8000);

    var digits = (mobile || '').replace(/\D/g, '');
    var waNumber = digits.length === 10 ? '91' + digits : digits; // bare 10-digit numbers are assumed Indian
    var msg = encodeURIComponent(shareText + ' — the PDF has been downloaded to your device, please attach it here.');
    var waUrl = (waNumber ? 'https://wa.me/' + waNumber : 'https://wa.me/') + '?text=' + msg;

    alert('PDF downloaded. WhatsApp will open next — please attach the downloaded file to the chat.');

    // Navigating an already-open window/tab is never blocked as a popup,
    // unlike window.open() called after the user's original click has gone
    // stale (e.g. after a page navigation or a multi-second async gap).
    if (preOpenedWindow && !preOpenedWindow.closed) {
        preOpenedWindow.location = waUrl;
    } else {
        setTimeout(function () { window.open(waUrl, '_blank'); }, 300);
    }
}
