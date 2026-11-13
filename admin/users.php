<?php
/**
 * Denthub Dental Clinic - User Management (Admin Only)
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('admin');

$db = getDB();

// List columns only. Username and phone are not loaded here.
$users = $db->query("SELECT u.user_id, u.full_name, u.email, u.role, u.is_active, u.created_at, b.branch_name
                     FROM users u
                     LEFT JOIN branches b ON u.branch_id = b.branch_id
                     ORDER BY u.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php echo denthubFaviconTags(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="denthub-page-title"><i class="bi bi-person-gear me-2"></i> User Management</h1>
            <button type="button" class="btn btn-primary" id="openAddUser" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-circle"></i> Add New User
            </button>
        </div>
        <div id="userPageAlert"></div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 users-table">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Full Name</th>
                                <th class="text-nowrap">Email</th>
                                <th class="text-nowrap">Role</th>
                                <th class="text-nowrap">Branch</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap">Created</th>
                                <th class="text-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users->num_rows > 0): ?>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td>
                                            <span class="sensitive-value">
                                                <span class="sensitive-masked"><?php echo htmlspecialchars(maskEmail($user['email'])); ?></span>
                                                <span class="sensitive-full d-none"><?php echo htmlspecialchars($user['email']); ?></span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary sensitive-toggle ms-1" aria-label="Show email">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'dentist' ? 'info' : 'secondary'); ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['branch_name'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap"><?php echo formatDate($user['created_at']); ?></td>
                                        <td>
                                            <a href="edit-user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </main>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable add-user-modal">
            <div class="modal-content">
                <div class="modal-header denthub-modal-header">
                    <h5 class="modal-title" id="addUserModalLabel"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="addUserModalBody">
                    <div class="text-center text-muted py-5">Loading form…</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAddUserAlert(box, type, message) {
            if (!box) return;
            box.replaceChildren();
            const div = document.createElement('div');
            div.className = 'alert alert-' + type + ' mb-3';
            div.textContent = message;
            box.appendChild(div);
        }

        function parseAddUserResponse(text) {
            const raw = String(text || '').trim();
            try {
                return JSON.parse(raw);
            } catch (err) {
                const start = raw.indexOf('{');
                const end = raw.lastIndexOf('}');
                if (start === -1 || end <= start) throw err;
                return JSON.parse(raw.slice(start, end + 1));
            }
        }

        function roleBadgeClass(role) {
            if (role === 'admin') return 'danger';
            if (role === 'dentist') return 'info';
            return 'secondary';
        }

        function prependCreatedUser(user) {
            const tbody = document.querySelector('.users-table tbody');
            if (!tbody || !user) return;
            const empty = tbody.querySelector('td[colspan]');
            if (empty) empty.closest('tr').remove();

            const tr = document.createElement('tr');

            const nameCell = document.createElement('td');
            nameCell.textContent = user.full_name || '';
            tr.appendChild(nameCell);

            const emailCell = document.createElement('td');
            const sensitive = document.createElement('span');
            sensitive.className = 'sensitive-value';
            const masked = document.createElement('span');
            masked.className = 'sensitive-masked';
            masked.textContent = user.email_masked || user.email || '';
            const full = document.createElement('span');
            full.className = 'sensitive-full d-none';
            full.textContent = user.email || '';
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'btn btn-sm btn-outline-secondary sensitive-toggle ms-1';
            toggle.setAttribute('aria-label', 'Show email');
            const eye = document.createElement('i');
            eye.className = 'bi bi-eye';
            toggle.appendChild(eye);
            sensitive.appendChild(masked);
            sensitive.appendChild(full);
            sensitive.appendChild(toggle);
            emailCell.appendChild(sensitive);
            tr.appendChild(emailCell);

            const roleCell = document.createElement('td');
            const roleBadge = document.createElement('span');
            const role = user.role || '';
            roleBadge.className = 'badge bg-' + roleBadgeClass(role);
            roleBadge.textContent = role ? role.charAt(0).toUpperCase() + role.slice(1) : '';
            roleCell.appendChild(roleBadge);
            tr.appendChild(roleCell);

            const branchCell = document.createElement('td');
            branchCell.textContent = user.branch_name || 'N/A';
            tr.appendChild(branchCell);

            const statusCell = document.createElement('td');
            const statusBadge = document.createElement('span');
            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = 'Active';
            statusCell.appendChild(statusBadge);
            tr.appendChild(statusCell);

            const createdCell = document.createElement('td');
            createdCell.className = 'text-nowrap';
            createdCell.textContent = user.created_at || '';
            tr.appendChild(createdCell);

            const actionCell = document.createElement('td');
            const edit = document.createElement('a');
            edit.className = 'btn btn-sm btn-outline-primary';
            edit.href = 'edit-user.php?id=' + encodeURIComponent(user.user_id);
            edit.textContent = 'Edit';
            actionCell.appendChild(edit);
            tr.appendChild(actionCell);

            tbody.insertBefore(tr, tbody.firstChild);
        }

        function showCreatedUserNotice(message) {
            const box = document.getElementById('userPageAlert');
            if (!box) return;
            box.replaceChildren();
            const div = document.createElement('div');
            div.className = 'alert alert-success alert-dismissible fade show';
            div.setAttribute('role', 'alert');
            div.textContent = message || 'User account created successfully.';
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close';
            close.setAttribute('data-bs-dismiss', 'alert');
            close.setAttribute('aria-label', 'Close');
            div.appendChild(close);
            box.appendChild(div);
        }

        function bindAddUserForm(root) {
            const form = root.querySelector('#addUserForm');
            const role = root.querySelector('#role');
            const dentistFields = root.querySelector('#dentistFields');
            function syncRole() {
                if (!role || !dentistFields) return;
                dentistFields.style.display = role.value === 'dentist' ? 'block' : 'none';
            }
            if (role) {
                role.addEventListener('change', syncRole);
                syncRole();
            }
            if (!form || form.dataset.bound === '1') return;
            form.dataset.bound = '1';
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const alertBox = form.querySelector('#addUserFormAlert');
                const submitBtn = form.querySelector('[type="submit"]');
                const data = new FormData(form);
                data.set('ajax', '1');
                if (submitBtn) submitBtn.disabled = true;
                fetch('add-user.php', {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                }).then(function (response) {
                    return response.text().then(function (text) {
                        return parseAddUserResponse(text);
                    });
                }).then(function (payload) {
                    payload = payload || {};
                    if (payload.success) {
                        if (payload.user) prependCreatedUser(payload.user);
                        showCreatedUserNotice(payload.message || 'User account created successfully.');
                        const modalEl = document.getElementById('addUserModal');
                        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                        modal.hide();
                        return;
                    }
                    showAddUserAlert(alertBox, 'danger', payload.message || 'Could not create the user.');
                    if (submitBtn) submitBtn.disabled = false;
                }).catch(function () {
                    showAddUserAlert(alertBox, 'danger', 'Could not create the user. Please try again.');
                    if (submitBtn) submitBtn.disabled = false;
                });
            });
        }

        function loadAddUserForm() {
            const body = document.getElementById('addUserModalBody');
            if (!body || body.dataset.loaded === '1') return;
            body.innerHTML = '<div class="text-center text-muted py-5">Loading form…</div>';
            fetch('add-user.php?embed=1', { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) throw new Error('load failed');
                    return response.text();
                })
                .then(function (html) {
                    if (html.indexOf('id="addUserForm"') === -1) throw new Error('form missing');
                    body.innerHTML = html;
                    body.dataset.loaded = '1';
                    bindAddUserForm(body);
                })
                .catch(function () {
                    body.dataset.loaded = '';
                    body.innerHTML = '<div class="alert alert-danger mb-0">The add-user form could not be loaded.</div>';
                });
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.sensitive-toggle');
            if (!btn) return;
            const container = btn.closest('.sensitive-value');
            if (!container) return;
            const masked = container.querySelector('.sensitive-masked');
            const full = container.querySelector('.sensitive-full');
            const icon = btn.querySelector('i');
            const isShowing = full && !full.classList.contains('d-none');
            if (isShowing) {
                if (full) full.classList.add('d-none');
                if (masked) masked.classList.remove('d-none');
                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
                btn.setAttribute('aria-label', 'Show email');
            } else {
                if (full) full.classList.remove('d-none');
                if (masked) masked.classList.add('d-none');
                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
                btn.setAttribute('aria-label', 'Hide email');
            }
        });

        document.getElementById('addUserModal').addEventListener('show.bs.modal', loadAddUserForm);
        document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
            const body = document.getElementById('addUserModalBody');
            if (body) {
                body.dataset.loaded = '';
                body.innerHTML = '<div class="text-center text-muted py-5">Loading form…</div>';
            }
        });
    </script>
</body>
</html>

