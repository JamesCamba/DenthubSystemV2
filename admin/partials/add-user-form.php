<?php
/** Add-user form. Expects $services, $branches, $embed. */
$embed = !empty($embed);
?>
<form id="addUserForm" method="POST" action="add-user.php">
    <div id="addUserFormAlert"></div>
    <div class="row g-4">
        <div class="col-lg-6">
            <h6 class="text-primary border-bottom pb-2 mb-3">User Details</h6>
            <div class="mb-3">
                <label class="form-label" for="addUserUsername">Username <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="addUserUsername" name="username" required
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="addUserEmail">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="addUserEmail" name="email" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="addUserFullName">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="addUserFullName" name="full_name" required
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="role">Role <span class="text-danger">*</span></label>
                <select class="form-select" name="role" id="role" required>
                    <option value="admin" <?php echo (($_POST['role'] ?? 'admin') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="dentist" <?php echo (($_POST['role'] ?? '') === 'dentist') ? 'selected' : ''; ?>>Dentist</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="addUserPhone">Phone</label>
                <input type="tel" class="form-control" id="addUserPhone" name="phone" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                <small class="text-muted">Philippines: 11 digits</small>
            </div>
            <div class="mb-3">
                <label class="form-label" for="addUserBranch">Initial Branch</label>
                <select class="form-select" id="addUserBranch" name="branch_id">
                    <?php $branches->data_seek(0); while ($branch = $branches->fetch_assoc()): ?>
                        <option value="<?php echo (int)$branch['branch_id']; ?>" <?php echo (($_POST['branch_id'] ?? 1) == $branch['branch_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Dentists can change this later.</small>
            </div>
        </div>
        <div class="col-lg-6" id="dentistFields" style="display: none;">
            <h6 class="text-primary border-bottom pb-2 mb-3">Dentist Information</h6>
            <div class="mb-3">
                <label class="form-label" for="addUserLicense">License Number</label>
                <input type="text" class="form-control" id="addUserLicense" name="license_number"
                       value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="addUserSpecialization">Specialization</label>
                <input type="text" class="form-control" id="addUserSpecialization" name="specialization"
                       placeholder="e.g., General Dentistry, Orthodontics"
                       value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Service Mastery</label>
                <p class="small text-primary mb-2">Select the services this dentist can perform.</p>
                <div class="row add-user-services">
                    <?php $services->data_seek(0); while ($service = $services->fetch_assoc()): ?>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="service_mastery[]"
                                       value="<?php echo (int)$service['service_id']; ?>"
                                       id="service_<?php echo (int)$service['service_id']; ?>"
                                       <?php echo (in_array($service['service_id'], $_POST['service_mastery'] ?? [])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="service_<?php echo (int)$service['service_id']; ?>">
                                    <?php echo htmlspecialchars($service['service_name']); ?>
                                </label>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <small class="text-muted">A default schedule (Monday–Friday, 9 AM – 5 PM) is created automatically.</small>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-3 mb-0">
        <i class="bi bi-info-circle"></i> A temporary password is generated and sent to the user's email. They should change it on first login.
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <?php if ($embed): ?>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <?php else: ?>
            <a href="users.php" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Create User</button>
    </div>
</form>
