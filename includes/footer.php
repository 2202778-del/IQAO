    </div><!-- end content-wrapper -->
</div><!-- end wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Init DataTables
$(document).ready(function() {
    $('.datatable').DataTable({ responsive: true, order: [[0,'desc']] });
});

// Sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.content-wrapper').classList.toggle('expanded');
});

// Load notifications
function loadNotifications() {
    fetch('<?= APP_URL ?>/ajax/get_notifications.php')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('notifCount');
            const list = document.getElementById('notifList');
            if (data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
            if (data.notifications.length === 0) {
                list.innerHTML = '<div class="text-center py-4 text-muted small"><i class="fas fa-bell-slash mb-2 d-block fa-lg"></i>No new notifications</div>';
            } else {
                list.innerHTML = data.notifications.map(n => `
                    <a href="${n.link || '#'}" class="notif-item d-flex gap-2 px-3 py-2 text-decoration-none border-bottom ${n.is_read ? 'read' : 'unread'}" data-id="${n.id}">
                        <i class="fas fa-circle-info text-primary mt-1"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">${n.title}</div>
                            <div class="small text-muted">${n.message.substring(0, 80)}...</div>
                            <div class="text-muted" style="font-size:11px">${n.created_at}</div>
                        </div>
                    </a>`).join('');
            }
        });
}

// Mark all read
document.getElementById('markAllRead')?.addEventListener('click', function(e) {
    e.preventDefault();
    fetch('<?= APP_URL ?>/ajax/get_notifications.php?action=mark_all_read', {method:'POST'})
        .then(() => loadNotifications());
});

// Load on bell open
document.getElementById('notifBell')?.addEventListener('click', loadNotifications);
loadNotifications();
setInterval(loadNotifications, 60000);
</script>
<?php if (isset($extraJs)): echo $extraJs; endif; ?>
</body>
</html>
