-- ============================================
-- COMPLETE ANTENATAL WEBSITE DATABASE
-- Copy and paste this entire code into phpMyAdmin
-- ============================================

-- Create the database
CREATE DATABASE antenatal_db;
USE antenatal_db;

-- ============================================
-- 1. USERS TABLE
-- ============================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    age INT NOT NULL,
    trimester INT NOT NULL CHECK (trimester IN (1, 2, 3)),
    interests TEXT,  -- Comma-separated: "nutrition,mental_health,exercise"
    location VARCHAR(100),
    language_preference VARCHAR(20) DEFAULT 'english',
    medical_history TEXT,
    due_date DATE,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Add is_active column to users table
ALTER TABLE users 
ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 
COMMENT 'User account status: 1 = active, 0 = inactive';

-- Optional: Add an index on is_active for better query performance
CREATE INDEX idx_users_is_active ON users(is_active);


-- ============================================
-- 2. CONTENT LIBRARY TABLE
-- ============================================
CREATE TABLE content (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content_text LONGTEXT NOT NULL,
    trimester INT NOT NULL CHECK (trimester IN (1, 2, 3)),
    age_group ENUM('teen', 'young_adult', 'adult', 'mature_adult', 'senior_adult', 'all') DEFAULT 'all',
    category VARCHAR(50) NOT NULL, -- nutrition, mental_health, exercise, labor_prep, etc.
    content_type ENUM('article', 'tip', 'checklist', 'video', 'audio') DEFAULT 'article',
    language VARCHAR(20) DEFAULT 'english',
    is_featured BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE content ADD COLUMN image_url VARCHAR(255) AFTER content_text;


-- ============================================
-- 3. USER INTERACTIONS TABLE
-- ============================================
CREATE TABLE user_interactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    interaction_type ENUM('view', 'like', 'bookmark', 'share') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
);

-- ============================================
-- 4. REMINDERS TABLE
-- ============================================
CREATE TABLE reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reminder_type ENUM('appointment', 'medication', 'milestone', 'custom') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    due_time TIME,
    is_sent BOOLEAN DEFAULT FALSE,
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- 5. USER PREFERENCES TABLE
-- ============================================
CREATE TABLE user_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    preference_key VARCHAR(50) NOT NULL,
    preference_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id, preference_key)
);

-- Create content_views table to track individual view records
CREATE TABLE content_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    INDEX idx_content_user (content_id, user_id),
    INDEX idx_viewed_at (viewed_at),
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Optional: Add index to your existing content table for better performance
ALTER TABLE content ADD INDEX idx_view_count (view_count);

-- Optional: If you want to initialize view_count to 0 for existing content
UPDATE content SET view_count = 0 WHERE view_count IS NULL;
-- ============================================
-- SAMPLE DATA INSERTION
-- ============================================

-- Insert sample users
INSERT INTO users (name, email, password, age, trimester, interests, location, due_date, phone) VALUES
('Amina Hassan', 'amina@email.com', MD5('password123'), 22, 2, 'nutrition,exercise,mental_health', 'Lagos', '2025-09-15', '08012345671'),
('Fatima Bello', 'fatima@email.com', MD5('password123'), 28, 1, 'nutrition,labor_prep', 'Abuja', '2025-12-10', '08012345672'),
('Kemi Adebayo', 'kemi@email.com', MD5('password123'), 18, 3, 'mental_health,parenting', 'Ibadan', '2025-07-20', '08012345673'),
('Grace Okafor', 'grace@email.com', MD5('password123'), 32, 2, 'exercise,nutrition,complications', 'Port Harcourt', '2025-08-30', '08012345674'),
('Aisha Musa', 'aisha@email.com', MD5('password123'), 25, 1, 'nutrition,mental_health', 'Kano', '2025-11-25', '08012345675');

-- Insert comprehensive content for all trimesters and categories

-- FIRST TRIMESTER CONTENT
INSERT INTO content (title, description, content_text, trimester, age_group, category, content_type, is_featured) VALUES

-- Nutrition Content
('Essential Nutrients in First Trimester', 'Key vitamins and minerals needed during early pregnancy', 
'During your first trimester, your body needs extra folic acid (400-800 mcg daily), iron (27mg daily), and calcium (1000mg daily). Folic acid prevents neural tube defects, iron prevents anemia, and calcium supports bone development. Good sources include leafy greens, fortified cereals, lean meats, and dairy products. Take prenatal vitamins as recommended by your healthcare provider.', 
1, 'all', 'nutrition', 'article', TRUE),

