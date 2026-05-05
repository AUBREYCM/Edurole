-- Users & Roles
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student','parent') NOT NULL DEFAULT 'student',
    phone VARCHAR(20),
    address TEXT,
    profile_photo VARCHAR(255),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Programmes / Courses
CREATE TABLE programmes (
    programme_id INT AUTO_INCREMENT PRIMARY KEY,
    programme_name VARCHAR(100) NOT NULL,
    description TEXT,
    duration_years INT,
    department VARCHAR(100),
    status ENUM('active','inactive') DEFAULT 'active'
);

-- Subjects
CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) UNIQUE,
    programme_id INT,
    credits INT DEFAULT 1,
    FOREIGN KEY (programme_id) REFERENCES programmes(programme_id)
);

-- Classes
CREATE TABLE classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    programme_id INT,
    year_level INT,
    academic_year VARCHAR(20),
    FOREIGN KEY (programme_id) REFERENCES programmes(programme_id)
);

-- Student Enrollments
CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    class_id INT,
    programme_id INT,
    enrollment_date DATE,
    status ENUM('active','completed','withdrawn') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (class_id) REFERENCES classes(class_id),
    FOREIGN KEY (programme_id) REFERENCES programmes(programme_id)
);

-- Teacher Assignments
CREATE TABLE teacher_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT,
    subject_id INT,
    class_id INT,
    academic_year VARCHAR(20),
    FOREIGN KEY (teacher_id) REFERENCES users(user_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (class_id) REFERENCES classes(class_id)
);

-- Timetable
CREATE TABLE timetable (
    timetable_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT,
    subject_id INT,
    teacher_id INT,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday'),
    start_time TIME,
    end_time TIME,
    room VARCHAR(50),
    FOREIGN KEY (class_id) REFERENCES classes(class_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (teacher_id) REFERENCES users(user_id)
);

-- Attendance
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    class_id INT,
    subject_id INT,
    attendance_date DATE,
    status ENUM('present','absent','late','excused') DEFAULT 'present',
    marked_by INT,
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (class_id) REFERENCES classes(class_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (marked_by) REFERENCES users(user_id)
);

-- Grades
CREATE TABLE grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    subject_id INT,
    class_id INT,
    assessment_type ENUM('assignment','test','exam','activity') DEFAULT 'exam',
    assessment_name VARCHAR(100),
    marks_obtained DECIMAL(5,2),
    total_marks DECIMAL(5,2),
    weight DECIMAL(5,2) DEFAULT 100,
    grade_date DATE,
    entered_by INT,
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (class_id) REFERENCES classes(class_id),
    FOREIGN KEY (entered_by) REFERENCES users(user_id)
);

-- Fee Structure
CREATE TABLE fee_structure (
    fee_id INT AUTO_INCREMENT PRIMARY KEY,
    programme_id INT,
    fee_name VARCHAR(100),
    amount DECIMAL(10,2),
    academic_year VARCHAR(20),
    due_date DATE,
    FOREIGN KEY (programme_id) REFERENCES programmes(programme_id)
);

-- Fee Payments
CREATE TABLE fee_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    fee_id INT,
    amount_paid DECIMAL(10,2),
    payment_date DATE,
    payment_method ENUM('cash','bank_transfer','mobile_money') DEFAULT 'cash',
    receipt_number VARCHAR(50) UNIQUE,
    recorded_by INT,
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (fee_id) REFERENCES fee_structure(fee_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

-- Staff / HR
CREATE TABLE staff_profiles (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    department VARCHAR(100),
    position VARCHAR(100),
    employment_type ENUM('full_time','part_time','contract') DEFAULT 'full_time',
    hire_date DATE,
    salary DECIMAL(10,2),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Leave Management
CREATE TABLE leave_requests (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    leave_type ENUM('sick','annual','maternity','emergency') DEFAULT 'annual',
    start_date DATE,
    end_date DATE,
    reason TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
);

-- Notice Board
CREATE TABLE notices (
    notice_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    posted_by INT,
    target_role ENUM('all','student','teacher','parent') DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(user_id)
);

-- Parent-Student Link
CREATE TABLE parent_student (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT,
    student_id INT,
    relationship VARCHAR(50),
    FOREIGN KEY (parent_id) REFERENCES users(user_id),
    FOREIGN KEY (student_id) REFERENCES users(user_id)
);