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

// Get all users
$users = $db->query("SELECT u.*, b.branch_name 
                     FROM users u 
                     LEFT JOIN branches b ON u.branch_id = b.branch_id 
                     ORDER BY u.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <?php $asset_base = '../'; require __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="denthub-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="denthub-page-title"><i class="bi bi-person-gear me-2"></i> User Management</h1>
            <a href="add-user.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New User
            </a>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users->num_rows > 0): ?>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td>
                                            <span class="sensitive-value">
                                                <span class="sensitive-masked"><?php echo htmlspecialchars(maskEmail($user['email'])); ?></span>
                                                <span class="sensitive-full d-none"><?php echo htmlspecialchars($user['email']); ?></span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary sensitive-toggle ms-1">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="sensitive-value">
                                                <span class="sensitive-masked"><?php echo htmlspecialchars(maskPhone($user['phone'] ?? '')); ?></span>
                                                <span class="sensitive-full d-none"><?php echo htmlspecialchars($user['phone'] ?? ''); ?></span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary sensitive-toggle ms-1">
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
                                        <td><?php echo formatDate($user['created_at']); ?></td>
                                        <td>
                                            <a href="edit-user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </main>

    <script>
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
            } else {
                if (full) full.classList.remove('d-none');
                if (masked) masked.classList.add('d-none');
                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            }
        });
    </script>
</body>
</html>