('Managing Morning Sickness Through Diet', 'Natural ways to reduce nausea and vomiting', 
'Morning sickness affects 70-80% of pregnant women. Try eating small, frequent meals, avoiding spicy or fatty foods, and eating crackers before getting out of bed. Ginger tea, lemon water, and vitamin B6 supplements can help. If you cannot keep food down for 24 hours, contact your healthcare provider immediately.', 
1, 'all', 'nutrition', 'tip', FALSE),

-- Mental Health Content
('Emotional Changes in Early Pregnancy', 'Understanding mood swings and anxiety', 
'Hormonal changes during pregnancy can cause mood swings, anxiety, and emotional sensitivity. This is completely normal. Practice relaxation techniques, get adequate sleep, and talk to supportive friends and family. If you feel persistently sad or anxious, speak with your healthcare provider about counseling options.', 
1, 'all', 'mental_health', 'article', FALSE),

('Building Your Support System', 'Creating emotional support during pregnancy', 
'A strong support system is crucial during pregnancy. This includes your partner, family, friends, and healthcare team. Join pregnancy support groups, connect with other expectant mothers, and do not hesitate to ask for help when needed. Consider couples counseling if relationship stress increases.', 
1, 'young_adult', 'mental_health', 'article', FALSE),

-- Exercise Content
('Safe Exercises in First Trimester', 'Low-impact activities for early pregnancy', 
'Unless your doctor advises otherwise, moderate exercise is safe and beneficial. Try walking, swimming, prenatal yoga, or stationary cycling. Avoid contact sports, activities with fall risk, and exercises lying flat on your back. Aim for 150 minutes of moderate activity per week. Stop if you experience bleeding, dizziness, or chest pain.', 
1, 'all', 'exercise', 'article', TRUE),

-- Teen-specific content
('Telling Your Parents About Pregnancy', 'Guide for young mothers', 
'Telling your parents about pregnancy can be scary. Choose a calm moment, be honest about your situation, and be prepared for various reactions. Consider having a trusted adult present for support. Remember that initial shock often gives way to acceptance and support. Focus on discussing your plans and needs moving forward.', 
1, 'teen', 'mental_health', 'article', FALSE),

-- SECOND TRIMESTER CONTENT
('Nutrition for Fetal Brain Development', 'Foods that support your baby\'s growth', 
'The second trimester is crucial for brain development. Include omega-3 fatty acids (fish, walnuts), protein (lean meats, beans), and choline (eggs, lean meats). Aim for 300 extra calories daily. Continue taking prenatal vitamins and stay hydrated with 8-10 glasses of water daily. Avoid high-mercury fish and raw foods.', 
2, 'all', 'nutrition', 'article', TRUE),

('Exercise Modifications for Growing Belly', 'Adapting your workout routine', 
'As your belly grows, modify exercises to accommodate your changing body. Avoid exercises on your back after 20 weeks, use support belts if needed, and focus on posture. Swimming is excellent for reducing joint stress. Pelvic floor exercises help prepare for delivery. Listen to your body and rest when needed.', 
2, 'all', 'exercise', 'article', FALSE),

('Preparing for Maternity Leave', 'Planning your time off work', 
'Start planning maternity leave early. Know your company\'s policies, save money for reduced income, and discuss workload transition with colleagues. Consider your desired leave length and childcare arrangements for your return. Document your current projects and train replacements where possible.', 
2, 'adult', 'work_life', 'article', FALSE),

('Body Changes and Self-Image', 'Embracing your changing body', 
'Your body will change significantly during pregnancy. Weight gain, stretch marks, and body shape changes are normal. Focus on what your body is accomplishing rather than appearance. Wear comfortable, well-fitting clothes, practice good posture, and remember that most changes are temporary.', 
2, 'all', 'mental_health', 'article', FALSE),

('Relationship Changes During Pregnancy', 'Maintaining intimacy and communication', 
'Pregnancy can affect relationships. Communicate openly with your partner about concerns, needs, and expectations. Physical intimacy may change due to comfort and energy levels. Attend prenatal appointments together when possible and discuss parenting roles and responsibilities.', 
2, 'young_adult', 'relationships', 'article', FALSE),

