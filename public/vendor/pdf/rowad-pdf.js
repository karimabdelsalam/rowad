/*
 * Aqar Nova: a page element saved as an A4 PDF, drawn by the browser itself (html-to-image renders through an SVG
 * foreignObject), so Arabic shaping, spacing and fonts are exactly what is printed. Long content is split into pages
 * at the bottom of a row, paragraph or section, never through a line of text.
 */
(function () {
    const BREAKS = 'tr, p, li, h1, h2, h3, dl > div, .section, .sign, .signatures, .totals, .doc-header, .doc-title, .doc-footer, .rowad-acc-card, .hero-amount, .amount-words, img';

    /**
     * A copy of the element beside it (same styles), without the attributes a page's scripts add (x-on:click, wire:model,
     * @click…): they are not valid in the SVG the picture is drawn through.
     */
    function cleanCopy(element) {
        const copy = element.cloneNode(true);
        for (const node of [copy, ...copy.querySelectorAll('*')]) {
            for (const attribute of [...node.attributes]) {
                if (/[:@]/.test(attribute.name) || /^(x-|wire)/.test(attribute.name)) {
                    node.removeAttribute(attribute.name);
                }
            }
        }
        // what the page shows in form fields, so filters and inputs print with their values
        const sources = element.querySelectorAll('input, select, textarea');
        copy.querySelectorAll('input, select, textarea').forEach((field, i) => {
            const value = sources[i]?.value ?? '';
            field.tagName === 'SELECT' ? (field.value = value) : field.setAttribute('value', value);
        });
        Object.assign(copy.style, { position: 'absolute', top: '0', left: '-100000px', width: element.offsetWidth + 'px' });
        element.after(copy);

        return copy;
    }

    async function save(element, filename, options = {}) {
        const copy = cleanCopy(element);
        try {
            await draw(copy, filename, options);
        } finally {
            copy.remove();
        }
    }

    async function draw(element, filename, options) {
        const landscape = !! options.landscape;
        const margin = options.margin ?? 0;
        await document.fonts.ready;

        const width = element.scrollWidth;
        const height = element.scrollHeight;
        const ratio = 2;
        const canvas = await window.htmlToImage.toCanvas(element, {
            pixelRatio: ratio, backgroundColor: '#ffffff', width, height, cacheBust: true,
            style: { margin: '0', boxShadow: 'none', position: 'static', left: 'auto', top: 'auto' },
        });

        const pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', orientation: landscape ? 'landscape' : 'portrait' });
        const pageWidth = pdf.internal.pageSize.getWidth() - margin * 2;
        const pageHeight = pdf.internal.pageSize.getHeight() - margin * 2;
        const pxPerMm = width / pageWidth;
        const pagePx = pageHeight * pxPerMm;

        // where a page may end: the bottom edge of a row, paragraph, section…
        const top = element.getBoundingClientRect().top;
        const edges = [...element.querySelectorAll(BREAKS)].map((n) => n.getBoundingClientRect().bottom - top).filter((y) => y > 0).sort((a, b) => a - b);
        const cuts = [];
        let from = 0;
        const later = margin === 0 ? 10 * pxPerMm : 0; // continuation pages start 10mm lower
        while (height - from > (cuts.length ? pagePx - later : pagePx) + 1) {
            const room = cuts.length ? pagePx - later : pagePx;
            const limit = from + room;
            const fit = edges.filter((y) => y > from + room * 0.4 && y <= limit).pop();
            from = fit ?? limit;
            cuts.push(from);
        }
        // the last page only when something is left on it, not just the sheet's bottom padding
        const lastContent = edges.length ? edges[edges.length - 1] : height;
        if (! cuts.length || lastContent > from + 2) {
            cuts.push(height);
        }

        let start = 0;
        cuts.forEach((end, i) => {
            const slice = document.createElement('canvas');
            slice.width = canvas.width;
            slice.height = Math.ceil((end - start) * ratio);
            const ctx = slice.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, slice.width, slice.height);
            ctx.drawImage(canvas, 0, Math.floor(start * ratio), canvas.width, slice.height, 0, 0, slice.width, slice.height);
            if (i > 0) {
                pdf.addPage();
            }
            // pages after the first start a little lower, as a printed continuation page would
            const offset = i > 0 && margin === 0 ? 10 : 0;
            pdf.addImage(slice.toDataURL('image/jpeg', 0.92), 'JPEG', margin, margin + offset, pageWidth, (end - start) / pxPerMm, undefined, 'FAST');
            start = end;
        });

        pdf.save(filename.endsWith('.pdf') ? filename : filename + '.pdf');
    }

    const scripts = ['html-to-image.js', 'jspdf.umd.min.js'];
    let loading = null;
    function load(base) {
        if (window.htmlToImage && window.jspdf) {
            return Promise.resolve();
        }
        return loading ??= Promise.all(scripts.map((file) => new Promise((resolve, reject) => {
            const tag = Object.assign(document.createElement('script'), { src: base + file, onload: resolve, onerror: reject });
            document.head.append(tag);
        })));
    }

    const self = document.currentScript ?? document.querySelector('script[src*="rowad-pdf.js"]');
    const base = (self?.src ?? '/vendor/pdf/rowad-pdf.js').replace(/rowad-pdf\.js.*$/, '');
    window.RowadPdf = { save: async (element, filename, options) => { await load(base); return save(element, filename, options); } };
})();
