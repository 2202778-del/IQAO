<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth([ROLE_PO, ROLE_IQAO]);

$user = currentUser($pdo);
$role = $user['role'];

// Get plans relevant to this user
if ($role === ROLE_PO) {
    $stmt = $pdo->prepare("SELECT tp.*, d.name AS dept_name FROM tactical_plans tp JOIN departments d ON tp.department_id = d.id WHERE tp.created_by = ? ORDER BY tp.academic_year DESC");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $pdo->prepare("SELECT tp.*, d.name AS dept_name FROM tactical_plans tp JOIN departments d ON tp.department_id = d.id ORDER BY tp.updated_at DESC");
    $stmt->execute();
}
$plans = $stmt->fetchAll();

$selectedPlan = null;
$objectives   = [];
$evidenceMap  = [];

$planId = (int)($_GET['plan_id'] ?? 0);
if ($planId) {
    $selectedPlan = getPlan($pdo, $planId);
    if ($selectedPlan) {
        $objectives = getObjectives($pdo, $planId);
        if (!empty($objectives)) {
            $ids = implode(',', array_column($objectives, 'id'));
            $evStmt = $pdo->query("SELECT e.*, u.name AS uploader FROM evidence e JOIN users u ON e.uploaded_by = u.id WHERE e.objective_id IN ($ids) ORDER BY e.uploaded_at DESC");
            foreach ($evStmt->fetchAll() as $ev) {
                $evidenceMap[$ev['objective_id']][] = $ev;
            }
        }
    }
}

$pageTitle = 'Evidence Management';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <h4><i class="fas fa-paperclip me-2 text-primary"></i>Evidence Management</h4>
    </div>

    <?php showFlash(); ?>

    <div class="row g-3">
        <!-- Plan Selector -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="fw-bold mb-0">Select Tactical Plan</h6>
                </div>
                <div class="list-group list-group-flush" style="max-height:600px;overflow-y:auto">
                    <?php if (empty($plans)): ?>
                    <div class="list-group-item text-muted text-center py-4">No plans found.</div>
                    <?php else: ?>
                    <?php foreach ($plans as $p): ?>
                    <a href="?plan_id=<?= $p['id'] ?>" class="list-group-item list-group-item-action <?= $planId == $p['id'] ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small"><?= e($p['dept_name']) ?></div>
                                <div class="small <?= $planId == $p['id'] ? 'text-white-50' : 'text-muted' ?>">AY <?= e($p['academic_year']) ?> &mdash; <?= e($p['reference_no'] ?? 'Draft') ?></div>
                            </div>
                            <?= statusBadge($p['status']) ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Evidence Upload Area -->
        <div class="col-lg-8">
            <?php if (!$selectedPlan): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                    <p>Select a tactical plan from the left to manage evidence files.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-0"><?= e($selectedPlan['dept_name']) ?> &mdash; AY <?= e($selectedPlan['academic_year']) ?></h6>
                        <small class="text-muted"><?= e($selectedPlan['reference_no'] ?? '') ?></small>
                    </div>
                    <?= statusBadge($selectedPlan['status']) ?>
                </div>
            </div>

            <?php foreach ($objectives as $obj): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold small">Objective #<?= e($obj['sort_order'] + 1) ?>: <?= e(substr($obj['quality_objective'], 0, 80)) ?>...</div>
                    </div>
                    <?= objStatusBadge($obj['status']) ?>
                </div>
                <div class="card-body">
                    <!-- Existing evidence -->
                    <?php if (!empty($evidenceMap[$obj['id']])): ?>
                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-2">Uploaded Evidence (<?= count($evidenceMap[$obj['id']]) ?>):</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($evidenceMap[$obj['id']] as $ev): ?>
                            <?php
                            $ext = strtolower(pathinfo($ev['original_name'], PATHINFO_EXTENSION));
                            $icon = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image'
                                  : (in_array($ext, ['mp4','avi','mov','wmv']) ? 'video'
                                  : (in_array($ext, ['pdf']) ? 'file-pdf'
                                  : (in_array($ext, ['doc','docx']) ? 'file-word'
                                  : 'file')));
                            ?>
                            <div class="border rounded p-2 d-flex align-items-center gap-2" style="max-width:260px">
                                <i class="fas fa-<?= $icon ?> text-primary fa-lg"></i>
                                <div class="overflow-hidden flex-grow-1">
                                    <div class="small fw-semibold text-truncate"><?= e($ev['original_name']) ?></div>
                                    <div class="text-muted" style="font-size:.7rem">
                                        <?= formatFileSize($ev['file_size']) ?> &bull; <?= e($ev['uploader']) ?>
                                    </div>
                                </div>
                                <a href="<?= APP_URL ?>/uploads/evidence/<?= e($ev['file_path']) ?>" target="_blank" class="btn btn-xs btn-sm btn-outline-primary py-0 px-1" style="font-size:.75rem"><i class="fas fa-external-link-alt"></i></a>
                                <?php if ($role === ROLE_PO && $ev['uploaded_by'] == $user['id']): ?>
                                <button class="btn btn-xs btn-sm btn-outline-danger py-0 px-1" style="font-size:.75rem" onclick="deleteEvidence(<?= $ev['id'] ?>, this)"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upload form (PO only) -->
                    <?php if ($role === ROLE_PO): ?>
                    <div class="upload-zone border-2 border-dashed rounded p-3 text-center" style="border-color:#dee2e6;border-style:dashed;" id="zone-<?= $obj['id'] ?>">
                        <input type="file" id="file-<?= $obj['id'] ?>" class="d-none" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.avi,.mov,.zip,.rar">
                        <label for="file-<?= $obj['id'] ?>" style="cursor:pointer">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2 d-block"></i>
                            <div class="small text-muted">Click to upload or drag and drop files here</div>
                            <div class="text-muted mt-1" style="font-size:.75rem">PDF, Word, Excel, Images, Videos &mdash; Max 50MB each</div>
                        </label>
                        <div id="upload-status-<?= $obj['id'] ?>" class="mt-2"></div>
                        <button class="btn btn-sm btn-primary mt-2 d-none" id="upload-btn-<?= $obj['id'] ?>" onclick="uploadFiles(<?= $obj['id'] ?>)">
                            <i class="fas fa-upload me-1"></i> Upload Selected Files
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $extraJs = '<script>
// File selection
document.querySelectorAll("input[type=file]").forEach(inp => {
    inp.addEventListener("change", function() {
        const objId = this.id.replace("file-", "");
        const btn = document.getElementById("upload-btn-" + objId);
        const status = document.getElementById("upload-status-" + objId);
        if (this.files.length > 0) {
            btn.classList.remove("d-none");
            status.innerHTML = `<div class="small text-primary">${this.files.length} file(s) selected</div>`;
        }
    });
});