-- THIRD TRIMESTER CONTENT
('Signs of Labor: When to Call Your Doctor', 'Recognizing true labor signs', 
'True labor signs include regular contractions 5 minutes apart for 1 hour, water breaking, bloody show, and increasing pelvic pressure. Call your doctor immediately if you experience severe abdominal pain, heavy bleeding, decreased fetal movement, or severe headaches with vision changes. Have your hospital bag ready by 36 weeks.', 
3, 'all', 'labor_prep', 'article', TRUE),

('Hospital Bag Checklist', 'What to pack for delivery', 
'Pack your hospital bag by 36 weeks. Include: comfortable going-home outfit (maternity size), nursing bras, comfortable slippers, toiletries, phone charger, insurance cards, birth plan copies, baby outfit in newborn and 0-3 month sizes, car seat (properly installed), and comfort items like pillows or music.', 
3, 'all', 'labor_prep', 'checklist', TRUE),

('Pain Management Options During Labor', 'Understanding your choices', 
'Pain relief options include natural methods (breathing, positioning, water therapy), medications (epidural, narcotic pain relievers), and alternative methods (massage, acupuncture). Discuss preferences with your healthcare provider and include them in your birth plan. Remember that plans can change during labor.', 
3, 'all', 'labor_prep', 'article', FALSE),

('Preparing Older Children for Baby', 'Helping siblings adjust', 
'Prepare older children by reading books about new babies, involving them in preparations, and maintaining routines. Explain changes in age-appropriate terms and reassure them of your continued love. Consider having them visit the hospital shortly after delivery and give them a special role as "big brother/sister."', 
3, 'mature_adult', 'parenting', 'article', FALSE),

('High-Risk Pregnancy Monitoring', 'Special considerations for older mothers', 
'Women over 35 may need additional monitoring including more frequent ultrasounds, blood pressure checks, and glucose testing. Be aware of signs requiring immediate medical attention: severe headaches, vision changes, upper abdominal pain, and decreased fetal movement. Maintain all scheduled appointments and follow medical advice closely.', 
3, 'senior_adult', 'complications', 'article', FALSE),

('Postpartum Recovery Planning', 'Preparing for after delivery', 
'Plan for postpartum recovery by arranging help with household tasks, preparing easy meals, setting up a comfortable nursing area, and discussing postpartum depression signs with your partner. Stock up on postpartum supplies: pads, comfortable underwear, nursing pads, and pain relief medication as recommended by your doctor.', 
3, 'all', 'postpartum', 'article', FALSE);

-- Insert sample reminders
INSERT INTO reminders (user_id, reminder_type, title, description, due_date, due_time) VALUES
(1, 'appointment', 'ANC Appointment', 'Monthly check-up with Dr. Smith', '2025-06-15', '10:00:00'),
(1, 'medication', 'Prenatal Vitamins', 'Take daily prenatal vitamins', '2025-06-01', '08:00:00'),
(2, 'appointment', 'First Ultrasound', 'Dating scan appointment', '2025-06-20', '14:30:00'),
(3, 'milestone', 'Hospital Bag', 'Pack hospital bag for delivery', '2025-06-10', NULL),
(4, 'appointment', 'Glucose Test', 'Gestational diabetes screening', '2025-06-25', '09:00:00');

-- Insert user preferences
INSERT INTO user_preferences (user_id, preference_key, preference_value) VALUES
(1, 'notification_frequency', 'daily'),
(1, 'preferred_content_type', 'article,tip'),
(2, 'notification_frequency', 'weekly'),
(3, 'preferred_content_type', 'video,article'),
(4, 'notification_frequency', 'daily'),
(5, 'preferred_content_type', 'article');

-- ============================================
-- USEFUL INDEXES FOR BETTER PERFORMANCE
-- ============================================
CREATE INDEX idx_content_trimester ON content(trimester);
CREATE INDEX idx_content_category ON content(category);
CREATE INDEX idx_content_age_group ON content(age_group);
CREATE INDEX idx_users_trimester ON users(trimester);
CREATE INDEX idx_users_age ON users(age);
CREATE INDEX idx_user_interactions_user ON user_interactions(user_id);
CREATE INDEX idx_reminders_user_date ON reminders(user_id, due_date);


