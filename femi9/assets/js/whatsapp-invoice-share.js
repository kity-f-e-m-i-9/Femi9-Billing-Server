// Shared "Share to WhatsApp" for invoice print pages (TP + company).
// Converts the print page's invoice div into a PDF (html2pdf.js — CDN,
// loaded by each page that uses this) and hands it to the device's native
// share sheet via the Web Share API (navigator.share with files), which is
// the only way a browser can hand a file directly to the WhatsApp app —
// there is no wa.me parameter for attaching a file. Where that API/File
// support isn't available (most desktop browsers), it falls back to
// downloading the PDF and opening a WhatsApp chat with a text prompt asking
// the customer's shop to attach the file that was just downloaded.

function shareInvoiceToWhatsApp(opts) {
    var el = document.getElementById(opts.elementId || 'divToPrint');
    if (!el) { alert('Invoice content not found.'); return; }

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

    var pdfOpt = {
        margin: 5,
        filename: fileName,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(pdfOpt).from(el).outputPdf('blob').then(function (pdfBlob) {
        if (btn) { btn.disabled = false; btn.innerHTML = originalLabel; }

        var pdfFile;
        try { pdfFile = new File([pdfBlob], fileName, { type: 'application/pdf' }); }
        catch (e) { pdfFile = null; }

        if (pdfFile && navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
            navigator.share({ files: [pdfFile], title: fileName, text: shareText })
                .catch(function (err) {
                    if (err && err.name !== 'AbortError') {
                        whatsappShareFallback(pdfBlob, fileName, opts.mobile, shareText);
                    }
                });
        } else {
            whatsappShareFallback(pdfBlob, fileName, opts.mobile, shareText);
        }
    }).catch(function (err) {
        console.error('Invoice PDF generation failed:', err);
        alert('Could not generate the PDF. Please try Print instead.');
        if (btn) { btn.disabled = false; btn.innerHTML = originalLabel; }
    });
}

// Desktop / unsupported-browser path: no browser can attach a file to a
// wa.me link, so the PDF is downloaded and WhatsApp opens with a text
// message — the customer's shop has to attach the just-downloaded file
// manually. This is a real browser limitation, not something to work
// around from the web page itself.
function whatsappShareFallback(pdfBlob, fileName, mobile, shareText) {
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
    setTimeout(function () { window.open(waUrl, '_blank'); }, 300);
}