// Drag-drop zones
document.querySelectorAll(".upload-zone").forEach(zone => {
    const objId = zone.id.replace("zone-", "");
    zone.addEventListener("dragover", e => { e.preventDefault(); zone.style.background="#f0f6ff"; });
    zone.addEventListener("dragleave", () => { zone.style.background=""; });
    zone.addEventListener("drop", e => {
        e.preventDefault(); zone.style.background="";
        document.getElementById("file-" + objId).files = e.dataTransfer.files;
        document.getElementById("file-" + objId).dispatchEvent(new Event("change"));
    });
});

async function uploadFiles(objId) {
    const fileInput = document.getElementById("file-" + objId);
    if (!fileInput.files.length) return;
    const fd = new FormData();
    fd.append("objective_id", objId);
    for (const f of fileInput.files) fd.append("evidence_files[]", f);

    const btn = document.getElementById("upload-btn-" + objId);
    btn.disabled = true; btn.innerHTML = \'<i class="fas fa-spinner fa-spin me-1"></i> Uploading...\';

    const resp = await fetch(\'' . APP_URL . '/ajax/upload_evidence.php\', { method: "POST", body: fd });
    const data = await resp.json();

    if (data.success) {
        TPMS.toast(data.uploaded.length + " file(s) uploaded successfully!");
        setTimeout(() => location.reload(), 1000);
    } else {
        const msg = data.errors.join("<br>");
        TPMS.toast(msg || "Upload failed.", "error");
        btn.disabled = false; btn.innerHTML = \'<i class="fas fa-upload me-1"></i> Upload Selected Files\';
    }
}

async function deleteEvidence(evId, btn) {
    const r = await TPMS.confirm("Delete File?", "Are you sure you want to remove this evidence file?", "Yes, Delete", "#dc3545");
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append("evidence_id", evId);
    fd.append("csrf_token", "' . csrfToken() . '");
    const resp = await fetch(\'' . APP_URL . '/ajax/delete_evidence.php\', { method:"POST", body:fd });
    const data = await resp.json();
    if (data.success) { TPMS.toast("File removed."); btn.closest(".border").remove(); }
    else TPMS.toast(data.error || "Error.", "error");
}
</script>'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