-- ============================================
-- 6. ADMIN USERS TABLE
-- ============================================
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    permissions TEXT, -- JSON string of permissions
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- ============================================
-- 7. ADMIN ACTIVITY LOG TABLE 
-- ============================================
CREATE TABLE admin_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL, -- 'user_created', 'content_deleted', 'user_banned', etc.
    target_type ENUM('user', 'content', 'admin', 'system') NOT NULL,
    target_id INT NULL, -- ID of the affected record
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- ============================================
-- SAMPLE ADMIN USERS
-- ============================================

-- Insert sample admin users
INSERT INTO admin_users (username, email, password, full_name, role, permissions, is_active) VALUES
('superadmin', 'admin@antenatal.com', MD5('admin123'), 'System Administrator', 'super_admin', 
'{"users": ["create", "read", "update", "delete"], "content": ["create", "read", "update", "delete"], "admin": ["create", "read", "update", "delete"], "system": ["read", "update"]}', 
TRUE),

('content_admin', 'content@antenatal.com', MD5('content123'), 'Content Manager', 'admin', 
'{"users": ["read"], "content": ["create", "read", "update", "delete"], "admin": ["read"], "system": ["read"]}', 
TRUE),

('user_admin', 'users@antenatal.com', MD5('users123'), 'User Manager', 'admin', 
'{"users": ["create", "read", "update", "delete"], "content": ["read"], "admin": ["read"], "system": ["read"]}', 
TRUE),

('moderator', 'mod@antenatal.com', MD5('mod123'), 'Content Moderator', 'moderator', 
'{"users": ["read"], "content": ["read", "update"], "admin": [], "system": ["read"]}', 
TRUE);

-- ============================================
-- ADDITIONAL INDEXES FOR ADMIN TABLES
-- ============================================
CREATE INDEX idx_admin_username ON admin_users(username);
CREATE INDEX idx_admin_email ON admin_users(email);
CREATE INDEX idx_admin_role ON admin_users(role);
CREATE INDEX idx_admin_active ON admin_users(is_active);
CREATE INDEX idx_admin_log_admin ON admin_activity_log(admin_id);
CREATE INDEX idx_admin_log_action ON admin_activity_log(action);
CREATE INDEX idx_admin_log_date ON admin_activity_log(created_at);


-- ============================================
-- ADMIN LOGIN CREDENTIALS FOR TESTING
-- ============================================
/*
SUPER ADMIN:
Username: superadmin
Email: admin@antenatal.com
Password: admin123
Role: super_admin (Full access to everything)

CONTENT ADMIN:
Username: content_admin
Email: content@antenatal.com
Password: content123
Role: admin (Can manage content, view users)

USER ADMIN:
Username: user_admin
Email: users@antenatal.com
Password: users123
Role: admin (Can manage users, view content)

MODERATOR:
Username: moderator
Email: mod@antenatal.com
Password: mod123
Role: moderator (Can moderate content, view users)
*/

CREATE TABLE user_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    admin_reply TEXT NULL,
    replied_at TIMESTAMP NULL,
    replied_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (replied_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

ALTER TABLE user_messages 
ADD COLUMN IF NOT EXISTS status ENUM('pending', 'replied', 'closed') DEFAULT 'pending' AFTER message,
ADD COLUMN IF NOT EXISTS replied_at TIMESTAMP NULL DEFAULT NULL AFTER admin_reply;

-- Change is_read to TINYINT if it's not already (0 = unread, 1 = read)
ALTER TABLE user_messages 
MODIFY COLUMN is_read TINYINT(1) DEFAULT 0;

-- Set status to 'replied' where admin_reply exists and is not empty
UPDATE user_messages 
SET status = 'replied' 
WHERE admin_reply IS NOT NULL AND admin_reply != '' AND status = 'pending';

-- Set replied_at timestamp for existing replies (use created_at as fallback)
UPDATE user_messages 
SET replied_at = created_at 
WHERE admin_reply IS NOT NULL AND admin_reply != '' AND replied_at IS NULL;

-- Optional: Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_user_messages_user_status ON user_messages(user_id, status);
CREATE INDEX IF NOT EXISTS idx_user_messages_user_read ON user_messages(user_id, is_read);

