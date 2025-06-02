<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$page_title = 'Register';
$error = '';
$success = '';

if ($_POST) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $due_date = sanitize($_POST['due_date'] ?? '');
    $trimester = (int)($_POST['trimester'] ?? 1);
    $interests = isset($_POST['interests']) ? implode(',', $_POST['interests']) : '';
    $location = sanitize($_POST['location'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
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
    
    $language_preference = sanitize($_POST['language_preference'] ?? 'english');

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif ($age < 13 || $age > 60) {
        $error = 'Please enter a valid age between 13 and 60.';
    } elseif (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            $error = 'Email address is already registered.';
        } else {
            // Create new user
            $hashed_password = hashPassword($password);
            $query = "INSERT INTO users (name, email, password, age, due_date, trimester, interests, location, phone, medical_history, language_preference, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssisssssss", $name, $email, $hashed_password, $age, $due_date, $trimester, $interests, $location, $phone, $medical_history, $language_preference);
            
            if ($stmt->execute()) {
                $success = 'Account created successfully! You can now login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card">
                <div class="card-header text-center">
                    <h4><i class="fas fa-user-plus"></i> Create Your Account</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <br><a href="login.php" class="btn btn-primary btn-sm mt-2">Login Now</a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <!-- Basic Information -->
                            <h5 class="mb-3 text-primary"><i class="fas fa-user"></i> Basic Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="form-text text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="age" class="form-label">Age *</label>
                                    <input type="number" class="form-control" id="age" name="age" min="13" max="60"
                                           value="<?php echo $_POST['age'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                           placeholder="e.g., +234 801 234 5678">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="language_preference" class="form-label">Language Preference</label>
                                    <select class="form-control" id="language_preference" name="language_preference">
                                        <option value="english" <?php echo ($_POST['language_preference'] ?? 'english') == 'english' ? 'selected' : ''; ?>>English</option>
                                        <option value="hausa" <?php echo ($_POST['language_preference'] ?? '') == 'hausa' ? 'selected' : ''; ?>>Hausa</option>
                                        <option value="yoruba" <?php echo ($_POST['language_preference'] ?? '') == 'yoruba' ? 'selected' : ''; ?>>Yoruba</option>
                                        <option value="igbo" <?php echo ($_POST['language_preference'] ?? '') == 'igbo' ? 'selected' : ''; ?>>Igbo</option>
                                        <option value="french" <?php echo ($_POST['language_preference'] ?? '') == 'french' ? 'selected' : ''; ?>>French</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location"
                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                                       placeholder="e.g., Lagos, Nigeria">
                                <small class="form-text text-muted">City, State or Country</small>
                            </div>
                            
                            <!-- Pregnancy Information -->
                            <h5 class="mb-3 text-primary mt-4"><i class="fas fa-baby"></i> Pregnancy Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="due_date" class="form-label">Expected Due Date</label>
                                    <input type="date" class="form-control" id="due_date" name="due_date"
                                           value="<?php echo $_POST['due_date'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="trimester" class="form-label">Current Trimester *</label>
                                    <select class="form-control" id="trimester" name="trimester" required>
                                        <option value="">Select Trimester</option>
                                        <option value="1" <?php echo ($_POST['trimester'] ?? '') == '1' ? 'selected' : ''; ?>>First Trimester (1-12 weeks)</option>
                                        <option value="2" <?php echo ($_POST['trimester'] ?? '') == '2' ? 'selected' : ''; ?>>Second Trimester (13-26 weeks)</option>
                                        <option value="3" <?php echo ($_POST['trimester'] ?? '') == '3' ? 'selected' : ''; ?>>Third Trimester (27-40 weeks)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Medical History & Family Background</label>
                                <p class="text-muted small mb-3">Please select any conditions that apply to you or your family history:</p>
                                
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
                                        $selected_personal = isset($_POST['personal_medical']) ? $_POST['personal_medical'] : [];
                                        foreach ($personal_conditions as $key => $label):
                                        ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="personal_medical[]" 
                                                           value="<?php echo $key; ?>" id="personal_<?php echo $key; ?>"
                                                           <?php echo in_array($key, $selected_personal) ? 'checked' : ''; ?>>
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
                                        $selected_family = isset($_POST['family_medical']) ? $_POST['family_medical'] : [];
                                        foreach ($family_conditions as $key => $label):
                                        ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="family_medical[]" 
                                                           value="<?php echo $key; ?>" id="family_<?php echo $key; ?>"
                                                           <?php echo in_array($key, $selected_family) ? 'checked' : ''; ?>>
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
                                        $selected_allergies = isset($_POST['allergies']) ? $_POST['allergies'] : [];
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
                                
                                <!-- Additional Details -->
                                <div class="mb-3">
                                    <label for="medical_history_details" class="form-label">Additional Medical Information</label>
                                    <textarea class="form-control" id="medical_history_details" name="medical_history_details" rows="3"
                                              placeholder="Please provide any additional details about the conditions selected above, current medications, or other relevant health information..."><?php echo htmlspecialchars($_POST['medical_history_details'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Include specific medications, severity of conditions, or any other important details.</small>
                                </div>
                                
                                <small class="form-text text-muted">
                                    <i class="fas fa-shield-alt"></i> All medical information is kept strictly confidential and will only be used to provide better personalized pregnancy care advice.
                                </small>
                            </div>
                            
                            <!-- Interests -->
                            <h5 class="mb-3 text-primary"><i class="fas fa-heart"></i> Areas of Interest</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Select topics you'd like to learn more about (Optional)</label>
                                <div class="row">
                                    <?php
                                    $interest_options = [
                                        'nutrition' => 'Nutrition & Diet',
                                        'exercise' => 'Exercise & Fitness',
                                        'mental_health' => 'Mental Health & Wellness',
                                        'baby_development' => 'Baby Development',
                                        'labor_preparation' => 'Labor & Delivery Preparation',
                                        'breastfeeding' => 'Breastfeeding & Lactation'
                                    ];
                                    $selected_interests = $_POST['interests'] ?? [];
                                    foreach ($interest_options as $key => $label):
                                    ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="interests[]" 
                                                       value="<?php echo $key; ?>" id="interest_<?php echo $key; ?>"
                                                       <?php echo in_array($key, $selected_interests) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="interest_<?php echo $key; ?>">
                                                    <?php echo $label; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>