/* ============================================================
   TPMS - Application JavaScript
   ============================================================ */

const TPMS = {
    baseUrl: document.querySelector('meta[name="base-url"]')?.content || '',

    // SweetAlert2 confirm modal
    confirm(title, text, confirmText, confirmColor) {
        return Swal.fire({
            title, html: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: confirmColor || '#1a3a5c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText || 'Confirm',
            cancelButtonText: 'Cancel',
        });
    },

    // Generic AJAX POST
    async post(url, data) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data),
        });
        return resp.json();
    },

    // Toast notification
    toast(msg, type = 'success') {
        Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false,
            timer: 3000, timerProgressBar: true, icon: type, title: msg });
    },

    // Plan workflow actions
    async planAction(planId, action, extraData = {}) {
        const messages = {
            submit:   { title: 'Forward to Division Chief?',
                        text:  'Are you certain you wish to forward this document to the Division Chief for review?',
                        btn:   'Yes, Forward', color: '#1a3a5c' },
            dc_approve: { title: 'Approve & Forward to IQAO?',
                          text: 'Do you confirm the approval and forwarding of this document to the IQAO?',
                          btn:  'Yes, Approve', color: '#198754' },
            iqao_approve: { title: 'Forward to Office of the President?',
                            text: 'Do you confirm forwarding this finalized document to the Office of the President for signature?',
                            btn:  'Yes, Forward', color: '#1a3a5c' },
            sign:     { title: 'Sign & File Document?',
                        text:  'By signing, you officially approve these Quality Objectives. The document will be marked as a Controlled Copy.',
                        btn:   'Yes, Sign & File', color: '#198754' },
        };
        const msg = messages[action];
        if (msg) {
            const result = await TPMS.confirm(msg.title, msg.text, msg.btn, msg.color);
            if (!result.isConfirmed) return;
        }
        this.submitAction(planId, action, extraData);
    },

    submitAction(planId, action, extraData = {}) {
        const form = document.getElementById('actionForm');
        document.getElementById('actionInput').value = action;
        document.getElementById('planIdInput').value = planId;
        if (extraData.comment) document.getElementById('actionComment').value = extraData.comment || '';
        if (extraData.signature) document.getElementById('actionSignature').value = extraData.signature || '';
        if (extraData.revision_notes) document.getElementById('actionRevNotes').value = extraData.revision_notes || '';
        form.submit();
    },

    // Return for revision modal
    async returnForRevision(planId) {
        const { value: notes, isConfirmed } = await Swal.fire({
            title: 'Return for Revision',
            html: `<p class="text-muted small mb-3">Please provide specific revision notes so the author knows what to fix.</p>
                   <textarea id="revisionNotes" class="form-control" rows="5" placeholder="Enter revision notes..."></textarea>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Return Document',
            confirmButtonColor: '#dc3545',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                const val = document.getElementById('revisionNotes').value.trim();
                if (!val) { Swal.showValidationMessage('Revision notes are required.'); return false; }
                return val;
            }
        });
        if (isConfirmed && notes) {
            this.submitAction(planId, 'return', { revision_notes: notes });
        }
    },
};

// ---- Signature Pad ----
class SignaturePad {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.drawing = false;
        this.empty = true;
        this.setup();
    }
    setup() {
        this.canvas.addEventListener('mousedown', e => { this.drawing = true; this.ctx.beginPath(); this.ctx.moveTo(...this.pos(e)); });
        this.canvas.addEventListener('mousemove', e => { if (!this.drawing) return; this.ctx.lineTo(...this.pos(e)); this.ctx.stroke(); this.empty = false; });
        this.canvas.addEventListener('mouseup', () => { this.drawing = false; });
        this.canvas.addEventListener('touchstart', e => { e.preventDefault(); this.drawing = true; const t = e.touches[0]; this.ctx.beginPath(); this.ctx.moveTo(...this.pos(t)); });
        this.canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!this.drawing) return; const t = e.touches[0]; this.ctx.lineTo(...this.pos(t)); this.ctx.stroke(); this.empty = false; });
        this.canvas.addEventListener('touchend', () => { this.drawing = false; });
        this.ctx.strokeStyle = '#1a3a5c';
        this.ctx.lineWidth = 2;
        this.ctx.lineCap = 'round';
    }
    pos(e) {
        const r = this.canvas.getBoundingClientRect();
        return [e.clientX - r.left, e.clientY - r.top];
    }
    clear() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.empty = true;
    }
    toDataURL() { return this.canvas.toDataURL('image/png'); }
    isEmpty() { return this.empty; }
}

// ---- Objective row management (create/edit plan) ----
let objIndex = parseInt(document.getElementById('objCount')?.value || 0);

function addObjectiveRow() {
    const tbl = document.getElementById('objectivesTable');
    const idx = objIndex++;
    const tr = document.createElement('tr');
    tr.id = 'obj-row-' + idx;
    tr.innerHTML = `
        <td>${tbl.rows.length}</td>
        <td><textarea name="objectives[${idx}][quality_objective]" class="form-control form-control-sm" rows="3" required></textarea></td>
        <td><textarea name="objectives[${idx}][success_indicator]" class="form-control form-control-sm" rows="2"></textarea></td>
        <td><input type="text" name="objectives[${idx}][target]" class="form-control form-control-sm"></td>
        <td>
            <div class="quarter-check"><input type="checkbox" name="objectives[${idx}][q1]" value="1"> Q1</div>
            <div class="quarter-check"><input type="checkbox" name="objectives[${idx}][q2]" value="1"> Q2</div>
            <div class="quarter-check"><input type="checkbox" name="objectives[${idx}][q3]" value="1"> Q3</div>
            <div class="quarter-check"><input type="checkbox" name="objectives[${idx}][q4]" value="1"> Q4</div>
        </td>
        <td><input type="text" name="objectives[${idx}][person_responsible]" class="form-control form-control-sm"></td>
        <td><input type="number" name="objectives[${idx}][budget]" class="form-control form-control-sm" step="0.01" min="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow('obj-row-${idx}')"><i class="fas fa-trash"></i></button></td>`;
    tbl.appendChild(tr);
    document.getElementById('objCount').value = objIndex;
    renumberRows(tbl);
}

function removeRow(rowId) {
    document.getElementById(rowId)?.remove();
    const tbl = document.getElementById('objectivesTable');
    if (tbl) renumberRows(tbl);
}

function renumberRows(tbl) {
    Array.from(tbl.rows).forEach((r, i) => { if (r.cells[0]) r.cells[0].textContent = i + 1; });
}

// ---- Evidence preview ----
document.querySelectorAll('.evidence-file-input')?.forEach(inp => {
    inp.addEventListener('change', function() {
        const label = this.nextElementSibling;
        if (label && this.files.length > 0) {
            label.textContent = Array.from(this.files).map(f => f.name).join(', ');
        }
    });
});
