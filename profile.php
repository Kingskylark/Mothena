<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Update Profile';
$current_user = getCurrentUser();
$success = $error = '';

if ($_POST) {
    $name = sanitize($_POST['name']);
    $age = (int) $_POST['age'];
    $due_date = sanitize($_POST['due_date']);
    $trimester = (int) $_POST['trimester'];
    $interests = isset($_POST['interests']) ? implode(',', $_POST['interests']) : '';
    $location = sanitize($_POST['location'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $language_preference = sanitize($_POST['language_preference'] ?? 'english');
    
    // Process medical history
    $personal_medical = isset($_POST['personal_medical']) ? implode(',', $_POST['personal_medical']) : '';
    $family_medical = isset($_POST['family_medical']) ? implode(',', $_POST['family_medical']) : '';
    $allergies = isset($_POST['allergies']) ? implode(',', $_POST['allergies']) : '';
    $medical_history_details = sanitize($_POST['medical_history_details'] ?? '');
    
    // Combine all medical information
    $medical_history_parts = array_filter([
        $personal_medical ? "Personal: $personal_medical" : '',
        $family_medical ? "Family: $family_medical" : '',
        $allergies ? "Allergies: $allergies" : '',
        $medical_history_details ? "Details: $medical_history_details" : ''
    ]);
    $medical_history = implode(' | ', $medical_history_parts);

    // Validation
    if (empty($name) || $age < 13 || $age > 60 || $trimester < 1 || $trimester > 3) {
        $error = 'Please provide valid input values.';
    } elseif (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } else {
        // Use prepared statement for security
        $query = "UPDATE users SET name=?, age=?, due_date=?, trimester=?, interests=?, location=?, phone=?, medical_history=?, language_preference=?, updated_at=NOW() WHERE id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sisssssssi", $name, $age, $due_date, $trimester, $interests, $location, $phone, $medical_history, $language_preference, $current_user['id']);
        
        if ($stmt->execute()) {
            $success = 'Profile updated successfully.';
            $current_user = getCurrentUser(); // Refresh user data
        } else {
            $error = 'Update failed. Please try again.';
        }
    }
}

// Parse existing medical history for display
$existing_medical = [];
if (!empty($current_user['medical_history'])) {
    $medical_parts = explode(' | ', $current_user['medical_history']);
    foreach ($medical_parts as $part) {
        if (strpos($part, 'Personal: ') === 0) {
            $existing_medical['personal'] = explode(',', substr($part, 10));
        } elseif (strpos($part, 'Family: ') === 0) {
            $existing_medical['family'] = explode(',', substr($part, 8));
        } elseif (strpos($part, 'Allergies: ') === 0) {
            $existing_medical['allergies'] = explode(',', substr($part, 11));
        } elseif (strpos($part, 'Details: ') === 0) {
            $existing_medical['details'] = substr($part, 9);
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-user-edit"></i> Edit Profile</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <!-- Basic Information -->
                        <h5 class="mb-3 text-primary"><i class="fas fa-user"></i> Basic Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($current_user['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($current_user['email']); ?>" disabled>
                                <small class="form-text text-muted">Email cannot be changed</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Age *</label>
                                <input type="number" class="form-control" name="age" value="<?php echo $current_user['age']; ?>" min="13" max="60" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" placeholder="e.g., +234 801 234 5678">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Language Preference</label>
                                <select class="form-control" name="language_preference">
                                    <option value="english" <?php echo ($current_user['language_preference'] ?? 'english') == 'english' ? 'selected' : ''; ?>>English</option>
                                    <option value="hausa" <?php echo ($current_user['language_preference'] ?? '') == 'hausa' ? 'selected' : ''; ?>>Hausa</option>
                                    <option value="yoruba" <?php echo ($current_user['language_preference'] ?? '') == 'yoruba' ? 'selected' : ''; ?>>Yoruba</option>
                                    <option value="igbo" <?php echo ($current_user['language_preference'] ?? '') == 'igbo' ? 'selected' : ''; ?>>Igbo</option>
                                    <option value="french" <?php echo ($current_user['language_preference'] ?? '') == 'french' ? 'selected' : ''; ?>>French</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($current_user['location'] ?? ''); ?>" placeholder="e.g., Lagos, Nigeria">
                            <small class="form-text text-muted">City, State or Country</small>
                        </div>

                        <!-- Pregnancy Information -->
                        <h5 class="mb-3 text-primary mt-4"><i class="fas fa-baby"></i> Pregnancy Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expected Due Date</label>
                                <input type="date" class="form-control" name="due_date" value="<?php echo $current_user['due_date']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Trimester *</label>
                                <select class="form-control" name="trimester" required>
                                    <option value="1" <?php echo $current_user['trimester'] == 1 ? 'selected' : ''; ?>>First Trimester (1-12 weeks)</option>
                                    <option value="2" <?php echo $current_user['trimester'] == 2 ? 'selected' : ''; ?>>Second Trimester (13-26 weeks)</option>
                                    <option value="3" <?php echo $current_user['trimester'] == 3 ? 'selected' : ''; ?>>Third Trimester (27-40 weeks)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Medical History -->
                        <h5 class="mb-3 text-primary"><i class="fas fa-heartbeat"></i> Medical History & Family Background</h5>
                        
                        <!-- Personal Medical History -->
                        <div class="mb-3">
                            <h6 class="text-secondary mb-2">Personal Medical History</h6>
                            <div class="row">
                                <?php
                                $personal_conditions = [
                                    'hypertension' => 'High Blood Pressure/Hypertension',
                                    'diabetes' => 'Diabetes',
                                    'gestational_diabetes' => 'Previous Gestational Diabetes',
                                    'asthma' => 'Asthma',
                                    'heart_disease' => 'Heart Disease',
                                    'kidney_disease' => 'Kidney Disease',
                                    'thyroid_disorder' => 'Thyroid Disorder',
                                    'epilepsy' => 'Epilepsy/Seizure Disorder',
                                    'depression_anxiety' => 'Depression/Anxiety',
                                    'blood_clotting' => 'Blood Clotting Disorders',
                                    'anemia' => 'Anemia',
                                    'previous_miscarriage' => 'Previous Miscarriage',
                                    'previous_preterm' => 'Previous Preterm Birth',
                                    'previous_cesarean' => 'Previous C-Section'
                                ];
                                $selected_personal = $existing_medical['personal'] ?? [];
                                foreach ($personal_conditions as $key => $label):
                                ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="personal_medical[]" 
                                                   value="<?php echo $key; ?>" id="personal_<?php echo $key; ?>">
                                                   <?php echo in_array($key, $selected_personal) ? 'checked' : ''; ?>
                                            <label class="form-check-label small" for="personal_<?php echo $key; ?>">
                                                <?php echo $label; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Family History -->
                        <div class="mb-3">
                            <h6 class="text-secondary mb-2">Family History</h6>
                            <div class="row">
                                <?php
                                $family_conditions = [
                                    'family_hypertension' => 'High Blood Pressure',
                                    'family_diabetes' => 'Diabetes',
                                    'family_heart_disease' => 'Heart Disease',
                                    'family_stroke' => 'Stroke',
                                    'family_cancer' => 'Cancer',
                                    'family_mental_health' => 'Mental Health Disorders',
                                    'family_birth_defects' => 'Birth Defects',
                                    'family_genetic_disorders' => 'Genetic Disorders',
                                    'family_twins' => 'History of Twins/Multiple Births',
                                    'family_pregnancy_complications' => 'Pregnancy Complications'
                                ];
                                $selected_family = $existing_medical['family'] ?? [];
                                foreach ($family_conditions as $key => $label):
                                ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="family_medical[]" 
                                                   value="<?php echo $key; ?>" id="family_<?php echo $key; ?>">
                                                   <?php echo in_array($key, $selected_family) ? 'checked' : ''; ?>
                                            <label class="form-check-label small" for="family_<?php echo $key; ?>">
                                                <?php echo $label; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Allergies -->
                        <div class="mb-3">
                            <h6 class="text-secondary mb-2">Known Allergies</h6>
                            <div class="row">
                                <?php
                                $allergies = [
                                    'drug_allergies' => 'Drug/Medication Allergies',
                                    'food_allergies' => 'Food Allergies',
                                    'environmental_allergies' => 'Environmental Allergies',
                                    'latex_allergy' => 'Latex Allergy'
                                ];
                                $selected_allergies = $existing_medical['allergies'] ?? [];
                                foreach ($allergies as $key => $label):
                                ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="allergies[]" 
                                                   value="<?php echo $key; ?>" id="allergy_<?php echo $key; ?>"
                                                   <?php echo in_array($key, $selected_allergies) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="allergy_<?php echo $key; ?>">
                                                <?php echo $label; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Additional Medical Details -->
                        <div class="mb-4">
                            <label for="medical_history_details" class="form-label">Additional Medical Information</label>
                            <textarea class="form-control" name="medical_history_details" rows="3"
                                      placeholder="Please provide any additional details about the conditions selected above, current medications, or other relevant health information..."><?php echo htmlspecialchars($existing_medical['details'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Include specific medications, severity of conditions, or any other important details.</small>
                        </div>

                        <!-- Interests -->
                        <h5 class="mb-3 text-primary"><i class="fas fa-heart"></i> Areas of Interest</h5>
                        
                        <div class="mb-4">
                            <label class="form-label">Select topics you'd like to learn more about</label>
                            <?php
                                $interest_options = [
                                    'nutrition' => 'Nutrition & Diet',
                                    'exercise' => 'Exercise & Fitness',
                                    'mental_health' => 'Mental Health & Wellness',
                                    'baby_development' => 'Baby Development',
                                    'labor_preparation' => 'Labor & Delivery Preparation',
                                    'breastfeeding' => 'Breastfeeding & Lactation'
                                ];
                                $user_interests = explode(',', $current_user['interests']);
                            ?>
                            <div class="row">
                                <?php foreach ($interest_options as $key => $label): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="interests[]" value="<?php echo $key; ?>"
                                                <?php echo in_array($key, $user_interests) ? 'checked' : ''; ?> id="interest_<?php echo $key; ?>">
                                            <label class="form-check-label" for="interest_<?php echo $key; ?>"><?php echo $label; ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Information Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($current_user['created_at'])); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo $current_user['updated_at'] ? date('F j, Y g:i A', strtotime($current_user['updated_at'])) : 'Never'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Last Login:</strong> <?php echo $current_user['last_login'] ? date('F j, Y g:i A', strtotime($current_user['last_login'])) : 'Never'; ?></p>
                            <p><strong>Account Status:</strong> <span class="badge bg-success">Active</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>