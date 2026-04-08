<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$user   = currentUser($pdo);
$role   = $user['role'];
$planId = (int)($_GET['id'] ?? 0);

$plan = getPlan($pdo, $planId);
if (!$plan) { flash('error', 'Plan not found.', 'danger'); redirect(APP_URL . '/plans/index.php'); }

// Access control
$canView = false;
if ($role === ROLE_IQAO || $role === ROLE_PRESIDENT) $canView = true;
elseif ($role === ROLE_PO && $plan['created_by'] == $user['id']) $canView = true;
elseif ($role === ROLE_DC && $plan['division_name'] == $user['division_name']) $canView = true;
if (!$canView) { flash('error', 'Access denied.', 'warning'); redirect(APP_URL . '/plans/index.php'); }

$objectives = getObjectives($pdo, $planId);

// Get comments
$comments = $pdo->prepare("
    SELECT c.*, u.name AS user_name, u.role AS user_role
    FROM comments c JOIN users u ON c.user_id = u.id
    WHERE c.tactical_plan_id = ? ORDER BY c.created_at ASC
");
$comments->execute([$planId]);
$comments = $comments->fetchAll();

// Get audit log
$auditLog = $pdo->prepare("
    SELECT al.*, u.name AS user_name, u.role AS user_role
    FROM audit_logs al JOIN users u ON al.user_id = u.id
    WHERE al.tactical_plan_id = ? ORDER BY al.created_at DESC
");
$auditLog->execute([$planId]);
$auditLog = $auditLog->fetchAll();

// Get evidence
$evidenceMap = [];
if (!empty($objectives)) {
    $ids = implode(',', array_column($objectives, 'id'));
    $evStmt = $pdo->query("SELECT e.*, u.name AS uploader FROM evidence e JOIN users u ON e.uploaded_by = u.id WHERE e.objective_id IN ($ids) ORDER BY e.uploaded_at DESC");
    foreach ($evStmt->fetchAll() as $ev) {
        $evidenceMap[$ev['objective_id']][] = $ev;
    }
}

$canEdit   = canEditPlan($plan, $user['id'], $role);
$canDCact  = ($role === ROLE_DC && $plan['status'] === 'submitted');
$canIQAOact = ($role === ROLE_IQAO && $plan['status'] === 'dc_approved');
$canPresact = ($role === ROLE_PRESIDENT && $plan['status'] === 'iqao_approved');
$canReturn  = ($canDCact || $canIQAOact || $canPresact);
$canApprove = ($canDCact || $canIQAOact || $canPresact);
$canTagStatus = ($role === ROLE_IQAO && $plan['status'] === 'signed');
$canComment = ($canDCact || $canIQAOact || $canPresact || $canEdit);

$pageTitle = 'View Plan &mdash; ' . e($plan['reference_no'] ?? '');
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Hidden action form -->
<form method="POST" action="<?= APP_URL ?>/ajax/plan_action.php" id="actionForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" id="actionInput">
    <input type="hidden" name="plan_id" id="planIdInput" value="<?= $planId ?>">
    <input type="hidden" name="comment" id="actionComment">
    <input type="hidden" name="signature" id="actionSignature">
    <input type="hidden" name="revision_notes" id="actionRevNotes">
</form>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header flex-wrap gap-2">
        <div>
            <h4 class="mb-0"><i class="fas fa-file-alt me-2 text-primary"></i><?= e($plan['reference_no'] ?? 'Tactical Plan') ?></h4>
            <small class="text-muted"><?= e($plan['dept_name']) ?> &mdash; AY <?= e($plan['academic_year']) ?></small>
        </div>
        <div class="d-flex gap-2 flex-wrap no-print">
            <?= statusBadge($plan['status']) ?>
            <?php if ($plan['is_controlled_copy']): ?>
            <span class="badge bg-danger border border-danger">CONTROLLED COPY</span>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/plans/print.php?id=<?= $planId ?>" class="btn btn-sm btn-outline-dark" target="_blank">
                <i class="fas fa-print me-1"></i> Print
            </a>
            <?php if ($canEdit): ?>
            <a href="<?= APP_URL ?>/plans/edit.php?id=<?= $planId ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <?php showFlash(); ?>
    <?php if ($plan['status'] === 'returned_to_po' && $plan['revision_notes']): ?>
    <div class="alert alert-warning d-flex gap-2">
        <i class="fas fa-exclamation-triangle mt-1 flex-shrink-0"></i>
        <div><strong>Revision Notes:</strong><br><?= nl2br(e($plan['revision_notes'])) ?></div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Document -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="doc-header">
                    <div class="fw-bold text-uppercase fs-5"><?= e(UNIVERSITY_NAME) ?></div>
                    <div class="mt-1">Quality Objectives / Tactical Plan</div>
                    <div class="small opacity-75">Academic Year: <?= e($plan['academic_year']) ?></div>
                    <?php if ($plan['is_controlled_copy']): ?>
                    <div class="controlled-copy-stamp">CONTROLLED COPY</div>
                    <?php endif; ?>
                </div>
                <div class="doc-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <small class="text-muted">Department/Office</small>
                            <div class="fw-semibold"><?= e($plan['dept_name']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Division</small>
                            <div class="fw-semibold"><?= e($plan['division_name'] ?? '&mdash;') ?></div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <small class="text-muted">Prepared By</small>
                            <div class="fw-semibold"><?= e($plan['created_by_name']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Reference No.</small>
                            <div class="fw-semibold"><?= e($plan['reference_no'] ?? 'N/A') ?></div>
                        </div>
                    </div>

                    <div class="doc-section-title">Quality Objectives</div>
                    <?php if (empty($objectives)): ?>
                    <p class="text-muted text-center py-3">No objectives added yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" style="font-size:.85rem">
                            <thead style="background:#1a3a5c;color:#fff">
                                <tr>
                                    <th style="width:30px">#</th>
                                    <th>Quality Objective</th>
                                    <th>Success Indicator</th>
                                    <th>Target</th>
                                    <th>Timeline</th>
                                    <th>Person Responsible</th>
                                    <th>Budget (₱)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($objectives as $i => $obj): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= nl2br(e($obj['quality_objective'])) ?></td>
                                    <td><?= nl2br(e($obj['success_indicator'])) ?></td>
                                    <td><?= e($obj['target']) ?></td>
                                    <td class="text-center">
                                        <?php for ($q=1;$q<=4;$q++): ?>
                                        <?php if ($obj['timeline_q' . $q]): ?><span class="badge bg-primary me-1">Q<?= $q ?></span><?php endif; ?>
                                        <?php endfor; ?>
                                    </td>
                                    <td><?= e($obj['person_responsible']) ?></td>
                                    <td class="text-end">₱<?= number_format($obj['budget'], 2) ?></td>
                                    <td>
                                        <?= objStatusBadge($obj['status']) ?>
                                        <?php if ($canTagStatus): ?>
                                        <div class="mt-1">
                                            <select class="form-select form-select-sm status-tagger" data-obj-id="<?= $obj['id'] ?>" style="font-size:.75rem">
                                                <option value="">Tag Status</option>
                                                <option value="accomplished" <?= $obj['status']==='accomplished'?'selected':'' ?>>Accomplished</option>
                                                <option value="ongoing" <?= $obj['status']==='ongoing'?'selected':'' ?>>On-going</option>
                                                <option value="not_accomplished" <?= $obj['status']==='not_accomplished'?'selected':'' ?>>Not Accomplished</option>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($evidenceMap[$obj['id']])): ?>
                                <tr class="table-light">
                                    <td></td>
                                    <td colspan="7">
                                        <small class="text-muted fw-semibold">Evidence:</small>
                                        <?php foreach ($evidenceMap[$obj['id']] as $ev): ?>
                                        <a href="<?= APP_URL ?>/uploads/evidence/<?= e($ev['file_path']) ?>" target="_blank" class="badge bg-secondary text-decoration-none ms-1">
                                            <i class="fas fa-paperclip"></i> <?= e($ev['original_name']) ?>
                                        </a>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="6" class="text-end fw-bold">Total Budget:</td>
                                    <td class="text-end fw-bold">₱<?= number_format(array_sum(array_column($objectives, 'budget')), 2) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Approval Trail -->
                    <?php if ($plan['status'] !== 'draft'): ?>
                    <div class="doc-section-title mt-4">Approval / Signature Trail</div>
                    <div class="row mt-3">
                        <div class="col-md-4 text-center">
                            <div class="pb-2 border-bottom">
                                <div class="small text-muted">Prepared By</div>
                                <div class="fw-bold"><?= e($plan['created_by_name']) ?></div>
                                <div class="small text-muted">Process Owner</div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="pb-2 border-bottom">
                                <div class="small text-muted">Reviewed By</div>
                                <div class="fw-bold"><?= $plan['dc_approved_by_name'] ? e($plan['dc_approved_by_name']) : '<span class="text-muted">Pending</span>' ?></div>
                                <div class="small text-muted">Division Chief</div>
                                <?php if ($plan['dc_approved_at']): ?><div class="small text-muted"><?= date('M j, Y', strtotime($plan['dc_approved_at'])) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <?php if ($plan['status'] === 'signed' && $plan['president_signature']): ?>
                            <div class="pb-2 border-bottom">
                                <div class="small text-muted">Signed By</div>
                                <img src="<?= $plan['president_signature'] ?>" alt="Signature" style="max-height:60px;max-width:150px">
                                <div class="fw-bold"><?= e($plan['signed_by_name']) ?></div>
                                <div class="small text-muted">University President</div>
                                <div class="small text-muted"><?= date('M j, Y', strtotime($plan['signed_at'])) ?></div>
                            </div>
                            <?php else: ?>
                            <div class="pb-2 border-bottom text-muted">
                                <div class="small">Signed By</div>
                                <div class="fw-bold text-muted">Pending</div>
                                <div class="small">University President</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <?php if ($canApprove || $canReturn || $canPresact): ?>
            <div class="card border-0 shadow-sm mt-3 no-print">
                <div class="card-body d-flex gap-2 flex-wrap">
                    <?php if ($canDCact): ?>
                    <button class="btn btn-success" onclick="TPMS.planAction(<?= $planId ?>,'dc_approve')">
                        <i class="fas fa-check-circle me-1"></i> Approve & Forward to IQAO
                    </button>
                    <?php endif; ?>
                    <?php if ($canIQAOact): ?>
                    <button class="btn btn-success" onclick="TPMS.planAction(<?= $planId ?>,'iqao_approve')">
                        <i class="fas fa-check-circle me-1"></i> Forward to President
                    </button>
                    <?php endif; ?>
                    <?php if ($canPresact): ?>
                    <button class="btn btn-success" onclick="openSignModal()">
                        <i class="fas fa-signature me-1"></i> Sign & Approve
                    </button>
                    <?php endif; ?>
                    <?php if ($canReturn): ?>
                    <button class="btn btn-outline-danger" onclick="TPMS.returnForRevision(<?= $planId ?>)">
                        <i class="fas fa-undo me-1"></i> Return for Revision
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar: Comments & Audit -->
        <div class="col-lg-4">
            <!-- Add Comment -->
            <?php if ($canComment): ?>
            <div class="card border-0 shadow-sm mb-3 no-print">
                <div class="card-header bg-white border-0">
                    <h6 class="fw-bold mb-0"><i class="fas fa-comment me-2 text-primary"></i>Add Remark</h6>
                </div>
                <div class="card-body">
                    <form id="commentForm">
                        <input type="hidden" name="plan_id" value="<?= $planId ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <textarea name="comment" id="commentText" class="form-control mb-2" rows="3" placeholder="Enter your remarks..."></textarea>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-paper-plane me-1"></i> Post Remark
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Comments List -->
            <?php if (!empty($comments)): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="fw-bold mb-0"><i class="fas fa-comments me-2 text-primary"></i>Remarks (<?= count($comments) ?>)</h6>
                </div>
                <div class="card-body p-0" id="commentsList">
                    <?php foreach ($comments as $c): ?>
                    <div class="p-3 border-bottom">
                        <div class="d-flex gap-2">
                            <div class="avatar-circle flex-shrink-0"><?= strtoupper(substr($c['user_name'],0,1)) ?></div>
                            <div>
                                <div class="fw-semibold small"><?= e($c['user_name']) ?>
                                    <span class="badge bg-light text-secondary ms-1" style="font-size:.7rem"><?= e(str_replace('_',' ',ucfirst($c['user_role']))) ?></span>
                                </div>
                                <div class="small text-muted"><?= date('M j, Y g:i A', strtotime($c['created_at'])) ?></div>
                                <div class="mt-1"><?= nl2br(e($c['comment'])) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Audit Log -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>Audit Trail</h6>
                    <span class="badge bg-secondary"><?= count($auditLog) ?></span>
                </div>
                <div class="card-body p-2" style="max-height:350px;overflow-y:auto">
                    <?php if (empty($auditLog)): ?>
                    <div class="text-center text-muted py-3 small">No history yet.</div>
                    <?php else: ?>
                    <?php foreach ($auditLog as $log): ?>
                    <div class="audit-item">
                        <div class="audit-action"><?= e($log['action']) ?></div>
                        <?php if ($log['details']): ?><div class="small"><?= e($log['details']) ?></div><?php endif; ?>
                        <div class="audit-meta">
                            <i class="fas fa-user me-1"></i><?= e($log['user_name']) ?>
                            &nbsp;|&nbsp;<i class="fas fa-clock me-1"></i><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Signature Modal -->
<div class="modal fade" id="signModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-signature me-2 text-primary"></i>Digital Signature</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please sign below using your mouse or touch device to officially approve this document.</p>
                <div class="sig-pad-wrapper text-center">
                    <canvas id="signatureCanvas" width="580" height="200"></canvas>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-muted">Draw your signature above</small>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="sigPad.clear()">
                        <i class="fas fa-eraser me-1"></i> Clear
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="confirmSign()">
                    <i class="fas fa-check me-1"></i> Sign & Approve
                </button>
            </div>
        </div>
    </div>
</div>

<?php $extraJs = '<script>
let sigPad;

function openSignModal() {
    const modal = new bootstrap.Modal(document.getElementById("signModal"));
    modal.show();
    setTimeout(() => {
        sigPad = new SignaturePad(document.getElementById("signatureCanvas"));
    }, 300);
}

async function confirmSign() {
    if (!sigPad || sigPad.isEmpty()) {
        TPMS.toast("Please draw your signature first.", "warning");
        return;
    }
    const result = await TPMS.confirm(
        "Sign & File Document?",
        "By signing, you officially approve these Quality Objectives. The document will be filed as a Controlled Copy.",
        "Yes, Sign & File", "#198754"
    );
    if (result.isConfirmed) {
        document.getElementById("actionSignature").value = sigPad.toDataURL();
        TPMS.submitAction(' . $planId . ', "sign");
    }
}

// Comment form AJAX
document.getElementById("commentForm")?.addEventListener("submit", async function(e) {
    e.preventDefault();
    const text = document.getElementById("commentText").value.trim();
    if (!text) return;
    const fd = new FormData(this);
    const resp = await fetch("' . APP_URL . '/ajax/add_comment.php", { method:"POST", body:fd });
    const data = await resp.json();
    if (data.success) {
        TPMS.toast("Remark posted.");
        document.getElementById("commentText").value = "";
        setTimeout(() => location.reload(), 800);
    } else {
        TPMS.toast(data.error || "Error posting remark.", "error");
    }
});

// Status tagger
document.querySelectorAll(".status-tagger").forEach(sel => {
    sel.addEventListener("change", async function() {
        const objId = this.dataset.objId;
        const status = this.value;
        if (!status) return;
        const fd = new FormData();
        fd.append("objective_id", objId);
        fd.append("status", status);
        fd.append("csrf_token", "' . csrfToken() . '");
        const resp = await fetch("' . APP_URL . '/ajax/tag_status.php", { method:"POST", body:fd });
        const data = await resp.json();
        if (data.success) {
            TPMS.toast("Status updated to: " + status.replace("_"," "), "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            TPMS.toast(data.error || "Error updating status.", "error");
        }
    });
});
</script>'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
